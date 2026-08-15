<?php

use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Models\AppVaultKey;
use Fnp\ElStart\Services\VaultService;
use Fnp\ElStart\Tests\Stubs\EVaultDetailStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Fnp\ElStart\Tests\Stubs\UserStub;
use Illuminate\Auth\Events\Logout;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
});

afterEach(function (): void {
    VaultService::useDerivationCost(
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    );

    // The pepper and the system keys are static and would leak between tests.
    VaultService::usePepper(null);
    VaultService::useSystemKeys(null, null);

    Schema::dropIfExists(TokenableStub::TABLE);
    Schema::dropIfExists(UserStub::TABLE);
});

it('has the three vault tables', function (): void {
    expect(Schema::getColumnListing(AppVault::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'vaultable_type',
            'vaultable_id',
            'detail_eid',
            'value',
            'created_at',
            'updated_at',
        ])
        ->and(Schema::getColumnListing(AppVaultKey::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'keyable_type',
            'keyable_id',
            'public_key',
            'secret_key',
            'created_at',
            'updated_at',
        ])
        ->and(Schema::getColumnListing(AppVaultGrant::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'vault_id',
            'keyable_type',
            'keyable_id',
            'sealed_key',
            'created_at',
            'updated_at',
        ]);
});

it('resolves out of the container as a single instance', function (): void {
    expect(app(VaultService::class))->toBeInstanceOf(VaultService::class)
        ->and(app(VaultService::class))->toBe(app(VaultService::class));
});

it('has an autoloaded vault helper', function (): void {
    expect(function_exists('vault'))->toBeTrue()
        ->and(vault())->toBe(app(VaultService::class));
});

it('starts locked', function (): void {
    expect(vault()->isLocked())->toBeTrue()
        ->and(vault()->isUnlocked())->toBeFalse()
        ->and(vault()->isSystem())->toBeFalse()
        ->and(vault()->userId())->toBeNull();
});

it('mints a key pair on the first unlock', function (): void {
    expect(AppVaultKey::count())->toBe(0);

    vault()->unlock($this->user, 'correct horse');

    $key = AppVaultKey::first();

    expect(AppVaultKey::count())->toBe(1)
        ->and($key->keyable_type)->toBe(UserStub::class)
        ->and($key->keyable_id)->toBe($this->user->id)
        ->and(strlen(base64_decode($key->public_key, true)))->toBe(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES)
        ->and(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->userId())->toBe($this->user->id);
});

it('keeps the same key pair on every unlock after', function (): void {
    vault()->unlock($this->user, 'correct horse');
    $public = AppVaultKey::first()->public_key;

    vault()->lock();
    vault()->unlock($this->user, 'correct horse');

    expect(AppVaultKey::count())->toBe(1)
        ->and(AppVaultKey::first()->public_key)->toBe($public);
});

it('never stores the secret key in the clear', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $key = AppVaultKey::first();

    expect(base64_decode($key->secret_key, true))->not->toBe(base64_decode($key->public_key, true))
        ->and(strlen(base64_decode($key->secret_key, true)))
        ->toBeGreaterThan(SODIUM_CRYPTO_BOX_SECRETKEYBYTES)
        ->and($key->toArray())->not->toHaveKey('secret_key');
});

it('turns a wrong password down at the unlock', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->lock();

    expect(fn () => vault()->unlock($this->user, 'wrong horse'))->toThrow(VaultException::class);
});

it('refuses to derive a key without an id or an email address', function (): void {
    expect(fn () => vault()->unlock(new UserStub(['email' => 'user@example.test']), 'pw'))
        ->toThrow(VaultException::class)
        ->and(fn () => vault()->unlock(UserStub::create(['email' => null]), 'pw'))
        ->toThrow(VaultException::class);
});

it('keeps the derived key wrapped in the session', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $stored = Session::get(VaultService::SESSION_KEY);

    expect($stored)->toHaveKeys(['user', 'type', 'key'])
        ->and($stored['type'])->toBe(UserStub::class)
        // The session holds the key encrypted with the application key, never raw.
        ->and(json_decode(base64_decode($stored['key'], true), true))->toHaveKeys(['iv', 'value', 'mac'])
        ->and(strlen(base64_decode(Crypt::decryptString($stored['key']), true)))->toBe(32);
});

it('restores the key pair from the session within a request', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    // A fresh service, holding nothing but the session.
    app()->forgetInstance(VaultService::class);

    expect(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('refuses to read or write while locked', function (): void {
    expect(fn () => vault()->put($this->user, EVaultDetailStub::Pin, '1234'))->toThrow(VaultException::class)
        ->and(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->not->toThrow(VaultException::class);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('locks by dropping the key from the session', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->lock();

    expect(vault()->isLocked())->toBeTrue()
        ->and(Session::has(VaultService::SESSION_KEY))->toBeFalse()
        ->and(vault()->userId())->toBeNull();
});

it('locks the vault when the user logs out', function (): void {
    vault()->unlock($this->user, 'correct horse');

    Event::dispatch(new Logout('web', $this->user));

    expect(vault()->isLocked())->toBeTrue();
});

it('seals a new entry to the model that wrote it', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $entry = vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    $grant = AppVaultGrant::first();

    expect(AppVaultGrant::count())->toBe(1)
        ->and($grant->vault_id)->toBe($entry->id)
        ->and($grant->keyable_type)->toBe(UserStub::class)
        ->and($grant->keyable_id)->toBe($this->user->id)
        ->and($grant->toArray())->not->toHaveKey('sealed_key');
});

it('stores and reads a value of any type', function (): void {
    vault()->unlock($this->user, 'correct horse');

    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($this->user, EVaultDetailStub::Recovery, ['a', 'b']);
    vault()->put($this->user, EVaultDetailStub::Note, null);

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234')
        ->and(vault()->get($this->user, EVaultDetailStub::Recovery))->toBe(['a', 'b'])
        ->and(vault()->get($this->user, EVaultDetailStub::Note))->toBeNull();
});

it('stores the detail of any other model', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');

    expect(vault()->get($note, EVaultDetailStub::Note))->toBe('meet at noon')
        ->and(AppVault::first()->vaultable)->toBeInstanceOf(TokenableStub::class);
});

it('stores the detail as an integer', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    expect(DB::table(AppVault::TABLE)->value('detail_eid'))->toBe(EVaultDetailStub::Pin->value)
        ->and(AppVault::first()->detail_eid)->toBe(EVaultDetailStub::Pin->value);
});

it('never writes the value in the clear', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Note, 'meet at noon');

    $stored = AppVault::first()->value;

    expect($stored)->not->toContain('meet at noon')
        ->and(base64_decode($stored, true))->not->toContain('meet at noon');
});

it('encrypts the same value differently every time', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $first = vault()->encrypt('same', EVaultDetailStub::Pin);
    $second = vault()->encrypt('same', EVaultDetailStub::Pin);

    expect($first)->not->toBe($second)
        ->and(vault()->decrypt($first, EVaultDetailStub::Pin))->toBe('same')
        ->and(vault()->decrypt($second, EVaultDetailStub::Pin))->toBe('same');
});

it('gives every detail its own payload key', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $payload = vault()->encrypt('secret', EVaultDetailStub::Pin);

    expect(fn () => vault()->decrypt($payload, EVaultDetailStub::Note))->toThrow(VaultException::class);
});

it('binds a payload to its context', function (): void {
    vault()->unlock($this->user, 'correct horse');

    $payload = vault()->encrypt('secret', EVaultDetailStub::Pin, 'one');

    expect(fn () => vault()->decrypt($payload, EVaultDetailStub::Pin, 'two'))->toThrow(VaultException::class);
});

it('rejects a tampered entry', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    $entry = AppVault::first();
    $raw = base64_decode($entry->value, true);
    $entry->forceFill(['value' => base64_encode(substr($raw, 0, -1) . chr(ord(substr($raw, -1)) ^ 1))])->save();

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('cannot read an entry moved to another model', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    AppVault::first()->forceFill([
        'vaultable_type' => $note->getMorphClass(),
        'vaultable_id' => $note->getKey(),
    ])->save();

    expect(fn () => vault()->get($note, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('cannot read an entry it holds no grant on', function (): void {
    $other = UserStub::create(['email' => 'other@example.test']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    vault()->lock();
    vault()->unlock($other, 'their password');

    expect(fn () => vault()->get($this->user, EVaultDetailStub::Pin))->toThrow(VaultException::class);
});

it('replaces the entry of the same detail without a new key', function (): void {
    vault()->unlock($this->user, 'correct horse');

    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    $sealed = AppVaultGrant::first()->sealed_key;

    vault()->put($this->user, EVaultDetailStub::Pin, '5678');

    expect(AppVault::count())->toBe(1)
        ->and(AppVaultGrant::count())->toBe(1)
        ->and(AppVaultGrant::first()->sealed_key)->toBe($sealed)
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('5678');
});

it('reports and removes the entries without a key', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($this->user, EVaultDetailStub::Recovery, 'codes');
    vault()->lock();

    expect(vault()->has($this->user, EVaultDetailStub::Pin))->toBeTrue()
        ->and(vault()->has($this->user, EVaultDetailStub::Note))->toBeFalse()
        ->and(vault()->remove($this->user, EVaultDetailStub::Pin))->toBe(1)
        ->and(vault()->remove($this->user))->toBe(1)
        ->and(AppVault::count())->toBe(0)
        // The keys of an entry go with it.
        ->and(AppVaultGrant::count())->toBe(0);
});

it('peppers the derivation with the application key by default', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->lock();

    config()->set('app.key', 'base64:' . base64_encode(str_repeat('rotated!', 4)));

    expect(fn () => vault()->unlock($this->user, 'correct horse'))->toThrow(VaultException::class);
});

it('peppers the derivation with a secret of its own', function (): void {
    VaultService::usePepper('a secret of the application');

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->lock();

    // The application key no longer takes part in the derivation.
    config()->set('app.key', 'base64:' . base64_encode(str_repeat('rotated!', 4)));
    vault()->unlock($this->user, 'correct horse');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');

    VaultService::usePepper('another secret');
    vault()->lock();

    expect(fn () => vault()->unlock($this->user, 'correct horse'))->toThrow(VaultException::class);
});

it('changes the password without touching a single entry', function (): void {
    $note = TokenableStub::create(['name' => 'Note']);

    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');
    vault()->put($note, EVaultDetailStub::Note, 'meet at noon');

    $entries = AppVault::pluck('value', 'id')->all();
    $grants = AppVaultGrant::pluck('sealed_key', 'id')->all();
    $public = AppVaultKey::first()->public_key;

    vault()->rekey($this->user, 'battery staple');

    expect(AppVault::pluck('value', 'id')->all())->toBe($entries)
        ->and(AppVaultGrant::pluck('sealed_key', 'id')->all())->toBe($grants)
        ->and(AppVaultKey::first()->public_key)->toBe($public);

    // Only the new password opens the key pair now.
    vault()->lock();
    vault()->unlock($this->user, 'battery staple');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234')
        ->and(vault()->get($note, EVaultDetailStub::Note))->toBe('meet at noon');

    vault()->lock();

    expect(fn () => vault()->unlock($this->user, 'correct horse'))->toThrow(VaultException::class);
});

it('changes the email address without touching a single entry', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    $this->user->email = 'renamed@example.test';
    vault()->rekey($this->user, 'correct horse');

    vault()->lock();
    vault()->unlock($this->user, 'correct horse');

    expect(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('leaves the session unlocked after a rekey', function (): void {
    vault()->unlock($this->user, 'correct horse');
    vault()->put($this->user, EVaultDetailStub::Pin, '1234');

    vault()->rekey($this->user, 'battery staple');

    expect(vault()->isUnlocked())->toBeTrue()
        ->and(vault()->get($this->user, EVaultDetailStub::Pin))->toBe('1234');
});

it('refuses to rekey while locked', function (): void {
    expect(fn () => vault()->rekey($this->user, 'battery staple'))->toThrow(VaultException::class);
});
