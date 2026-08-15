@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# El Start

- `framesnpictures/el-start` is an opinionated Laravel start module built on `framesnpictures/el-module`. It renames Laravel's framework-owned tables and ships the migrations that create them.

## Renamed Framework Tables
- This module overrides config at boot so every framework-owned table carries an `app_` prefix. Never assume Laravel's default table names in this application.
- `jobs` is `app_jobs`, `failed_jobs` is `app_jobs_failed`, `job_batches` is `app_jobs_batches`, `cache` is `app_cache`, `cache_locks` is `app_cache_locks`, `sessions` is `app_sessions`, `migrations` is `app_migrations`.
- Prefer resolving the name from config over hardcoding it: `config('queue.connections.database.table')`, `config('queue.failed.table')`, `config('queue.batching.table')`, `config('cache.stores.database.table')`, `config('cache.stores.database.lock_table')`, `config('session.table')`, `config('database.migrations.table')`.
- The renames live in `ElStartModule::defineConfigOverride()`. Keep every table rename there rather than editing the application's `config/*.php`, so one file stays the source of truth.
- If there are migrations (usually boundled with Laravel scaffolding), they should be removed from the application code.

## Migrations
- The migrations for these tables ship with this module and are auto-loaded through the `ModuleMigrations` feature. There is nothing to publish — `vendor:publish` is not part of installing them.
- Do not create application-side migrations for `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, or `failed_jobs`. Those tables already exist under their `app_` names, and a second migration fails with "table already exists".
- Remove Laravel's skeleton migrations from the application when adopting this module: `0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`, and the `sessions` block inside `0001_01_01_000000_create_users_table.php`.
- `app_migrations` has no migration file of its own. The migrator creates it from `database.migrations.table`.
- Run migrations with `{{ $assist->artisanCommand('migrate') }}`.

## Config Caching
- The overrides are applied while the service provider boots and are skipped when config is already cached. `{{ $assist->artisanCommand('config:cache') }}` clears first and boots providers against fresh config, so the `app_` names are baked into the cached file.
- Re-run `{{ $assist->artisanCommand('config:cache') }}` after installing or upgrading this module. A cache generated beforehand still holds Laravel's default table names.

## Controller Actions
- Controller Actions is a concept of single method controllers, but have handle() method that will be executed instead of the default `__invoke()` method.
- It's a preferred way to organize new controllers to create them as an action controller.
- Action controllers should be created in the `app/Http/Actions` directory.
- It's reasonable to add a subdirectory to the `app/Http/Actions` directory to organize related action controllers together.
- Action controllers should be created as a single method controller with a `handle()` method.
- When defining a route use ->uses([Action::class, 'handle']) and a proper ->name() method.

## Eloquent Models
- Whenever new eloquent models are created, they should be placed in the `app/Models` directory.
- It's reasonable to add a subdirectory to the `app/Models` directory to organize related models together.
- Each model should have a TABLE constant defined and `protected $table = self::TABLE` property.
- Eloquent models should not rely on magic for table names, they should always be defined explicitly in the TABLE constant.
- Whenever table name is being used (in a migration, query builder, etc.), use the TABLE constant instead of the table name directly.

## Database table naming convention
- The usual naming convention for database tables (with snake_case and plural form of the model name) should not be followed.
- Instead treat table names as directory structure with every subsequent element being a logical subdirectory (e.g. `users`, `users_profiles`, `users_profiles_addresses`, etc.).

## Enums
- All enums should be native PHP enum classes.
- All enums should be placed in the `app/Enums` directory.
- It's reasonable to add a subdirectory to the `app/Enums` directory to organize related enums together.
- Enums should be prefixed with the "E" (e.g. `EUserStatus`, `EUserRole`, etc.).
- Enums should be used instead of string or integer constants wherever possible.
- When creating table column that will cast to enum, use "_eid" suffix (e.g. `user_status_eid`). Especially for numeric ones.

## Helpers
- Helpers are classes that provide utility functions or methods (usually static).
- Helper classes should be placed in the `app/Helpers` directory.
- It's reasonable to add a subdirectory to the `app/Helpers` directory to organize related helpers together.
- Helper classes should be prefixed with the "H" (e.g. `HUser`, etc.).

## Views
- Names of the blade files should be in kebab-case.
- Names of the blade components should be in kebab-case.

## Data Models
- Data models should be placed in the `app/Models` directory.
- It's reasonable to add a subdirectory to the `app/Models` directory to organize related models together.
- Data models should be suffixed with "Model".

## Tokens
- `app_tokens` holds tokens attached to any model through a polymorphic `tokenable` relation. The table and the `Fnp\ElStart\Models\AppToken` model ship with this module — do not create an application migration for them.
- A token carries a type (`type_eid`), a `value`, and an optional `expires_at`. A token without an expiry date never expires.
- The token type enum belongs to the application. Create an integer backed enum in `app/Enums` (e.g. `ETokenType`) that implements `Fnp\ElStart\Contracts\TokenType`.
- Every token method takes the enum case, never the raw integer. Only the column is plain: `type_eid` is stored and read back as an integer and is not cast, so resolve it with `ETokenType::from($token->type_eid)` where the case itself is needed.
- Add the `Fnp\ElStart\Traits\HasTokens` trait to any model that should own tokens.
- Write with `addToken($type, $value = null, $expiresAt = null)` (a random 64 character value is generated when none is given), `removeToken($tokenOrValue, $type = null)`, `removeTokens($type = null)`, and `removeExpiredTokens()`.
- Read with `tokens()`, `token($type)` (newest valid one), `tokenValue($type)`, `findToken($value, $type = null, $validOnly = true)`, `hasToken($type, $value = null)`, `validTokens($type = null)`, and `expiredTokens($type = null)`.
- Expired tokens are skipped by every read helper. Query them explicitly with the `valid()`, `expired()`, `ofType()`, and `withValue()` scopes on `AppToken`.
- When only the token value is known (an incoming link, a request header), resolve it with the `token()` helper, which returns the shared `Fnp\ElStart\Services\TokenService`.
- `token()->find($value, $type = null, $validOnly = true)` returns the `AppToken` record, `token()->findTarget(...)` returns the model it is attached to. Both look across every model and return null when nothing matches.

## Audit
- Any event implementing `Fnp\ElStart\Contracts\Auditable` is recorded in `app_audit` by the listener this module registers on `*`. Nothing else needs wiring: dispatch the event and return the payload from `audit()`.
- Every row records who was there in `user_id`: the id of the logged in user, or null when nobody was — a console command, a queued job, a schedule, anything running as the system.
- Keep secrets out of `audit()`. The payload is stored unencrypted; record identifiers and what changed, never the value it changed to.

## Vault
- The vault encrypts data of any model for the key pairs that may read it. Three tables ship with this module: `app_vault` for the entries, `app_vault_keys` for the key pairs, `app_vault_grants` for who may open what.
- An entry is a single detail of a model, attached through a polymorphic `vaultable` relation: the `detail_eid` column names it, the `value` column holds the ciphertext. One entry per model and detail, so writing the same detail twice replaces the value and keeps the grants.
- The detail enum belongs to the application. Create an integer backed enum in `app/Enums` (e.g. `EVaultDetail`) that implements `Fnp\ElStart\Contracts\VaultDetail`.
- Every vault method takes the enum case, never the raw integer. Only the column is plain: `detail_eid` is stored and read back as an integer and is not cast, so resolve it with `EVaultDetail::from($entry->detail_eid)` where the case itself is needed.
- Case values take part in the key derivation. Renumbering a case makes every entry stored under it unreadable, so treat the numbers as permanent.
- Every model that unlocks the vault owns an X25519 key pair, minted on its first unlock and stored in `app_vault_keys`. The public half is in the clear, the secret half encrypted with a key derived from the password with Argon2id, salted with `user.id` and `user.email` and peppered with the application key.
- Each entry carries a random key of its own. The value is encrypted with it, and that key is sealed once per reader into `app_vault_grants` — to the writer, to the system user, and to anyone the entry is shared with. Any one grant opens it.
- Unlock at login, where the plain password still exists: `vault()->unlock($user, $password)`. A wrong password is refused there, because it fails to open the stored key pair. `vault()->lock()` drops everything, and the module locks the vault on the `Logout` event.
- Add the `Fnp\ElStart\Traits\HasVault` trait to any model that holds encrypted data. Write with `putVault($detail, $value)`, read with `vaultValue($detail, $default = null)`, drop with `removeVault($detail = null)`, and reach the rows with `vault()`, `vaultGrants()` and `vaultKey`.
- The service takes the same operations for any model: `vault()->put($model, $detail, $value)`, `vault()->get($model, $detail, $default)`, `vault()->has()`, `vault()->remove()`, plus `vault()->encrypt()` / `vault()->decrypt()` for payloads that never reach the table — those are sealed to no key pair and do not survive a `rekey()`.
- Share with `shareVault($detail, $reader)` and take it back with `revokeVault($detail, $reader)`. The reader needs a key pair, so it must have unlocked the vault once. Revoking rotates the entry key and re-seals it to everyone left.
- `hasVault($detail)`, `vaultDetails()` and `removeVault()` work while the vault is locked — details and grants are stored in the clear, only the values need a key pair.
- Changing a password or an email address needs `vault()->rekey($user, $newPassword)` while the vault is still unlocked with the old ones. It rewrites one row, the key pair of that user. No entry is touched and there is no list of models to keep.
- The system user is a key pair, not a record. Register it with the `Fnp\ElStart\Features\ModuleVaultSystem` feature on a module — `defineVaultSystem()` returns `public`, `secret` and `unlock`. Give every process the public key and only the console the secret one. `VaultService::generateSystemKeys()` mints a pair.
- `vault()->unlockAsSystem()` opens the vault as the system user, `vault()->isSystem()` reports it, and nothing is written to the session, so system access dies with the process.
- Writes always run as a logged in user: `put()` and `rekey()` throw while unlocked as the system user, because the user could not open what they produced.
- `vault()->recover($user, $newPassword)` is the password reset path and runs only as the system user. It mints a fresh key pair for the user and re-seals every entry the system can open, found through that user's grants. It returns the count; entries never sealed to the system user are left behind.
- Entries written before a system public key was registered have no system grant. The next `put()` on them adds one.
- `VaultService::usePepper($secret)` swaps the application key for a secret of your own, and `VaultService::useDerivationCost($operations, $memory)` sets the Argon2id limits. Both change the derived key, so set them once at boot and keep them stable.
- Anything the current key pair cannot open throws `Fnp\ElStart\Exceptions\VaultException`. Guard the call, or check `vault()->isUnlocked()` first.
- Values go through `json_encode`, so store data that survives a JSON round trip.
- The vault announces itself with five `Auditable` events in `Fnp\ElStart\Events`: `VaultOpened`, `VaultClosed`, `VaultRekeyed`, `VaultUpdated` and `VaultRemoved`. The audit listener of this module records them in `app_audit` without any wiring.
- No vault event and no audit payload ever carries a value, a key or a password — only who was there and which detail of which model changed. Keep it that way in any event you add on top.
- A lock with nothing open and a removal that removed nothing announce nothing, so the audit trail stays free of noise.