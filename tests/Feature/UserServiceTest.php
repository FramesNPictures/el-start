<?php

use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Models\AppUserEmail;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Fnp\ElStart\Tests\Stubs\UserSubclassStub;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

beforeEach(function (): void {
    $this->users = app(UserService::class);
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
        ->and($user->email)->toBe('user@example.test')
        ->and(Hash::check('correct horse', $user->password))->toBeTrue()
        ->and(AppUser::count())->toBe(1);
});

it('normalises the address it stores', function (): void {
    $user = $this->users->register('Example', '  User@Example.Test ', 'correct horse');

    expect($user->email)->toBe('user@example.test')
        ->and(DB::table(AppUser::TABLE)->value('email'))->toBe('user@example.test');
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

it('logs a user in', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    $logged = $this->users->login('user@example.test', 'correct horse');

    expect($logged)->toBeInstanceOf(AppUser::class)
        ->and($logged->is($user))->toBeTrue()
        ->and(Auth::check())->toBeTrue();
});

it('logs a user in however their address was typed', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($this->users->login('  User@Example.Test ', 'correct horse'))->not->toBeNull()
        ->and(Auth::check())->toBeTrue();
});

it('turns a wrong password down', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    expect($this->users->login('user@example.test', 'wrong horse'))->toBeNull()
        ->and($this->users->login('nobody@example.test', 'correct horse'))->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

it('gives the session a fresh id on login', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    $before = Session::getId();
    $this->users->login('user@example.test', 'correct horse');

    expect(Session::getId())->not->toBe($before);
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

    expect($this->users->login('user@example.test', 'correct horse'))->toBeNull()
        ->and(Auth::check())->toBeFalse();
});

it('ends the session of a user deleting themselves', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    $user = $this->users->login('user@example.test', 'correct horse');

    $this->users->delete($user);

    expect(Auth::check())->toBeFalse();
});

it('leaves the session alone when deleting somebody else', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    $other = $this->users->register('Other', 'other@example.test', 'their password');

    $this->users->login('user@example.test', 'correct horse');

    $this->users->delete($other);

    expect(Auth::check())->toBeTrue()
        ->and(Auth::user()->email)->toBe('user@example.test');
});

it('changes the address a user is known by', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $this->users->changeEmail($user, 'Renamed@Example.Test');

    expect($user->fresh()->email)->toBe('renamed@example.test');

    // And the new address is the one that logs in from now on.
    Auth::logout();

    expect($this->users->login('user@example.test', 'correct horse'))->toBeNull()
        ->and($this->users->login('renamed@example.test', 'correct horse'))->not->toBeNull();
});

it('keeps the addresses a user had before', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');

    expect($user->previousEmails)->toBeEmpty();

    $this->users->changeEmail($user, 'second@example.test');
    $this->users->changeEmail($user, 'third@example.test');

    expect($user->email)->toBe('third@example.test')
        ->and($user->previousEmails)->toHaveCount(2)
        // Oldest first, each with the moment it stopped being theirs.
        ->and($user->previousEmails->pluck('email')->all())
        ->toBe(['first@example.test', 'second@example.test'])
        ->and($user->previousEmails->first()->until)->not->toBeNull();
});

it('writes no history when the address does not actually change', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $this->users->changeEmail($user, 'USER@example.test');

    expect($user->previousEmails)->toBeEmpty()
        ->and(AppUserEmail::count())->toBe(0)
        ->and($user->fresh()->email)->toBe('user@example.test');
});

it('lets an address be taken up again once it is free', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');
    $this->users->changeEmail($user, 'second@example.test');

    // Nothing logs in through the old row, so the address is free.
    $other = $this->users->register('Other', 'first@example.test', 'their password');

    expect($other->email)->toBe('first@example.test')
        ->and($this->users->findByEmail('first@example.test')->is($other))->toBeTrue();
});

it('keeps the tokens and the addresses of a deleted user', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');
    $this->users->changeEmail($user, 'second@example.test');
    $user->addToken(ETokenTypeStub::Api, 'secret');

    $this->users->delete($user);

    expect($user->tokens()->count())->toBe(1)
        ->and($user->previousEmails()->count())->toBe(1)
        ->and($user->email)->toBe('second@example.test')
        ->and($user->name)->toBe('Example');
});
