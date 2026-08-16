<?php

use Fnp\ElStart\Enums\ESystemVaultDetail;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Keep the key derivation cheap, the cost is not what these tests check.
    VaultService::useDerivationCost(1, 8192);

    $this->users = app(UserService::class);
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );
});

it('has the user tables', function (): void {
    expect(Schema::hasTable(AppUser::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppUser::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'uuid',
            'email_hash',
            'email_verified_at',
            'password',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);
});

it('has no column for who the account belongs to', function (): void {
    expect(Schema::hasColumn(AppUser::TABLE, 'email'))->toBeFalse()
        ->and(Schema::hasColumn(AppUser::TABLE, 'name'))->toBeFalse()
        ->and(Schema::hasColumn(AppUser::TABLE, 'remember_token'))->toBeFalse();
});

it('supersedes the user model of the application', function (): void {
    expect(config('auth.providers.users.model'))->toBe(AppUser::class);
});

it('gets a uuid of its own alongside the primary key', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $other = $this->users->register('Other', 'other@example.test', 'their password');

    expect($user->uuid)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($user->uuid)->not->toBe($other->uuid)
        // The key itself stays an auto incrementing integer.
        ->and($user->getKeyName())->toBe('id')
        ->and($user->getIncrementing())->toBeTrue()
        ->and($user->id)->toBeInt()
        // And the uuid is what a URL carries.
        ->and($user->getRouteKeyName())->toBe('uuid')
        ->and($user->getRouteKey())->toBe($user->uuid);
});

it('keeps the uuid it was given', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $uuid = $user->uuid;

    $user->touch();

    expect($user->fresh()->uuid)->toBe($uuid);
});

it('hashes an address the same way every time', function (): void {
    $hash = AppUser::hashEmail('user@example.test');

    expect($hash)->toHaveLength(64)
        ->and($hash)->toBe(AppUser::hashEmail('user@example.test'))
        // Case and padding are normalised, so one address is one hash.
        ->and($hash)->toBe(AppUser::hashEmail('  User@Example.Test '))
        ->and($hash)->not->toBe(AppUser::hashEmail('other@example.test'))
        ->and($hash)->not->toContain('user@example.test');
});

it('keys the hash with the application key', function (): void {
    $hash = AppUser::hashEmail('user@example.test');

    config()->set('app.key', 'base64:' . base64_encode(str_repeat('rotated!', 4)));

    expect(AppUser::hashEmail('user@example.test'))->not->toBe($hash)
        // A plain digest of the address would give this away.
        ->and($hash)->not->toBe(hash('sha256', 'user@example.test'));
});

it('never writes the name or the address into the user table', function (): void {
    $this->users->register('Example Name', 'user@example.test', 'correct horse');

    $row = json_encode(DB::table(AppUser::TABLE)->first());

    expect($row)->not->toContain('user@example.test')
        ->and($row)->not->toContain('Example Name')
        ->and($row)->toContain(AppUser::hashEmail('user@example.test'));
});

it('keeps the name and the address in the vault', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'correct horse');

    // Read as ordinary attributes, so anything expecting a Laravel user works.
    expect($user->email)->toBe('user@example.test')
        ->and($user->name)->toBe('Example Name')
        ->and($user->hasVault(ESystemVaultDetail::Email))->toBeTrue()
        ->and($user->hasVault(ESystemVaultDetail::Name))->toBeTrue()
        ->and(ESystemVaultDetail::Email->value)->toBeGreaterThanOrEqual(100000)
        ->and(ESystemVaultDetail::Name->value)->toBeGreaterThanOrEqual(100000);
});

it('reads nothing rather than throwing while the vault is locked', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'correct horse');

    vault()->lock();

    expect($user->email)->toBeNull()
        ->and($user->name)->toBeNull()
        // Notifications route nowhere rather than throwing mid-send.
        ->and($user->routeNotificationFor('mail'))->toBeNull()
        // The difference between locked and not set is still there to be told.
        ->and(fn () => $user->vaultValue(ESystemVaultDetail::Email))->toThrow(VaultException::class);
});

it('routes mail to the address in the vault', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->routeNotificationFor('mail'))->toBe('user@example.test');
});

it('keeps the name and the address out of array output', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'correct horse');

    expect($user->toArray())->not->toHaveKey('name')
        ->and($user->toArray())->not->toHaveKey('email')
        ->and(json_encode($user->toArray()))->not->toContain('user@example.test');
});

it('keys password resets by the hash', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->getEmailForPasswordReset())->toBe(AppUser::hashEmail('user@example.test'))
        ->and($user->getEmailForPasswordReset())->not->toContain('@');
});

it('hides the hash and the password from array output', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->toArray())->not->toHaveKey('password')
        ->and($user->toArray())->not->toHaveKey('remember_token')
        ->and($user->toArray())->not->toHaveKey('email_hash')
        ->and(Hash::check('correct horse', $user->password))->toBeTrue();
});

it('authenticates through the configured provider', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();
    Auth::logout();

    $hash = AppUser::hashEmail('user@example.test');

    expect(Auth::attempt(['email_hash' => $hash, 'password' => 'wrong horse']))->toBeFalse()
        ->and(Auth::attempt(['email_hash' => $hash, 'password' => 'correct horse']))->toBeTrue()
        ->and(Auth::user())->toBeInstanceOf(AppUser::class);
});

it('carries the tokens and the vault of this module', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $user->addToken(ETokenTypeStub::Api, 'secret');
    $user->putVault(EVaultDetailStub::Pin, '1234');

    expect($user->token(ETokenTypeStub::Api)->value)->toBe('secret')
        ->and($user->vaultValue(EVaultDetailStub::Pin))->toBe('1234')
        // The detail of this module and the details of an application sit
        // side by side without colliding.
        ->and($user->vaultDetails()->all())
        ->toBe([
            EVaultDetailStub::Pin->value,
            ESystemVaultDetail::Email->value,
            ESystemVaultDetail::Name->value,
        ]);
});
