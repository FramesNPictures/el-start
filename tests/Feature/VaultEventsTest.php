<?php

use Fnp\ElStart\Events\VaultClosed;
use Fnp\ElStart\Events\VaultOpened;
use Fnp\ElStart\Events\VaultRekeyed;
use Fnp\ElStart\Events\VaultRemoved;
use Fnp\ElStart\Events\VaultRevoked;
use Fnp\ElStart\Events\VaultShared;
use Fnp\ElStart\Events\VaultUpdated;
use Fnp\ElStart\Models\AppAudit;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Fnp\ElStart\Tests\Stubs\UserStub;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // Keep the key derivation cheap, the cost is not what these tests check.
    VaultService::useDerivationCost(1, 8192);

    Schema::create(UserStub::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('email')->nullable();
    });

    Schema::create(TokenableStub::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });

    $this->user = UserStub::create(['email' => 'user@example.test']);
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    VaultService::useSystemKeys(null, null);

    Schema::dropIfExists(TokenableStub::TABLE);
    Schema::dropIfExists(UserStub::TABLE);
});

it('announces an unlock, minted or not', function (): void {
    Event::fake([VaultOpened::class]);

    vault()->unlock($this->user, 'correct horse');
    vault()->lock();
    vault()->unlock($this->user, 'correct horse');

    Event::assertDispatched(VaultOpened::class, fn (VaultOpened $event): bool => $event->identity === UserStub::class
            && $event->identityId === $this->user->id
            && $event->minted === true
            && $event->isSystem() === false);

    Event::assertDispatched(fn (VaultOpened $event): bool => $event->minted === false);
    Event::assertDispatchedTimes(VaultOpened::class, 2);
});

it('announces a system unlock', function (): void {
    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    Event::fake([VaultOpened::class]);

    vault()->unlockAsSystem();

    Event::assertDispatched(VaultOpened::class, fn (VaultOpened $event): bool => $event->isSystem()
            && $event->identity === AppVaultGrant::SYSTEM_TYPE
            && $event->minted === false);
});

it('announces a lock', function (): void {
    vault()->unlock($this->user, 'correct horse');

    Event::fake([VaultClosed::class]);

    vault()->lock();

    Event::assertDispatched(VaultClosed::class, fn (VaultClosed $event): bool => $event->identity === UserStub::class
            && $event->identityId === $this->user->id);
});

it('announces the lock a logout causes', function (): void {
    vault()->unlock($this->user, 'correct horse');

    Event::fake([VaultClosed::class]);

    Event::dispatch(new Logout('web', $this->user));

    Event::assertDispatched(VaultClosed::class);
});

it('stays quiet when there was nothing to lock', function (): void {
    Event::fake([VaultClosed::class]);

    vault()->lock();
    vault()->lock();

    Event::assertNotDispatched(VaultClosed::class);
});

it('announces a write and whether it was the first', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');

    Event::fake([VaultUpdated::class]);

    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');
    vault()->put($note, EVaultDetailStub::Note, 'meet at one');

    Event::assertDispatchedTimes(VaultUpdated::class, 2);

    Event::assertDispatched(VaultUpdated::class, fn (VaultUpdated $event): bool => $event->created
            && $event->entry->vaultable_id === $note->id
            && $event->entry->detailValue() === EVaultDetailStub::Note->value);

    Event::assertDispatched(fn (VaultUpdated $event): bool => $event->created === false);
});

it('announces a removal, of one detail or of all', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($this->user, EVaultDetailStub::Recovery, 'codes');

    Event::fake([VaultRemoved::class]);

    vault()->remove($this->user, EVaultDetailStub::Pin);
    vault()->remove($this->user);

    Event::assertDispatchedTimes(VaultRemoved::class, 2);

    Event::assertDispatched(VaultRemoved::class, fn (VaultRemoved $event): bool => $event->detail === EVaultDetailStub::Pin
            && $event->entries === 1
            && $event->model->is($this->user));

    Event::assertDispatched(fn (VaultRemoved $event): bool => $event->detail === null && $event->entries === 1);
});

it('stays quiet when there was nothing to remove', function (): void {
    vault()->unlock($this->user, 'correct horse');

    Event::fake([VaultRemoved::class]);

    expect(vault()->remove($this->user))->toBe(0);

    Event::assertNotDispatched(VaultRemoved::class);
});

it('announces a share and a revoke', function (): void {
    $reader = UserStub::create(['email' => 'reader@example.test']);
    vault()->unlock($reader, 'their password');
    vault()->lock();

    vault()->unlock($this->user, 'correct horse');
    $entry = vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    Event::fake([VaultShared::class, VaultRevoked::class]);

    vault()->share($this->user, EVaultDetailStub::Pin, $reader);
    vault()->revoke($this->user, EVaultDetailStub::Pin, $reader);

    Event::assertDispatched(VaultShared::class, fn (VaultShared $event): bool => $event->reader->is($reader)
            && $event->entry->is($entry)
            && $event->entry->detailValue() === EVaultDetailStub::Pin->value);

    Event::assertDispatched(VaultRevoked::class, fn (VaultRevoked $event): bool => $event->reader->is($reader)
            && $event->entry->is($entry));
});

it('stays quiet when the share or the revoke changed nothing', function (): void {
    $reader = UserStub::create(['email' => 'reader@example.test']);
    vault()->unlock($reader, 'their password');
    vault()->lock();

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->share($this->user, EVaultDetailStub::Pin, $reader);

    Event::fake([VaultShared::class, VaultRevoked::class]);

    // Already shared, and already gone.
    vault()->share($this->user, EVaultDetailStub::Pin, $reader);
    vault()->revoke($this->user, EVaultDetailStub::Pin, $reader);
    vault()->revoke($this->user, EVaultDetailStub::Pin, $reader);

    Event::assertNotDispatched(VaultShared::class);
    Event::assertDispatchedTimes(VaultRevoked::class, 1);
});

it('audits a share with who was let in but not what they may read', function (): void {
    $reader = UserStub::create(['email' => 'reader@example.test']);
    vault()->unlock($reader, 'their password');
    vault()->lock();

    vault()->unlock($this->user, 'correct horse');
    $entry = vault()->put($this->user, EVaultDetailStub::Pin, 'the value itself');
    vault()->share($this->user, EVaultDetailStub::Pin, $reader);

    $audit = AppAudit::query()->where('event', VaultShared::class)->first();

    expect($audit->payload)->toBe([
        'vaultable' => UserStub::class,
        'vaultable_id' => $this->user->id,
        'detail' => EVaultDetailStub::Pin->value,
        'entry_id' => $entry->id,
        'reader' => UserStub::class,
        'reader_id' => $reader->id,
    ])->and(json_encode($audit->payload))->not->toContain('the value itself');
});

it('announces a rekey', function (): void {
    vault()->unlock($this->user, 'correct horse');

    Event::fake([VaultRekeyed::class]);

    vault()->rekey($this->user, 'battery staple');

    Event::assertDispatched(VaultRekeyed::class, fn (VaultRekeyed $event): bool => $event->user->is($this->user)
            && $event->recovered === false
            && $event->entries === 0);
});

it('announces a recovery with the number of entries handed over', function (): void {
    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();
    vault()->unlockAsSystem();

    Event::fake([VaultRekeyed::class]);

    vault()->recover($this->user, 'battery staple');

    Event::assertDispatched(VaultRekeyed::class, fn (VaultRekeyed $event): bool => $event->user->is($this->user)
            && $event->recovered === true
            && $event->entries === 1);
});

it('records the events in the audit table', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->rekey($this->user, 'battery staple');
    vault()->remove($this->user, EVaultDetailStub::Pin);
    vault()->lock();

    expect(AppAudit::query()->orderBy('id')->pluck('event')->all())->toBe([
        VaultOpened::class,
        VaultUpdated::class,
        VaultRekeyed::class,
        VaultRemoved::class,
        VaultClosed::class,
    ]);
});

it('records who was there, and nobody when there was nobody', function (): void {
    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    expect(AppAudit::query()->pluck('user_id')->unique()->all())->toBe([null]);

    // The same run behind a logged in user is filed against them, and the
    // payload still says which key pair did the reading.
    Auth::shouldReceive('check')->andReturnTrue();
    Auth::shouldReceive('id')->andReturn($this->user->id);

    vault()->unlockAsSystem();

    $entry = AppAudit::query()->where('event', VaultOpened::class)->orderByDesc('id')->first();

    expect($entry->user_id)->toBe($this->user->id)
        ->and($entry->payload['identity'])->toBe(AppVaultGrant::SYSTEM_TYPE);
});

it('never writes a secret into the audit trail', function (): void {
    vault()->unlock($this->user, 'the password itself');
    vault()->put($this->user, EVaultDetailStub::Pin, 'the value itself');
    vault()->lock();

    $payloads = json_encode(AppAudit::pluck('payload')->all());

    expect($payloads)->not->toContain('the value itself')
        ->and($payloads)->not->toContain('the password itself')
        ->and($payloads)->not->toContain(AppVault::first()->value)
        ->and($payloads)->not->toContain(AppVaultGrant::first()->sealed_key);
});

it('audits a write with what changed but not what it became', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    $entry = vault()->put($note, EVaultDetailStub::Note, 'meet at noon');

    $audit = AppAudit::query()->where('event', VaultUpdated::class)->first();

    expect($audit->payload)->toBe([
        'vaultable' => TokenableStub::class,
        'vaultable_id' => $note->id,
        'detail' => EVaultDetailStub::Note->value,
        'entry_id' => $entry->id,
        'created' => true,
    ]);
});
