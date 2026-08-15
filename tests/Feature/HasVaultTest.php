<?php

use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
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

    vault()->unlock($this->user, 'correct horse');
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    // The pepper is static and would otherwise leak between tests.
    VaultService::usePepper(null);

    Schema::dropIfExists(TokenableStub::TABLE);
    Schema::dropIfExists(UserStub::TABLE);
});

it('puts and reads a detail through the model', function (): void {
    $entry = $this->user->putVault(EVaultDetailStub::Pin, '1234');

    expect($entry)->toBeInstanceOf(AppVault::class)
        ->and($entry->vaultable_type)->toBe(UserStub::class)
        ->and($entry->vaultable_id)->toBe($this->user->id)
        ->and($entry->detailValue())->toBe(EVaultDetailStub::Pin->value)
        ->and($this->user->vaultValue(EVaultDetailStub::Pin))->toBe('1234')
        ->and($this->user->vaultValue(EVaultDetailStub::Note, 'fallback'))->toBe('fallback');
});

it('works on a model that is not the user', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    $note->putVault(EVaultDetailStub::Note, 'meet at noon');

    expect($note->vaultValue(EVaultDetailStub::Note))->toBe('meet at noon')
        ->and($note->vault()->count())->toBe(1);
});

it('keeps the entries of each model apart', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    $this->user->putVault(EVaultDetailStub::Pin, 'mine');
    $note->putVault(EVaultDetailStub::Pin, 'theirs');

    expect($this->user->vaultValue(EVaultDetailStub::Pin))->toBe('mine')
        ->and($note->vaultValue(EVaultDetailStub::Pin))->toBe('theirs')
        ->and($this->user->vault()->count())->toBe(1);
});

it('tells whether a detail is there', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');

    expect($this->user->hasVault(EVaultDetailStub::Pin))->toBeTrue()
        ->and($this->user->hasVault(EVaultDetailStub::Note))->toBeFalse();
});

it('lists the details of the model', function (): void {
    $this->user->putVault(EVaultDetailStub::Recovery, 'codes');
    $this->user->putVault(EVaultDetailStub::Pin, '1234');

    expect($this->user->vaultDetails()->all())
        ->toBe([EVaultDetailStub::Pin->value, EVaultDetailStub::Recovery->value]);
});

it('removes one detail or all of them', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');
    $this->user->putVault(EVaultDetailStub::Recovery, 'codes');
    $this->user->putVault(EVaultDetailStub::Note, 'hello');

    expect($this->user->removeVault(EVaultDetailStub::Pin))->toBe(1)
        ->and($this->user->vaultDetails()->all())
        ->toBe([EVaultDetailStub::Note->value, EVaultDetailStub::Recovery->value])
        ->and($this->user->removeVault())->toBe(2)
        ->and($this->user->vault()->count())->toBe(0);
});

it('leaves the entries of other models alone when removing', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    $this->user->putVault(EVaultDetailStub::Pin, 'mine');
    $note->putVault(EVaultDetailStub::Pin, 'theirs');

    expect($this->user->removeVault())->toBe(1)
        ->and($note->vaultValue(EVaultDetailStub::Pin))->toBe('theirs');
});

it('needs the vault unlocked to write and to read', function (): void {
    $this->user->putVault(EVaultDetailStub::Pin, '1234');

    vault()->lock();

    expect(fn () => $this->user->putVault(EVaultDetailStub::Pin, '5678'))->toThrow(VaultException::class)
        ->and(fn () => $this->user->vaultValue(EVaultDetailStub::Pin))->toThrow(VaultException::class)
        // Details and presence stay addressable while the vault is locked.
        ->and($this->user->hasVault(EVaultDetailStub::Pin))->toBeTrue()
        ->and($this->user->vaultDetails()->all())->toBe([EVaultDetailStub::Pin->value]);
});

it('hides the ciphertext from array output', function (): void {
    $entry = $this->user->putVault(EVaultDetailStub::Pin, '1234');

    expect($entry->toArray())->not->toHaveKey('value')
        ->and($entry->value)->toBeString();
});
