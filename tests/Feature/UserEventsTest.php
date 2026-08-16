<?php

use Fnp\ElStart\Events\UserDeleted;
use Fnp\ElStart\Events\UserEmailChanged;
use Fnp\ElStart\Events\UserEmailVerified;
use Fnp\ElStart\Events\UserLoggedIn;
use Fnp\ElStart\Events\UserLoginFailed;
use Fnp\ElStart\Events\UserPasswordChanged;
use Fnp\ElStart\Events\UserPasswordReset;
use Fnp\ElStart\Events\UserPasswordResetRequested;
use Fnp\ElStart\Events\UserRegistered;
use Fnp\ElStart\Models\AppAudit;
use Fnp\ElStart\Services\UserService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->users = app(UserService::class);
});

it('announces a registration, next to the Laravel one', function (): void {
    Event::fake([UserRegistered::class, Registered::class]);

    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->is($user));
    Event::assertDispatched(Registered::class);
});

it('announces a login', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    Event::fake([UserLoggedIn::class]);

    $user = $this->users->login('user@example.test', 'correct horse', remember: true);

    Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event): bool => $event->user->is($user)
        && $event->remember === true);
});

it('announces a login that failed, by the address that was tried', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    Auth::logout();

    Event::fake([UserLoginFailed::class, UserLoggedIn::class]);

    $this->users->login('user@example.test', 'wrong horse');
    $this->users->login('nobody@example.test', 'correct horse');

    Event::assertDispatchedTimes(UserLoginFailed::class, 2);
    Event::assertNotDispatched(UserLoggedIn::class);

    Event::assertDispatched(
        UserLoginFailed::class,
        fn (UserLoginFailed $event): bool => $event->email === 'user@example.test',
    );
});

it('announces a deletion', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserDeleted::class]);

    $this->users->delete($user);

    Event::assertDispatched(UserDeleted::class, fn (UserDeleted $event): bool => $event->user->is($user));
});

it('announces a change of address with both of them', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserEmailChanged::class]);

    $this->users->changeEmail($user, 'renamed@example.test');

    Event::assertDispatched(UserEmailChanged::class, fn (UserEmailChanged $event): bool => $event->user->is($user)
        && $event->from === 'user@example.test'
        && $event->to === 'renamed@example.test');
});

it('stays quiet when the address does not actually change', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserEmailChanged::class]);

    $this->users->changeEmail($user, 'USER@example.test');

    Event::assertNotDispatched(UserEmailChanged::class);
});

it('announces a password change', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserPasswordChanged::class]);

    $this->users->changePassword($user, 'battery staple');

    Event::assertDispatched(
        UserPasswordChanged::class,
        fn (UserPasswordChanged $event): bool => $event->user->is($user),
    );
});

it('announces a reset being asked for and carried out', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserPasswordResetRequested::class, UserPasswordReset::class]);

    $token = $this->users->startPasswordReset($user, 15);

    Event::assertDispatched(
        UserPasswordResetRequested::class,
        fn (UserPasswordResetRequested $event): bool => $event->user->is($user)
            && $event->expiresAt->getTimestamp() > time(),
    );

    $this->users->resetPassword($token, 'battery staple');

    Event::assertDispatched(
        UserPasswordReset::class,
        fn (UserPasswordReset $event): bool => $event->user->is($user),
    );
});

it('stays quiet when a reset token leads nowhere', function (): void {
    Event::fake([UserPasswordReset::class]);

    expect($this->users->resetPassword('never issued', 'battery staple'))->toBeNull();

    Event::assertNotDispatched(UserPasswordReset::class);
});

it('records the events in the audit table', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $this->users->changeEmail($user, 'renamed@example.test');
    $this->users->changePassword($user, 'battery staple');
    $this->users->delete($user);

    $events = AppAudit::query()->orderBy('id')->pluck('event')->all();

    expect($events)->toContain('user.registered')
        ->and($events)->toContain('user.email-changed')
        ->and($events)->toContain('user.password-changed')
        ->and($events)->toContain('user.deleted');
});

it('never writes a name, a password or a token into the audit trail', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'the password itself');
    $this->users->changeEmail($user, 'renamed@example.test');
    $token = $this->users->startPasswordReset($user);
    $this->users->changePassword($user, 'another password');
    $this->users->delete($user);

    Auth::logout();
    $this->users->login('renamed@example.test', 'wrong horse');

    $payloads = json_encode(AppAudit::pluck('payload')->all());

    expect($payloads)->not->toContain('Example Name')
        ->and($payloads)->not->toContain('the password itself')
        ->and($payloads)->not->toContain('another password')
        ->and($payloads)->not->toContain($token)
        // The addresses of a change are the point of recording it.
        ->and($payloads)->toContain('renamed@example.test');
});

it('audits a registration by the ids of the account alone', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $audit = AppAudit::query()->where('event', 'user.registered')->first();

    expect($audit->payload)->toBe([
        'id' => $user->id,
        'uuid' => $user->uuid,
    ]);
});

it('announces an address being verified, once', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserEmailVerified::class, Verified::class]);

    expect($user->hasVerifiedEmail())->toBeFalse()
        ->and($this->users->verifyEmail($user))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->fresh()->email_verified_at)->not->toBeNull()
        // Already verified, so nothing to announce the second time.
        ->and($this->users->verifyEmail($user))->toBeFalse();

    Event::assertDispatchedTimes(UserEmailVerified::class, 1);
    Event::assertDispatchedTimes(Verified::class, 1);

    Event::assertDispatched(
        UserEmailVerified::class,
        fn (UserEmailVerified $event): bool => $event->user->is($user),
    );
});

it('audits a verification by when it happened', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $this->users->verifyEmail($user);

    $audit = AppAudit::query()->where('event', 'user.email-verified')->first();

    expect($audit->payload['id'])->toBe($user->id)
        ->and($audit->payload['uuid'])->toBe($user->uuid)
        ->and($audit->payload['verified_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/');
});
