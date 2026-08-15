<?php

namespace Fnp\ElStart\Services;

use Fnp\ElStart\Contracts\VaultDetail;
use Fnp\ElStart\Events\VaultClosed;
use Fnp\ElStart\Events\VaultOpened;
use Fnp\ElStart\Events\VaultRekeyed;
use Fnp\ElStart\Events\VaultRemoved;
use Fnp\ElStart\Events\VaultUpdated;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Models\AppVaultKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Stores data of any model encrypted for the key pairs that may read it.
 *
 * Every model that unlocks the vault owns an X25519 key pair. The public half
 * is stored in the clear, the secret half encrypted with a key derived from
 * the user record and the password with Argon2id — so a password or an email
 * address may change without touching a single entry.
 *
 * An entry carries a random key of its own. The value is encrypted with it and
 * the key is sealed once per reader into `app_vault_grants`: to the model that
 * wrote it, to the system user when a public key is registered, and to anyone
 * it is shared with afterwards. Sealing needs a public key alone, so a process
 * can escrow entries it will never be able to read back.
 */
class VaultService
{
    /**
     * Session key holding the identity of the unlocked model and its key.
     */
    public const SESSION_KEY = 'el-start.vault';

    /**
     * Key pair of this process: type, id, public and secret key.
     *
     * @var array{type: string, id: int|string, public: string, secret: string}|null
     */
    protected ?array $identity = null;

    /**
     * Master key of the current session, unwrapped for the lifetime of the request.
     */
    protected ?string $key = null;

    protected static int $memoryLimit = SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE;

    protected static int $operationLimit = SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE;

    /**
     * Secret peppering the key derivation, the application key when not set.
     */
    protected static ?string $pepper = null;

    protected static ?string $systemPublicKey = null;

    protected static ?string $systemSecretKey = null;

    /**
     * Generate a system key pair, both halves base64 encoded.
     *
     * Keep the public one wherever entries are written and the secret one only
     * where they may be read. Store both outside the repository.
     *
     * @return array{public: string, secret: string}
     */
    public static function generateSystemKeys(): array
    {
        $pair = sodium_crypto_box_keypair();

        return [
            'public' => base64_encode(sodium_crypto_box_publickey($pair)),
            'secret' => base64_encode(sodium_crypto_box_secretkey($pair)),
        ];
    }

    /**
     * Set the cost of the key derivation.
     *
     * The defaults are the interactive libsodium limits. Raise them with the
     * sensitive ones on hardware that can afford it, and keep them stable —
     * changing the cost locks every key pair out of its password.
     *
     * @param  int  $operations  Argon2id operations limit
     * @param  int  $memory  Argon2id memory limit in bytes
     */
    public static function useDerivationCost(int $operations, int $memory): void
    {
        static::$operationLimit = $operations;
        static::$memoryLimit = $memory;
    }

    /**
     * Pepper the key derivation with a secret of your own.
     *
     * Defaults to the application key. Use a separate secret to keep the vault
     * closed even when the application key leaks, and keep it stable and
     * backed up — a lost or rotated pepper locks every key pair away.
     *
     * @param  string|null  $secret  Secret to pepper with, null for the application key
     */
    public static function usePepper(#[SensitiveParameter] ?string $secret): void
    {
        static::$pepper = $secret;
    }

    /**
     * Register the key pair of the system user, both halves base64 encoded.
     *
     * The public key is enough to seal new entries, so web processes should be
     * given that one alone. Pass the secret key only where the system user is
     * allowed to read, and register it from a module feature rather than from
     * a call site.
     *
     * @param  string|null  $publicKey  Public key entries are sealed to, null to seal nothing
     * @param  string|null  $secretKey  Secret key the system user reads with, null in processes that may not
     *
     * @throws VaultException When a key is not of the expected size
     */
    public static function useSystemKeys(?string $publicKey, #[SensitiveParameter] ?string $secretKey = null): void
    {
        static::$systemPublicKey = static::decodeKey($publicKey, 'public', SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
        static::$systemSecretKey = static::decodeKey($secretKey, 'secret', SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
    }

    /**
     * Decrypt a payload with the subkey of a detail.
     *
     * Payloads are tied to the password of the session and are sealed to no
     * key pair — only the entries of the table are.
     *
     * @param  string  $payload  Payload produced by encrypt()
     * @param  VaultDetail  $detail  Detail the payload was encrypted under
     * @param  string  $context  Associated data the payload is bound to
     *
     * @throws VaultException When no password derived key is loaded
     */
    public function decrypt(string $payload, VaultDetail $detail, string $context = ''): mixed
    {
        return json_decode(
            $this->decryptWith($this->subkey($this->key(), (string) $detail->value), $payload, $context),
            true,
        );
    }

    /**
     * Encrypt a value with the subkey of a detail.
     *
     * @param  mixed  $value  Anything that survives a JSON round trip
     * @param  VaultDetail  $detail  Detail to encrypt under
     * @param  string  $context  Associated data to bind the payload to
     *
     * @throws VaultException When no password derived key is loaded
     */
    public function encrypt(mixed $value, VaultDetail $detail, string $context = ''): string
    {
        return $this->encryptWith($this->subkey($this->key(), (string) $detail->value), json_encode($value), $context);
    }

    /**
     * Read a detail of a model out of the vault.
     *
     * @param  Model  $model  Model the detail belongs to
     * @param  VaultDetail  $detail  Detail to read
     * @param  mixed  $default  Returned when the model holds no such detail
     *
     * @throws VaultException When the vault is locked or nothing grants access
     */
    public function get(Model $model, VaultDetail $detail, mixed $default = null): mixed
    {
        $entry = $this->entry($model, $detail);

        if ($entry === null) {
            return $default;
        }

        return json_decode(
            $this->decryptWith($this->entryKey($entry), $entry->value, $entry->context()),
            true,
        );
    }

    /**
     * Whether the model holds the given detail. Reading the presence of an
     * entry does not need the vault to be unlocked.
     */
    public function has(Model $model, VaultDetail $detail): bool
    {
        return AppVault::query()->for($model)->ofDetail($detail)->exists();
    }

    /**
     * Whether the session holds no key.
     */
    public function isLocked(): bool
    {
        return ! $this->isUnlocked();
    }

    /**
     * Whether this process is unlocked as the system user.
     */
    public function isSystem(): bool
    {
        return ($this->identity['type'] ?? null) === AppVaultGrant::SYSTEM_TYPE;
    }

    /**
     * Whether a key pair of either kind is loaded.
     */
    public function isUnlocked(): bool
    {
        return $this->identity !== null || Session::has(self::SESSION_KEY . '.key');
    }

    /**
     * Drop every key, leaving the entries unreadable until the next unlock.
     *
     * Locking a vault that holds nothing is a no-op and announces nothing.
     */
    public function lock(): void
    {
        if ($this->isLocked()) {
            return;
        }

        $identity = $this->identity['type'] ?? Session::get(self::SESSION_KEY . '.type');
        $identityId = $this->identity['id'] ?? $this->userId();

        $this->identity = null;
        $this->key = null;

        Session::forget(self::SESSION_KEY);

        if (is_string($identity)) {
            Event::dispatch(new VaultClosed($identity, $identityId));
        }
    }

    /**
     * Write a detail of a model into the vault, replacing the stored one.
     *
     * The entry is sealed to the key pair of this session and to the system
     * user when one is registered. An entry that already exists keeps its key,
     * so everyone it was shared with keeps reading it.
     *
     * @param  Model  $model  Model the detail belongs to
     * @param  VaultDetail  $detail  Detail to write
     * @param  mixed  $value  Anything that survives a JSON round trip
     *
     * @throws VaultException When the vault is locked or unlocked as the system user
     */
    public function put(Model $model, VaultDetail $detail, mixed $value): AppVault
    {
        if ($this->isSystem()) {
            throw VaultException::systemIsReadOnly();
        }

        $identity = $this->identity();
        $entry = $this->entry($model, $detail);
        $created = $entry === null;
        $entryKey = $created
            ? random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES)
            : $this->entryKey($entry);

        $entry ??= new AppVault();

        $entry = DB::transaction(function () use ($model, $detail, $value, $entry, $entryKey, $identity): AppVault {
            $entry->forceFill([
                'vaultable_type' => $model->getMorphClass(),
                'vaultable_id' => $model->getKey(),
                'detail_eid' => $detail->value,
                'value' => $this->encryptWith($entryKey, json_encode($value), $this->context($model, $detail)),
            ])->save();

            $this->grant($entry, $identity['type'], $identity['id'], $identity['public'], $entryKey);

            if (static::$systemPublicKey !== null) {
                $this->grant(
                    $entry,
                    AppVaultGrant::SYSTEM_TYPE,
                    AppVaultGrant::SYSTEM_ID,
                    static::$systemPublicKey,
                    $entryKey,
                );
            }

            return $entry;
        });

        Event::dispatch(new VaultUpdated($entry, $created));

        return $entry;
    }

    /**
     * Hand the entries a model can open over to a fresh key pair.
     *
     * This is the recovery path for a password nobody remembers: unlocked as
     * the system user, it mints a key pair for the given password and re-seals
     * every entry the system can open to it. Entries that were never sealed to
     * the system user are left as they are.
     *
     * @param  Model  $user  User record the new key pair belongs to
     * @param  string  $password  New password protecting it
     * @return int Number of re-sealed entries
     *
     * @throws VaultException When the vault is not unlocked as the system user
     */
    public function recover(Model $user, #[SensitiveParameter] string $password): int
    {
        if (! $this->isSystem()) {
            throw VaultException::systemUnavailable();
        }

        $master = $this->deriveKey($user, $password);

        $count = DB::transaction(function () use ($user, $master): int {
            $pair = $this->mint($user, $master);
            $count = 0;

            foreach (AppVaultGrant::query()->for($user)->with('vault')->get() as $grant) {
                try {
                    $entryKey = $this->entryKey($grant->vault);
                } catch (VaultException) {
                    // Never sealed to the system user, so it cannot be handed over.
                    continue;
                }

                $grant->sealed_key = base64_encode(sodium_crypto_box_seal($entryKey, $pair['public']));
                $grant->save();
                $count++;
            }

            return $count;
        });

        Event::dispatch(new VaultRekeyed($user, recovered: true, entries: $count));

        return $count;
    }

    /**
     * Derive a new key from the password and re-wrap the key pair with it.
     *
     * Call it whenever the password or the email address of the user changes,
     * while the vault is still unlocked with the old ones. Not a single entry
     * is touched — only the secret key of the model is wrapped anew — and the
     * session is left unlocked.
     *
     * @param  Model  $user  User record as it will be stored
     * @param  string  $password  New password
     *
     * @throws VaultException When the vault is locked or unlocked as the system user
     */
    public function rekey(Model $user, #[SensitiveParameter] string $password): void
    {
        if ($this->isSystem()) {
            throw VaultException::systemIsReadOnly();
        }

        $identity = $this->identity();
        $master = $this->deriveKey($user, $password);
        $row = AppVaultKey::query()->for($user)->first() ?? new AppVaultKey();

        $row->forceFill([
            'keyable_type' => $user->getMorphClass(),
            'keyable_id' => $user->getKey(),
            'public_key' => base64_encode($identity['public']),
            'secret_key' => $this->encryptWith(
                $this->identityKey($master),
                $identity['secret'],
                $this->keyContext($user),
            ),
        ])->save();

        $this->remember($user, $master);

        Event::dispatch(new VaultRekeyed($user));
    }

    /**
     * Drop the entries of a model, either one detail or all of them. Their
     * keys go with them, and neither needs the vault to be unlocked.
     *
     * @param  Model  $model  Model the entries belong to
     * @param  VaultDetail|null  $detail  Detail to drop, all of them when omitted
     * @return int Number of removed entries
     */
    public function remove(Model $model, ?VaultDetail $detail = null): int
    {
        $entries = AppVault::query()
            ->for($model)
            ->when($detail, fn ($query, VaultDetail $only) => $query->ofDetail($only))
            ->get();

        $count = DB::transaction(function () use ($entries): int {
            $entries->each->delete();

            return $entries->count();
        });

        if ($count > 0) {
            Event::dispatch(new VaultRemoved($model, $detail, $count));
        }

        return $count;
    }

    /**
     * Take a grant away and rotate the key of the entry, so the value stored
     * from now on is out of reach of whoever held it.
     *
     * @param  Model  $model  Model the detail belongs to
     * @param  VaultDetail  $detail  Detail to revoke access to
     * @param  Model  $reader  Model losing access
     * @return bool Whether there was a grant to take away
     *
     * @throws VaultException When the entry is not there or this process cannot open it
     */
    public function revoke(Model $model, VaultDetail $detail, Model $reader): bool
    {
        $entry = $this->entry($model, $detail) ?? throw VaultException::noEntry();
        $entryKey = $this->entryKey($entry);

        if (! AppVaultGrant::query()->where('vault_id', $entry->id)->for($reader)->exists()) {
            return false;
        }

        return DB::transaction(function () use ($entry, $entryKey, $reader): bool {
            AppVaultGrant::query()->where('vault_id', $entry->id)->for($reader)->delete();

            $rotated = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES);

            $entry->value = $this->encryptWith(
                $rotated,
                $this->decryptWith($entryKey, $entry->value, $entry->context()),
                $entry->context(),
            );

            $entry->save();

            foreach ($entry->grants()->get() as $grant) {
                $grant->sealed_key = base64_encode(
                    sodium_crypto_box_seal($rotated, $this->publicKeyOf($grant->keyable_type, $grant->keyable_id)),
                );

                $grant->save();
            }

            return true;
        });
    }

    /**
     * Seal an entry to the key pair of another model, so it can read it too.
     *
     * @param  Model  $model  Model the detail belongs to
     * @param  VaultDetail  $detail  Detail to share
     * @param  Model  $reader  Model gaining access, needs a key pair of its own
     *
     * @throws VaultException When the entry is not there, this process cannot
     *                        open it, or the reader never unlocked the vault
     */
    public function share(Model $model, VaultDetail $detail, Model $reader): AppVaultGrant
    {
        $entry = $this->entry($model, $detail) ?? throw VaultException::noEntry();

        $row = AppVaultKey::query()->for($reader)->first();

        if ($row === null) {
            throw VaultException::noPublicKey($reader);
        }

        return $this->grant(
            $entry,
            $reader->getMorphClass(),
            $reader->getKey(),
            base64_decode($row->public_key, true),
            $this->entryKey($entry),
        );
    }

    /**
     * Unlock the vault with the user record and the password.
     *
     * The password is never stored. It derives the key that wraps the secret
     * key of the model, which is minted the first time and read back every
     * time after — a wrong password fails right here rather than later.
     *
     * @param  Model  $user  User record, needs an id and an email address
     * @param  string  $password  Password only the user knows
     *
     * @throws VaultException When the user cannot be turned into a key or the
     *                        password does not open the stored key pair
     */
    public function unlock(Model $user, #[SensitiveParameter] string $password): void
    {
        $master = $this->deriveKey($user, $password);
        $row = AppVaultKey::query()->for($user)->first();

        $this->identity = $row === null
            ? $this->mint($user, $master)
            : $this->open($user, $row, $master);

        $this->remember($user, $master);

        Event::dispatch(new VaultOpened($user->getMorphClass(), $user->getKey(), minted: $row === null));
    }

    /**
     * Unlock the vault as the system user, reading through the grants sealed
     * to its public key.
     *
     * Nothing of this is written to the session: the key pair lives in memory
     * and dies with the process, so a request that unlocks as the system user
     * does not hand system access to the next one.
     *
     * @throws VaultException When this process holds no system key pair
     */
    public function unlockAsSystem(): void
    {
        if (static::$systemPublicKey === null || static::$systemSecretKey === null) {
            throw VaultException::systemUnavailable();
        }

        $this->identity = [
            'type' => AppVaultGrant::SYSTEM_TYPE,
            'id' => AppVaultGrant::SYSTEM_ID,
            'public' => static::$systemPublicKey,
            'secret' => static::$systemSecretKey,
        ];

        Event::dispatch(new VaultOpened(AppVaultGrant::SYSTEM_TYPE, AppVaultGrant::SYSTEM_ID));
    }

    /**
     * Id of the model the session is unlocked for, null for the system user.
     */
    public function userId(): int|string|null
    {
        return Session::get(self::SESSION_KEY . '.user');
    }

    /**
     * Decode a base64 key and check its size.
     *
     * @throws VaultException When the key is not of the expected size
     */
    protected static function decodeKey(?string $key, string $which, int $expected): ?string
    {
        if ($key === null || $key === '') {
            return null;
        }

        $raw = base64_decode($key, true);

        if ($raw === false || strlen($raw) !== $expected) {
            throw VaultException::systemKeyInvalid($which, $expected);
        }

        return $raw;
    }

    /**
     * The data an entry is bound to as associated data.
     */
    protected function context(Model $model, VaultDetail $detail): string
    {
        return implode('|', [$model->getMorphClass(), $model->getKey(), $detail->value]);
    }

    /**
     * Decrypt a payload with the given key.
     *
     * @throws VaultException When the payload does not decrypt
     */
    protected function decryptWith(string $key, string $payload, string $context): string
    {
        $raw = base64_decode($payload, true);
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;

        if ($raw === false || strlen($raw) <= $nonceLength) {
            throw VaultException::unreadable();
        }

        $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($raw, $nonceLength),
            $context,
            substr($raw, 0, $nonceLength),
            $key,
        );

        if ($plain === false) {
            throw VaultException::unreadable();
        }

        return $plain;
    }

    /**
     * Derive the key wrapping the secret key of a user record.
     *
     * The id and the email address salt the derivation, the pepper keeps it
     * tied to a secret of the application, and Argon2id makes guessing the
     * password expensive.
     *
     * @throws VaultException When the user has no id or no email address
     */
    protected function deriveKey(Model $user, #[SensitiveParameter] string $password): string
    {
        $id = $user->getKey();
        $email = Str::lower((string) $user->getAttribute('email'));

        if ($id === null || $email === '') {
            throw VaultException::unidentified($user);
        }

        $salt = substr(
            hash_hmac('sha256', $id . '|' . $email, $this->pepper(), true),
            0,
            SODIUM_CRYPTO_PWHASH_SALTBYTES,
        );

        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            $password,
            $salt,
            static::$operationLimit,
            static::$memoryLimit,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }

    /**
     * Encrypt a value with the given key.
     */
    protected function encryptWith(string $key, string $plain, string $context): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        return base64_encode($nonce . sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plain,
            $context,
            $nonce,
            $key,
        ));
    }

    /**
     * The stored entry of a model, if there is one.
     */
    protected function entry(Model $model, VaultDetail $detail): ?AppVault
    {
        return AppVault::query()->for($model)->ofDetail($detail)->first();
    }

    /**
     * The key an entry is encrypted with, unsealed from the grant issued to
     * the key pair this process holds.
     *
     * @throws VaultException When the vault is locked or nothing grants access
     */
    protected function entryKey(AppVault $entry): string
    {
        $identity = $this->identity();

        $grant = AppVaultGrant::query()
            ->where('vault_id', $entry->id)
            ->where('keyable_type', $identity['type'])
            ->where('keyable_id', $identity['id'])
            ->first();

        if ($grant === null) {
            throw VaultException::notGranted();
        }

        $sealed = base64_decode($grant->sealed_key, true);

        $key = $sealed === false ? false : sodium_crypto_box_seal_open(
            $sealed,
            sodium_crypto_box_keypair_from_secretkey_and_publickey($identity['secret'], $identity['public']),
        );

        if ($key === false) {
            throw VaultException::unreadable();
        }

        return $key;
    }

    /**
     * Seal the key of an entry to a public key.
     *
     * A grant that is already there is left untouched: it opens the same entry
     * key, and rewriting it would only churn the row on every write.
     */
    protected function grant(
        AppVault $entry,
        string $type,
        int|string $id,
        string $publicKey,
        #[SensitiveParameter]
        string $entryKey,
    ): AppVaultGrant {
        $grant = AppVaultGrant::query()
            ->where('vault_id', $entry->id)
            ->where('keyable_type', $type)
            ->where('keyable_id', $id)
            ->first();

        if ($grant !== null) {
            return $grant;
        }

        $grant = new AppVaultGrant();

        $grant->forceFill([
            'vault_id' => $entry->id,
            'keyable_type' => $type,
            'keyable_id' => $id,
            'sealed_key' => base64_encode(sodium_crypto_box_seal($entryKey, $publicKey)),
        ])->save();

        return $grant;
    }

    /**
     * The key pair this process holds, restored from the session when the
     * request has not touched it yet.
     *
     * @return array{type: string, id: int|string, public: string, secret: string}
     *
     * @throws VaultException When the vault is locked
     */
    protected function identity(): array
    {
        if ($this->identity !== null) {
            return $this->identity;
        }

        $master = $this->key();
        $type = Session::get(self::SESSION_KEY . '.type');
        $id = $this->userId();

        $row = AppVaultKey::query()
            ->where('keyable_type', $type)
            ->where('keyable_id', $id)
            ->first();

        if (! is_string($type) || $row === null) {
            throw VaultException::locked();
        }

        return $this->identity = [
            'type' => $type,
            'id' => $id,
            'public' => base64_decode($row->public_key, true),
            'secret' => $this->decryptWith($this->identityKey($master), $row->secret_key, $row->context()),
        ];
    }

    /**
     * The key wrapping the secret key of a model, derived from its password.
     */
    protected function identityKey(#[SensitiveParameter] string $master): string
    {
        return $this->subkey($master, 'identity');
    }

    /**
     * The master key of the session.
     *
     * @throws VaultException When the session holds no key
     */
    protected function key(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $wrapped = Session::get(self::SESSION_KEY . '.key');

        if (! is_string($wrapped)) {
            throw VaultException::locked();
        }

        $key = base64_decode(Crypt::decryptString($wrapped), true);

        if ($key === false) {
            throw VaultException::locked();
        }

        return $this->key = $key;
    }

    /**
     * The data the secret key of a model is bound to.
     */
    protected function keyContext(Model $model): string
    {
        return implode('|', [$model->getMorphClass(), $model->getKey()]);
    }

    /**
     * Mint a key pair for a model and store it wrapped with its password.
     *
     * @return array{type: string, id: int|string, public: string, secret: string}
     */
    protected function mint(Model $model, #[SensitiveParameter] string $master): array
    {
        $pair = sodium_crypto_box_keypair();
        $public = sodium_crypto_box_publickey($pair);
        $secret = sodium_crypto_box_secretkey($pair);

        $row = AppVaultKey::query()->for($model)->first() ?? new AppVaultKey();

        $row->forceFill([
            'keyable_type' => $model->getMorphClass(),
            'keyable_id' => $model->getKey(),
            'public_key' => base64_encode($public),
            'secret_key' => $this->encryptWith($this->identityKey($master), $secret, $this->keyContext($model)),
        ])->save();

        return [
            'type' => $model->getMorphClass(),
            'id' => $model->getKey(),
            'public' => $public,
            'secret' => $secret,
        ];
    }

    /**
     * Open a stored key pair with the key derived from the password.
     *
     * @return array{type: string, id: int|string, public: string, secret: string}
     *
     * @throws VaultException When the password does not open it
     */
    protected function open(Model $model, AppVaultKey $row, #[SensitiveParameter] string $master): array
    {
        return [
            'type' => $model->getMorphClass(),
            'id' => $model->getKey(),
            'public' => base64_decode($row->public_key, true),
            'secret' => $this->decryptWith($this->identityKey($master), $row->secret_key, $row->context()),
        ];
    }

    /**
     * The secret peppering the key derivation.
     */
    protected function pepper(): string
    {
        return static::$pepper ?? (string) config('app.key');
    }

    /**
     * The public key a grant was issued to.
     *
     * @throws VaultException When it cannot be resolved any more
     */
    protected function publicKeyOf(string $type, int|string $id): string
    {
        if ($type === AppVaultGrant::SYSTEM_TYPE) {
            return static::$systemPublicKey ?? throw VaultException::systemUnavailable();
        }

        $row = AppVaultKey::query()
            ->where('keyable_type', $type)
            ->where('keyable_id', $id)
            ->first();

        if ($row === null) {
            throw VaultException::notGranted();
        }

        return base64_decode($row->public_key, true);
    }

    /**
     * Keep the key derived from the password for the rest of the session.
     *
     * The key is wrapped with the application key, so a session store that
     * ends up in the same database as the entries is of no use on its own.
     */
    protected function remember(Model $user, #[SensitiveParameter] string $key): void
    {
        $this->key = $key;

        Session::put(self::SESSION_KEY, [
            'user' => $user->getKey(),
            'type' => $user->getMorphClass(),
            'key' => Crypt::encryptString(base64_encode($key)),
        ]);
    }

    /**
     * A key derived from the master key for one purpose only.
     */
    protected function subkey(#[SensitiveParameter] string $key, string $salt): string
    {
        return hash_hkdf(
            'sha256',
            $key,
            SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES,
            self::SESSION_KEY,
            $salt,
        );
    }
}
