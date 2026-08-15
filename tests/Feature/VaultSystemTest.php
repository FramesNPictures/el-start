<?php

use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Models\AppVaultKey;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Fnp\ElStart\Tests\Stubs\UserStub;
use Fnp\ElStart\Tests\Stubs\VaultSystemStubModule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;

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
    $this->keys = VaultService::generateSystemKeys();

    VaultService::useSystemKeys($this->keys['public'], $this->keys['secret']);
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    // The keys and the pepper are static and would otherwise leak between tests.
    VaultService::useSystemKeys(null, null);
    VaultService::usePepper(null);
    VaultSystemStubModule::$keys = ['public' => null, 'secret' => null, 'unlock' => false];

    Schema::dropIfExists(TokenableStub::TABLE);
    Schema::dropIfExists(UserStub::TABLE);
});

it('generates a key pair', function (): void {
    $keys = VaultService::generateSystemKeys();

    expect(strlen(base64_decode($keys['public'], true)))->toBe(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES)
        ->and(strlen(base64_decode($keys['secret'], true)))->toBe(SODIUM_CRYPTO_BOX_SECRETKEYBYTES)
        ->and($keys['public'])->not->toBe(VaultService::generateSystemKeys()['public']);
});

it('refuses a key of the wrong size', function (): void {
    expect(fn () => VaultService::useSystemKeys(base64_encode('too short')))
        ->toThrow(VaultException::class)
        ->and(fn () => VaultService::useSystemKeys($this->keys['public'], base64_encode('too short')))
        ->toThrow(VaultException::class);
});

it('is sealed a grant of its own on every write', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    expect(AppVaultGrant::count())->toBe(2)
        ->and(AppVaultGrant::query()->forSystem()->count())->toBe(1)
        ->and(AppVaultGrant::query()->forSystem()->first()->sealed_key)
        ->not->toBe(AppVaultGrant::query()->for($this->user)->first()->sealed_key);
});

it('reads an entry a user stored', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');
    vault()->lock();

    vault()->unlockAsSystem();

    expect(vault()->isSystem())->toBeTrue()
        ->and(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234')
        ->and(vault()->get($note, EVaultDetailStub::Note))->toBe('meet at noon');
});

it('reads the entries of every user', function (): void {
    $other = UserStub::create(['email' => 'other@example.test']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, 'mine');
    vault()->lock();

    vault()->unlock($other, 'their password');
    vault()->put($other, EVaultDetailStub::Pin, 'theirs');
    vault()->lock();

    vault()->unlockAsSystem();

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('mine')
        ->and(vault()->get($other, EVaultDetailStub::Pin))->toBe('theirs');
});

it('needs a registered key pair to unlock', function (): void {
    VaultService::useSystemKeys(null, null);

    expect(fn () => vault()->unlockAsSystem())->toThrow(VaultException::class);
});

it('cannot unlock with the public key alone', function (): void {
    VaultService::useSystemKeys($this->keys['public']);

    expect(fn () => vault()->unlockAsSystem())->toThrow(VaultException::class);
});

it('still gets its grant from a process holding the public key alone', function (): void {
    VaultService::useSystemKeys($this->keys['public']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    expect(AppVaultGrant::query()->forSystem()->count())->toBe(1);

    // A process that holds the secret key reads what the web process sealed.
    VaultService::useSystemKeys($this->keys['public'], $this->keys['secret']);
    vault()->unlockAsSystem();

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('cannot read an entry stored before the system key existed', function (): void {
    VaultService::useSystemKeys(null, null);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    expect(AppVaultGrant::count())->toBe(1);

    VaultService::useSystemKeys($this->keys['public'], $this->keys['secret']);
    vault()->unlockAsSystem();

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('is sealed in on the next write of an entry stored earlier', function (): void {
    VaultService::useSystemKeys(null, null);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    VaultService::useSystemKeys($this->keys['public'], $this->keys['secret']);
    vault()->put($this->user, EVaultDetailStub::Pin, '5678');
    vault()->lock();

    vault()->unlockAsSystem();

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('5678');
});

it('cannot read with a key pair that is not the one entries were sealed to', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    $other = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($other['public'], $other['secret']);
    vault()->unlockAsSystem();

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('never writes entries as the system user', function (): void {
    vault()->unlockAsSystem();

    expect(fn () => vault()->put($this->user, EVaultDetailStub::Pin, '1234'))->toThrow(VaultException::class)
        ->and(fn () => vault()->rekey($this->user, 'battery staple'))->toThrow(VaultException::class)
        ->and(AppVault::count())->toBe(0);
});

it('keeps the system unlock out of the session', function (): void {
    vault()->unlockAsSystem();

    expect(Session::has(VaultService::SESSION_KEY))->toBeFalse()
        ->and(vault()->userId())->toBeNull();
});

it('locks the system user like any other', function (): void {
    vault()->unlockAsSystem();
    vault()->lock();

    expect(vault()->isSystem())->toBeFalse()
        ->and(vault()->isLocked())->toBeTrue();
});

it('recovers the entries of a user who lost the password', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');
    vault()->lock();

    $public = AppVaultKey::query()->for($this->user)->first()->public_key;

    // No model list, no old password — the grants of the user say what to do.
    vault()->unlockAsSystem();
    expect(vault()->recover($this->user, 'battery staple'))->toBe(2);
    vault()->lock();

    vault()->unlock($this->user, 'battery staple');

    expect(AppVaultKey::query()->for($this->user)->first()->public_key)->not->toBe($public)
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234')
        ->and(vault()->get($note, EVaultDetailStub::Note))->toBe('meet at noon');
});

it('leaves the entries it cannot open out of a recovery', function (): void {
    VaultService::useSystemKeys(null, null);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Note, 'stored before the escrow');
    vault()->lock();

    VaultService::useSystemKeys($this->keys['public'], $this->keys['secret']);
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    vault()->unlockAsSystem();

    // Two grants belong to the user, only one of them is escrowed.
    expect($this->user->vaultGrants()->count())->toBe(2)
        ->and(vault()->recover($this->user, 'battery staple'))->toBe(1);

    vault()->lock();
    vault()->unlock($this->user, 'battery staple');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234')
        ->and(fn () => vault()->get($this->user, EVaultDetailStub::Note))->toThrow(VaultException::class);
});

it('recovers only as the system user', function (): void {
    vault()->unlock($this->user, 'correct horse');

    expect(fn () => vault()->recover($this->user, 'battery staple'))->toThrow(VaultException::class);
});

it('registers the keys from a module feature', function (): void {
    VaultService::useSystemKeys(null, null);
    VaultSystemStubModule::$keys = [
        'public' => $this->keys['public'],
        'secret' => $this->keys['secret'],
        'unlock' => false,
    ];

    app()->register(VaultSystemStubModule::class);

    expect(vault()->isSystem())->toBeFalse();

    vault()->unlockAsSystem();

    expect(vault()->isSystem())->toBeTrue();
});

it('unlocks from a module feature when it is asked to', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    // A fresh process, holding nothing until the module boots.
    VaultService::useSystemKeys(null, null);
    VaultSystemStubModule::$keys = [
        'public' => $this->keys['public'],
        'secret' => $this->keys['secret'],
        'unlock' => true,
    ];

    app()->register(VaultSystemStubModule::class);

    expect(vault()->isSystem())->toBeTrue()
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('leaves the vault alone when the feature registers no keys', function (): void {
    VaultService::useSystemKeys(null, null);

    app()->register(VaultSystemStubModule::class);

    expect(vault()->isLocked())->toBeTrue()
        ->and(fn () => vault()->unlockAsSystem())->toThrow(VaultException::class);
});
