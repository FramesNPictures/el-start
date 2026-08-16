<?php

use Fnp\ElStart\Database\Factories\AppUserFactory;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\UserService;
use Illuminate\Support\Facades\Hash;

afterEach(function (): void {
    AppUserFactory::$password = 'password';
});

it('makes a user the way registering one does', function (): void {
    $user = AppUser::factory()->create();

    expect($user)->toBeInstanceOf(AppUser::class)
        ->and($user->exists)->toBeTrue()
        ->and($user->uuid)->not->toBeNull()
        ->and($user->name)->not->toBeNull()
        ->and($user->email)->not->toBeNull()
        ->and(Hash::check(AppUserFactory::$password, $user->password))->toBeTrue();
});

it('logs in with the password of the factory', function (): void {
    $user = AppUser::factory()->create();

    $logged = app(UserService::class)->login($user->email, AppUserFactory::$password);

    expect($logged)->not->toBeNull()
        ->and($logged->is($user))->toBeTrue();
});

it('takes a password for the whole suite', function (): void {
    AppUserFactory::$password = 'correct horse';

    $user = AppUser::factory()->create();

    expect(Hash::check('correct horse', $user->password))->toBeTrue();
});

it('takes an address and a name of your choosing', function (): void {
    $user = AppUser::factory()
        ->withEmail('user@example.test')
        ->withName('Example Name')
        ->create();

    expect($user->email)->toBe('user@example.test')
        ->and($user->name)->toBe('Example Name');
});

it('keeps the made up half when only one is given', function (): void {
    $named = AppUser::factory()->withName('Example Name')->create();
    $addressed = AppUser::factory()->withEmail('user@example.test')->create();

    expect($named->name)->toBe('Example Name')
        ->and($named->email)->not->toBeNull()
        ->and($addressed->email)->toBe('user@example.test')
        ->and($addressed->name)->not->toBeNull();
});

it('makes many users, each with an address of their own', function (): void {
    $users = AppUser::factory()->count(3)->create();

    expect($users)->toHaveCount(3)
        ->and(AppUser::count())->toBe(3)
        ->and($users->pluck('email')->unique())->toHaveCount(3);
});

it('makes a user with no addresses behind them', function (): void {
    $user = AppUser::factory()->create();

    expect($user->previousEmails)->toBeEmpty();
});

it('takes a verified or unverified address', function (): void {
    expect(AppUser::factory()->create()->email_verified_at)->toBeNull()
        ->and(AppUser::factory()->unverified()->create()->email_verified_at)->toBeNull()
        ->and(AppUser::factory()->verified()->create()->email_verified_at)->not->toBeNull();
});
