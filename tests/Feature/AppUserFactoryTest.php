<?php

use Fnp\ElStart\Database\Factories\AppUserFactory;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Services\VaultService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    // Keep the key derivation cheap, the cost is not what these tests check.
    VaultService::useDerivationCost(1, 8192);
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    AppUserFactory::$password = 'password';
});

it('makes a user the way registering one does', function (): void {
    $user = AppUser::factory()->create();

    expect($user)->toBeInstanceOf(AppUser::class)
        ->and($user->exists)->toBeTrue()
        ->and($user->uuid)->not->toBeNull()
        ->and($user->email_hash)->toHaveLength(64)
        ->and(Hash::check(AppUserFactory::$password, $user->password))->toBeTrue()
        // The two that only the vault holds.
        ->and($user->email)->not->toBeNull()
        ->and($user->name)->not->toBeNull()
        ->and($user->email_hash)->toBe(AppUser::hashEmail($user->email));
});

it('writes the columns the model guards against a request', function (): void {
    $user = AppUser::factory()->create();

    // `email_hash` is not fillable, so a factory that merely filled would
    // have dropped it and the insert would never have got this far.
    expect(DB::table(AppUser::TABLE)->value('email_hash'))->toBe($user->email_hash);
});

it('keeps the name and the address out of the table', function (): void {
    $user = AppUser::factory()->create();
    $email = $user->email;
    $name = $user->name;

    $row = json_encode(DB::table(AppUser::TABLE)->first());

    expect($row)->not->toContain($email)
        ->and($row)->not->toContain($name);
});

it('leaves the vault unlocked as the user it made', function (): void {
    $user = AppUser::factory()->create();

    expect(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->userId())->toBe($user->id);

    vault()->lock();

    expect($user->email)->toBeNull();
});

it('logs in with the password of the factory', function (): void {
    AppUser::factory()->create();
    $email = AppUser::first()->email;

    vault()->lock();

    $user = app(UserService::class)->login($email, AppUserFactory::$password);

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe($email);
});

it('takes a password for the whole suite', function (): void {
    AppUserFactory::$password = 'correct horse';

    $user = AppUser::factory()->create();

    expect(Hash::check('correct horse', $user->password))->toBeTrue()
        ->and($user->email)->not->toBeNull();
});

it('takes an address and a name of your choosing', function (): void {
    $user = AppUser::factory()
        ->withEmail('user@example.test')
        ->withName('Example Name')
        ->create();

    expect($user->email)->toBe('user@example.test')
        ->and($user->name)->toBe('Example Name')
        ->and($user->email_hash)->toBe(AppUser::hashEmail('user@example.test'));
});

it('keeps the made up half when only one is given', function (): void {
    // Each is read while the vault is still open as them: making the next
    // user opens it as that one instead.
    $named = AppUser::factory()->withName('Example Name')->create();

    expect($named->name)->toBe('Example Name')
        ->and($named->email)->not->toBeNull();

    $addressed = AppUser::factory()->withEmail('user@example.test')->create();

    expect($addressed->email)->toBe('user@example.test')
        ->and($addressed->name)->not->toBeNull()
        // And the one made before is closed to them.
        ->and($named->email)->toBeNull();
});

it('makes many users, each with an address of their own', function (): void {
    $users = AppUser::factory()->count(3)->create();

    expect($users)->toHaveCount(3)
        ->and(AppUser::count())->toBe(3)
        ->and($users->pluck('email_hash')->unique())->toHaveCount(3);
});

it('takes a verified or unverified address', function (): void {
    expect(AppUser::factory()->create()->email_verified_at)->toBeNull()
        ->and(AppUser::factory()->unverified()->create()->email_verified_at)->toBeNull()
        ->and(AppUser::factory()->verified()->create()->email_verified_at)->not->toBeNull();
});
