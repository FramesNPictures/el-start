<?php

use Fnp\ElStart\Enums\ESystemVaultDetail;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Models\AppVaultKey;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Fnp\ElStart\Tests\Stubs\UserSubclassStub;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

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

it('resolves out of the container as a single instance', function (): void {
    expect(app(UserService::class))->toBeInstanceOf(UserService::class)
        ->and(app(UserService::class))->toBe(app(UserService::class));
});

it('registers a user', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user)->toBeInstanceOf(AppUser::class)
        ->and($user->exists)->toBeTrue()
        ->and($user->name)->toBe('Example')
        ->and($user->email_hash)->toBe(AppUser::hashEmail('user@example.test'))
        ->and($user->email)->toBe('user@example.test')
        ->and(Hash::check('correct horse', $user->password))->toBeTrue()
        ->and(AppUser::count())->toBe(1);
});

it('refuses a second user with the same address', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');

    expect(fn () => $this->users->register('Other', 'USER@example.test', 'their password'))
        ->toThrow(QueryException::class);
});

it('finds a user by their address', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($this->users->findByEmail('user@example.test')->is($user))->toBeTrue()
        ->and($this->users->findByEmail('  User@Example.Test ')->is($user))->toBeTrue()
        ->and($this->users->findByEmail('nobody@example.test'))->toBeNull();

    $this->users->delete($user);

    expect($this->users->findByEmail('user@example.test'))->toBeNull()
        ->and($this->users->findByEmail('user@example.test', withTrashed: true)->is($user))->toBeTrue();
});

it('takes the further columns a model makes fillable', function (): void {
    config()->set('auth.providers.users.model', UserSubclassStub::class);

    $user = $this->users->register(
        'Example',
        'user@example.test',
        'correct horse',
        ['email_verified_at' => '2026-01-01 10:00:00'],
    );

    expect($user->fresh()->email_verified_at->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('drops the columns a model guards', function (): void {
    // `email_verified_at` is not fillable on the shipped model, so mass
    // assignment leaves it alone rather than letting a request set it.
    $user = $this->users->register(
        'Example',
        'user@example.test',
        'correct horse',
        ['email_verified_at' => '2026-01-01 10:00:00'],
    );

    expect($user->fresh()->email_verified_at)->toBeNull();
});

it('announces a registration', function (): void {
    Event::fake([Registered::class]);

    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
});

it('mints the vault key pair while the password is in hand', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect(AppVaultKey::query()->for($user)->exists())->toBeTrue()
        ->and(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->userId())->toBe($user->id);
});

it('leaves the registered user logged out', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');

    expect(Auth::check())->toBeFalse();
});

it('registers the model the config points at', function (): void {
    config()->set('auth.providers.users.model', UserSubclassStub::class);

    expect($this->users->model())->toBe(UserSubclassStub::class)
        ->and($this->users->register('Example', 'user@example.test', 'correct horse'))
        ->toBeInstanceOf(UserSubclassStub::class);
});

it('logs a user in and unlocks the vault', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $user->putVault(EVaultDetailStub::Pin, '1234');
    vault()->lock();
    Auth::logout();

    $logged = $this->users->login('user@example.test', 'correct horse');

    expect($logged)->toBeInstanceOf(AppUser::class)
        ->and($logged->is($user))->toBeTrue()
        ->and(Auth::check())->toBeTrue()
        ->and(vault()->isUnlocked())->toBeTrue()
        ->and($logged->vaultValue(EVaultDetailStub::Pin))->toBe('1234');
});

it('turns a wrong password down', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();
    Auth::logout();

    expect($this->users->login('user@example.test', 'wrong horse'))->toBeNull()
        ->and($this->users->login('nobody@example.test', 'correct horse'))->toBeNull()
        ->and(Auth::check())->toBeFalse()
        ->and(vault()->isLocked())->toBeTrue();
});

it('gives the session a fresh id on login', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();
    Auth::logout();

    $before = Session::getId();
    $this->users->login('user@example.test', 'correct horse');

    expect(Session::getId())->not->toBe($before)
        // The vault key survives the regeneration.
        ->and(vault()->isUnlocked())->toBeTrue();
});

it('soft deletes a user', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($this->users->delete($user))->toBeTrue()
        ->and(AppUser::count())->toBe(0)
        ->and(AppUser::withTrashed()->count())->toBe(1)
        ->and(AppUser::withTrashed()->first()->deleted_at)->not->toBeNull();
});

it('keeps a deleted user out of the login', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $this->users->delete($user);
    vault()->lock();

    expect($this->users->login('user@example.test', 'correct horse'))->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

it('ends the session of a user deleting themselves', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    $user = $this->users->login('user@example.test', 'correct horse');

    $this->users->delete($user);

    expect(Auth::check())->toBeFalse()
        // Logging out locks the vault through the listener of this module.
        ->and(vault()->isLocked())->toBeTrue();
});

it('leaves the session alone when deleting somebody else', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    $other = $this->users->register('Other', 'other@example.test', 'their password');

    $this->users->login('user@example.test', 'correct horse');

    $this->users->delete($other);

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('user@example.test')
        ->and(vault()->isUnlocked())->toBeTrue();
});

it('changes the address a user is known by', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $user->putVault(EVaultDetailStub::Pin, '1234');

    $this->users->changeEmail($user, 'renamed@example.test', 'correct horse');

    expect($user->fresh()->email_hash)->toBe(AppUser::hashEmail('renamed@example.test'))
        ->and($user->email)->toBe('renamed@example.test')
        // The entries stored under the old address are still readable.
        ->and($user->vaultValue(EVaultDetailStub::Pin))->toBe('1234');

    // And the new address is the one that logs in from now on.
    vault()->lock();
    Auth::logout();

    expect($this->users->login('user@example.test', 'correct horse'))->toBeNull()
        ->and($this->users->login('renamed@example.test', 'correct horse'))->not->toBeNull()
        ->and(vault()->isUnlocked())->toBeTrue();
});

it('keeps the addresses a user had before', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');

    expect($user->previous_emails)->toBe([]);

    $this->users->changeEmail($user, 'second@example.test', 'correct horse');
    $this->users->changeEmail($user, 'third@example.test', 'correct horse');

    expect($user->email)->toBe('third@example.test')
        ->and($user->previous_emails)->toHaveCount(2)
        // Oldest first, each with the moment it stopped being theirs.
        ->and(array_column($user->previous_emails, 'email'))
        ->toBe(['first@example.test', 'second@example.test'])
        ->and($user->previous_emails[0]['until'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});

it('keeps the addresses it had in the vault, not in the table', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');
    $this->users->changeEmail($user, 'second@example.test', 'correct horse');

    $rows = json_encode(DB::table(AppUser::TABLE)->get());

    expect($rows)->not->toContain('first@example.test')
        ->and($user->hasVault(ESystemVaultDetail::EmailHistory))->toBeTrue();

    vault()->lock();

    // Closed with the rest of the vault, and quiet about it.
    expect($user->previous_emails)->toBe([]);
});

it('writes no history when the address does not actually change', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $this->users->changeEmail($user, 'user@example.test', 'correct horse');

    expect($user->previous_emails)->toBe([])
        ->and($user->hasVault(ESystemVaultDetail::EmailHistory))->toBeFalse();
});

it('needs the vault open to change the address', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();

    expect(fn () => $this->users->changeEmail($user, 'renamed@example.test', 'correct horse'))
        ->toThrow(VaultException::class)
        ->and($user->fresh()->email_hash)->toBe(AppUser::hashEmail('user@example.test'));
});

it('keeps the tokens and the vault of a deleted user', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $user->putVault(EVaultDetailStub::Pin, '1234');

    $this->users->delete($user);

    expect(AppVaultKey::query()->for($user)->exists())->toBeTrue()
        // The name and address of the user, and the entry stored above.
        ->and($user->vault()->count())->toBe(3)
        ->and($user->email)->toBe('user@example.test')
        ->and($user->name)->toBe('Example');
});
