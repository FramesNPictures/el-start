<?php

use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Models\AppUserEmail;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->users = app(UserService::class);
});

it('has the user tables', function (): void {
    expect(Schema::hasTable(AppUser::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppUser::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'uuid',
            'name',
            'email',
            'email_verified_at',
            'password',
            'created_at',
            'updated_at',
            'deleted_at',
        ])
        ->and(Schema::hasTable(AppUserEmail::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppUserEmail::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'user_id',
            'email',
            'until',
            'created_at',
            'updated_at',
        ]);
});

it('has no column behind the remember me token', function (): void {
    expect(Schema::hasColumn(AppUser::TABLE, 'remember_token'))->toBeFalse();
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

it('keeps the name and the address on the row', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'correct horse');

    expect($user->name)->toBe('Example Name')
        ->and($user->email)->toBe('user@example.test')
        ->and($user->fresh()->email)->toBe('user@example.test');
});

it('routes mail to the address of the user', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->routeNotificationFor('mail'))->toBe('user@example.test');
});

it('keys password resets and verification links by the address', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->getEmailForPasswordReset())->toBe('user@example.test')
        ->and($user->getEmailForVerification())->toBe('user@example.test');
});

it('hides the password from array output', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    expect($user->toArray())->not->toHaveKey('password')
        ->and($user->toArray())->not->toHaveKey('remember_token')
        ->and($user->toArray())->toHaveKey('email')
        ->and(Hash::check('correct horse', $user->password))->toBeTrue();
});

it('authenticates through the configured provider', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    expect(Auth::attempt(['email' => 'user@example.test', 'password' => 'wrong horse']))->toBeFalse()
        ->and(Auth::attempt(['email' => 'user@example.test', 'password' => 'correct horse']))->toBeTrue()
        ->and(Auth::user())->toBeInstanceOf(AppUser::class);
});

it('carries the tokens of this module', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $user->addToken(ETokenTypeStub::Api, 'secret');

    expect($user->token(ETokenTypeStub::Api)->value)->toBe('secret');
});

it('keeps the addresses it had before, oldest first', function (): void {
    $user = $this->users->register('Example', 'first@example.test', 'correct horse');

    $this->users->changeEmail($user, 'second@example.test');
    $this->users->changeEmail($user, 'third@example.test');

    expect($user->previousEmails->pluck('email')->all())
        ->toBe(['first@example.test', 'second@example.test'])
        ->and($user->previousEmails->first()->until)->not->toBeNull()
        ->and($user->previousEmails->first()->user->is($user))->toBeTrue();
});
