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
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\UserService;
use Fnp\ElStart\Services\VaultService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

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

    VaultService::useSystemKeys(null, null);
});

it('announces a registration, next to the Laravel one', function (): void {
    Event::fake([UserRegistered::class, Registered::class]);

    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event): bool => $event->user->is($user));
    Event::assertDispatched(Registered::class);
});

it('announces a login and whether the vault came with it', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();
    Auth::logout();

    Event::fake([UserLoggedIn::class]);

    $user = $this->users->login('user@example.test', 'correct horse', remember: true);

    Event::assertDispatched(UserLoggedIn::class, fn (UserLoggedIn $event): bool => $event->user->is($user)
        && $event->remember === true
        && $event->vaultUnlocked === true);
});

it('announces a login that failed, by the hash that was tried', function (): void {
    $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();
    Auth::logout();

    Event::fake([UserLoginFailed::class, UserLoggedIn::class]);

    $this->users->login('user@example.test', 'wrong horse');
    $this->users->login('nobody@example.test', 'correct horse');

    Event::assertDispatchedTimes(UserLoginFailed::class, 2);
    Event::assertNotDispatched(UserLoggedIn::class);

    Event::assertDispatched(
        UserLoginFailed::class,
        fn (UserLoginFailed $event): bool => $event->emailHash === AppUser::hashEmail('user@example.test'),
    );
});

it('announces a deletion', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserDeleted::class]);

    $this->users->delete($user);

    Event::assertDispatched(UserDeleted::class, fn (UserDeleted $event): bool => $event->user->is($user));
});

it('announces a change of address with both hashes', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    Event::fake([UserEmailChanged::class]);

    $this->users->changeEmail($user, 'renamed@example.test', 'correct horse');

    Event::assertDispatched(UserEmailChanged::class, fn (UserEmailChanged $event): bool => $event->user->is($user)
        && $event->from === AppUser::hashEmail('user@example.test')
        && $event->to === AppUser::hashEmail('renamed@example.test'));
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
        fn (UserPasswordReset $event): bool => $event->user->is($user) && $event->recovered === false,
    );
});

it('says when a reset handed the vault over as well', function (): void {
    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $token = $this->users->startPasswordReset($user);

    vault()->lock();
    vault()->unlockAsSystem();

    Event::fake([UserPasswordReset::class]);

    $this->users->resetPassword($token, 'battery staple');

    Event::assertDispatched(
        UserPasswordReset::class,
        fn (UserPasswordReset $event): bool => $event->recovered === true,
    );
});

it('stays quiet when a reset token leads nowhere', function (): void {
    Event::fake([UserPasswordReset::class]);

    expect($this->users->resetPassword('never issued', 'battery staple'))->toBeNull();

    Event::assertNotDispatched(UserPasswordReset::class);
});

it('records the events in the audit table', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    $this->users->changeEmail($user, 'renamed@example.test', 'correct horse');
    $this->users->changePassword($user, 'battery staple');
    $this->users->delete($user);

    $events = AppAudit::query()->orderBy('id')->pluck('event')->all();

    expect($events)->toContain('user.registered')
        ->and($events)->toContain('user.email-changed')
        ->and($events)->toContain('user.password-changed')
        ->and($events)->toContain('user.deleted');
});

it('never writes a name, an address or a password into the audit trail', function (): void {
    $user = $this->users->register('Example Name', 'user@example.test', 'the password itself');
    $this->users->changeEmail($user, 'renamed@example.test', 'the password itself');
    $this->users->startPasswordReset($user);
    $this->users->changePassword($user, 'another password');
    $this->users->delete($user);

    vault()->lock();
    Auth::logout();
    $this->users->login('renamed@example.test', 'wrong horse');

    $payloads = json_encode(AppAudit::pluck('payload')->all());

    expect($payloads)->not->toContain('user@example.test')
        ->and($payloads)->not->toContain('renamed@example.test')
        ->and($payloads)->not->toContain('Example Name')
        ->and($payloads)->not->toContain('the password itself')
        ->and($payloads)->not->toContain('another password')
        // The hashes are what stands in for the addresses.
        ->and($payloads)->toContain(AppUser::hashEmail('renamed@example.test'));
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

it('verifies an address without opening the vault', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');
    vault()->lock();

    expect($this->users->verifyEmail($user))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        // Signed links are keyed by the hash, which needs no vault to read.
        ->and($user->getEmailForVerification())->toBe(AppUser::hashEmail('user@example.test'))
        ->and($user->getEmailForVerification())->not->toContain('@');
});

it('audits a verification without the address', function (): void {
    $user = $this->users->register('Example', 'user@example.test', 'correct horse');

    $this->users->verifyEmail($user);

    $audit = AppAudit::query()->where('event', 'user.email-verified')->first();

    expect($audit->payload['id'])->toBe($user->id)
        ->and($audit->payload['uuid'])->toBe($user->uuid)
        ->and($audit->payload['verified_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/')
        ->and(json_encode($audit->payload))->not->toContain('user@example.test');
});
