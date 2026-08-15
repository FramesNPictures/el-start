<?php

use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Fnp\ElStart\Tests\Stubs\UserStub;
use Illuminate\Database\Schema\Blueprint;
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
    $this->reader = UserStub::create(['email' => 'reader@example.test']);

    // The reader needs a key pair of its own, which the first unlock mints.
    vault()->unlock($this->reader, 'their password');
    vault()->lock();

    vault()->unlock($this->user, 'correct horse');
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

it('seals an entry to another model', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    $grant = vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);

    expect($grant->keyable_type)->toBe(UserStub::class)
        ->and($grant->keyable_id)->toBe($this->reader->id)
        ->and(AppVaultGrant::count())->toBe(2);

    vault()->lock();
    vault()->unlock($this->reader, 'their password');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('shares a detail of a model that is not a user', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');
    vault()->share($note, EVaultDetailStub::Note, $this->reader);

    vault()->lock();
    vault()->unlock($this->reader, 'their password');

    expect(vault()->get($note, EVaultDetailStub::Note))->toBe('meet at noon');
});

it('keeps a shared entry readable when it is written again', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);

    vault()->put($this->user, EVaultDetailStub::Pin, '5678');

    vault()->lock();
    vault()->unlock($this->reader, 'their password');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('5678');
});

it('refuses to share with a model that never unlocked the vault', function (): void {
    $stranger = UserStub::create(['email' => 'stranger@example.test']);

    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    expect(fn () => vault()->share($this->user, EVaultDetailStub::Pin, $stranger))
        ->toThrow(VaultException::class);
});

it('refuses to share an entry that is not there', function (): void {
    expect(fn () => vault()->share($this->user, EVaultDetailStub::Pin, $this->reader))
        ->toThrow(VaultException::class);
});

it('refuses to share an entry it cannot open itself', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    vault()->lock();
    vault()->unlock($this->reader, 'their password');

    expect(fn () => vault()->share($this->user, EVaultDetailStub::Pin, $this->reader))
        ->toThrow(VaultException::class);
});

it('takes a shared entry away again', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);

    expect(vault()->revoke($this->user, EVaultDetailStub::Pin, $this->reader))->toBeTrue()
        ->and(AppVaultGrant::count())->toBe(1)
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');

    vault()->lock();
    vault()->unlock($this->reader, 'their password');

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('rotates the key of the entry when a grant is taken away', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);

    $value = AppVault::first()->value;
    $sealed = AppVaultGrant::query()->for($this->user)->first()->sealed_key;

    vault()->revoke($this->user, EVaultDetailStub::Pin, $this->reader);

    expect(AppVault::first()->value)->not->toBe($value)
        ->and(AppVaultGrant::query()->for($this->user)->first()->sealed_key)->not->toBe($sealed);
});

it('rotates the key of a shared entry for everyone left', function (): void {
    $third = UserStub::create(['email' => 'third@example.test']);
    vault()->lock();
    vault()->unlock($third, 'third password');
    vault()->lock();
    vault()->unlock($this->user, 'correct horse');

    $keys = VaultService::generateSystemKeys();
    VaultService::useSystemKeys($keys['public'], $keys['secret']);

    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);
    vault()->share($this->user, EVaultDetailStub::Pin, $third);

    vault()->revoke($this->user, EVaultDetailStub::Pin, $this->reader);

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');

    vault()->lock();
    vault()->unlock($third, 'third password');
    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');

    vault()->lock();
    vault()->unlockAsSystem();
    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('reports when there was nothing to take away', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    expect(vault()->revoke($this->user, EVaultDetailStub::Pin, $this->reader))->toBeFalse()
        ->and(AppVaultGrant::count())->toBe(1);
});

it('lists the grants a model holds', function (): void {
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($this->user, EVaultDetailStub::Recovery, 'codes');
    vault()->share($this->user, EVaultDetailStub::Pin, $this->reader);

    expect($this->user->vaultGrants()->count())->toBe(2)
        ->and($this->reader->vaultGrants()->count())->toBe(1)
        ->and($this->user->vaultKey)->not->toBeNull();
});

it('shares and revokes through the model', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');
    $this->user->shareVault(EVaultDetailStub::Pin, $this->reader);

    expect(AppVaultGrant::count())->toBe(2)
        ->and($this->user->revokeVault(EVaultDetailStub::Pin, $this->reader))->toBeTrue()
        ->and(AppVaultGrant::count())->toBe(1);
});
