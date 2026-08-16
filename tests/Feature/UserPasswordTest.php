<?php

use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppToken;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Keep the key derivation cheap, the cost is not what these tests check.
    VaultService::useDerivationCost(1, 8192);

    $this->users = app(UserService::class);
    $this->user = $this->users->register('Example', 'user@example.test', 'correct horse');
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    VaultService::useSystemKeys(null, null);
});

it('has no column for either token', function (): void {
    expect(Schema::hasColumn(AppUser::TABLE, 'remember_token'))->toBeFalse()
        ->and(Schema::hasTable('app_users_resets'))->toBeFalse();
});

it('numbers the system token types out of reach of an application', function (): void {
    expect(ESystemTokenType::PasswordReset->value)->toBeGreaterThanOrEqual(100000)
        ->and(ESystemTokenType::Remember->value)->toBeGreaterThanOrEqual(100000);
});

it('keeps the remember me token in the tokens table', function (): void {
    $this->user->setRememberToken('a-remembered-token');

    expect($this->user->getRememberToken())->toBe('a-remembered-token')
        ->and(AppToken::query()->ofType(ESystemTokenType::Remember)->count())->toBe(1)
        ->and(AppToken::query()->ofType(ESystemTokenType::Remember)->first()->tokenable_id)
        ->toBe($this->user->id);
});

it('keeps one remember me token at a time', function (): void {
    $this->user->setRememberToken('first');
    $this->user->setRememberToken('second');

    expect($this->user->getRememberToken())->toBe('second')
        ->and(AppToken::query()->ofType(ESystemTokenType::Remember)->count())->toBe(1);
});

it('drops the remember me token when it is emptied', function (): void {
    $this->user->setRememberToken('a-remembered-token');
    $this->user->setRememberToken(null);

    expect($this->user->getRememberToken())->toBeNull()
        ->and(AppToken::query()->ofType(ESystemTokenType::Remember)->count())->toBe(0);
});

it('remembers a login through the tokens table', function (): void {
    vault()->lock();
    Auth::logout();

    $this->users->login('user@example.test', 'correct horse', remember: true);

    $token = AppToken::query()->ofType(ESystemTokenType::Remember)->first();

    expect($token)->not->toBeNull()
        ->and($token->tokenable_id)->toBe($this->user->id)
        ->and(Auth::user()->getRememberToken())->toBe($token->value);
});

it('changes a password and rewrites the vault key pair', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');
    $this->user->setRememberToken('a-remembered-token');

    $this->users->changePassword($this->user, 'battery staple');

    expect(Hash::check('battery staple', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->vaultValue(EVaultDetailStub::Pin))->toBe('1234')
        // A browser left logged in elsewhere has to sign in again.
        ->and($this->user->getRememberToken())->toBeNull();

    vault()->lock();
    Auth::logout();

    expect($this->users->login('user@example.test', 'correct horse'))->toBeNull()
        ->and($this->users->login('user@example.test', 'battery staple'))->not->toBeNull()
        ->and($this->user->vaultValue(EVaultDetailStub::Pin))->toBe('1234');
});

it('hands out a reset token without storing it', function (): void {
    $token = $this->users->startPasswordReset($this->user);

    $stored = AppToken::query()->ofType(ESystemTokenType::PasswordReset)->first();

    expect($token)->toHaveLength(64)
        ->and($stored)->not->toBeNull()
        ->and($stored->value)->not->toBe($token)
        ->and($stored->value)->toBe(hash('sha256', $token))
        ->and($stored->expires_at)->not->toBeNull()
        ->and(json_encode(DB::table(AppToken::TABLE)->get()))->not->toContain($token);
});

it('takes the expiry from the auth config', function (): void {
    config()->set('auth.passwords.users.expire', 15);

    $this->users->startPasswordReset($this->user);

    $expires = AppToken::query()->ofType(ESystemTokenType::PasswordReset)->first()->expires_at;

    expect($expires->diffInMinutes(Carbon::now()->addMinutes(15)))->toBeLessThan(1)
        ->and($this->users->startPasswordReset($this->user, 5))->toHaveLength(64)
        ->and(AppToken::query()->ofType(ESystemTokenType::PasswordReset)->count())->toBe(1);
});

it('resets a password with the token', function (): void {
    $token = $this->users->startPasswordReset($this->user);
    vault()->lock();

    $user = $this->users->resetPassword($token, 'battery staple');

    expect($user)->not->toBeNull()
        ->and($user->is($this->user))->toBeTrue()
        ->and(Hash::check('battery staple', $user->fresh()->password))->toBeTrue()
        // The token is spent.
        ->and(AppToken::query()->ofType(ESystemTokenType::PasswordReset)->count())->toBe(0)
        ->and($this->users->resetPassword($token, 'another one'))->toBeNull();
});

it('turns down an unknown or expired token', function (): void {
    $token = $this->users->startPasswordReset($this->user, 5);

    AppToken::query()->ofType(ESystemTokenType::PasswordReset)->first()
        ->forceFill(['expires_at' => Carbon::now()->subMinute()])->save();

    expect($this->users->resetPassword($token, 'battery staple'))->toBeNull()
        ->and($this->users->resetPassword('never issued', 'battery staple'))->toBeNull()
        ->and(Hash::check('correct horse', $this->user->fresh()->password))->toBeTrue();
});

it('leaves the vault closed on a reset nobody can recover', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');
    $token = $this->users->startPasswordReset($this->user);
    vault()->lock();

    $this->users->resetPassword($token, 'battery staple');

    // The account works again, the entries of the old key pair do not.
    $user = $this->users->login('user@example.test', 'battery staple');

    expect($user)->not->toBeNull()
        ->and(fn () => $user->vaultValue(EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('hands the vault over on a reset run as the system user', function (): void {
    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    // Stored while the system key is registered, so it is sealed to it too.
    $this->users->changeEmail($this->user, 'user@example.test', 'correct horse');
    $this->user->putVault(EVaultDetailStub::Pin, '1234');

    $token = $this->users->startPasswordReset($this->user);
    vault()->lock();
    vault()->unlockAsSystem();

    $this->users->resetPassword($token, 'battery staple');

    vault()->lock();
    $user = $this->users->login('user@example.test', 'battery staple');

    expect($user->vaultValue(EVaultDetailStub::Pin))->toBe('1234');
});
