@php
    /** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# El Start

- `framesnpictures/el-start` is an opinionated Laravel start module built on `framesnpictures/el-module`. It renames Laravel's framework-owned tables and ships the migrations that create them.

## Renamed Framework Tables
- This module overrides config at boot so every framework-owned table carries an `app_` prefix. Never assume Laravel's default table names in this application.
- `jobs` is `app_jobs`, `failed_jobs` is `app_jobs_failed`, `job_batches` is `app_jobs_batches`, `cache` is `app_cache`, `cache_locks` is `app_cache_locks`, `sessions` is `app_sessions`, `migrations` is `app_migrations`, `users` is `app_users`. `password_reset_tokens` has no counterpart: those tokens live in `app_tokens`.
- Prefer resolving the name from config over hardcoding it: `config('queue.connections.database.table')`, `config('queue.failed.table')`, `config('queue.batching.table')`, `config('cache.stores.database.table')`, `config('cache.stores.database.lock_table')`, `config('session.table')`, `config('database.migrations.table')`.
- The renames live in `ElStartModule::defineConfigOverride()`. Keep every table rename there rather than editing the application's `config/*.php`, so one file stays the source of truth.
- If there are migrations (usually boundled with Laravel scaffolding), they should be removed from the application code.

## Migrations
- The migrations for these tables ship with this module and are auto-loaded through the `ModuleMigrations` feature. There is nothing to publish — `vendor:publish` is not part of installing them.
- Do not create application-side migrations for `users`, `cache`, `cache_locks`, `sessions`, `jobs`, `job_batches`, or `failed_jobs`. Those tables already exist under their `app_` names, and a second migration fails with "table already exists". `password_reset_tokens` needs no table at all.
- Remove Laravel's skeleton migrations from the application when adopting this module: `0001_01_01_000000_create_users_table.php` in full — users, password reset tokens and sessions all ship here — along with `0001_01_01_000001_create_cache_table.php` and `0001_01_01_000002_create_jobs_table.php`.
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
- Enums owned by this module are prefixed with "ESystem" (e.g. `ESystemTokenType`) and their cases are numbered from 100000 up, so the enums of an application, numbered from one, never collide with them in a shared column.
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

## Dictionary
- `app_dictionary` writes down what the numbers in `_eid` columns mean, one row per enum case: the `entity` the enum is known as, the `name` of the case in kebab-case, and the `value` it is backed by.
- `entity` is the class map alias of the enum where it has one — the enum of this module is written down as `token.type` — and its class name where it has none. Give an enum an alias in `defineClassMap()` and the dictionary names it the way the rest of the database does.
- Query it with `AppDictionary::ofEnum(ETokenType::class)`, which resolves the alias itself, so nothing has to know which of the two is in the column.
- It is a copy, never a source. The enums stay authoritative and nothing reads the table back into PHP — it is there for whatever looks at the database without the application in front of it: a report, a query by hand, a tool that only speaks SQL.
- Add the `Fnp\ElStart\Features\ModuleDictionary` feature to a module and return its integer backed enums from `defineDictionary()`. Anything else is refused when the enums are gathered.
- Nothing of this runs at boot. The feature answers the `ElStartDictionary` on demand group, so `store()` is what asks every module for its enums, through `ElModuleService::initOnDemand()`. A request that never writes the dictionary never pays for it.
- A migration of this module writes the dictionary once, so a fresh database can answer for its own columns from the start. Migrations run once and enums keep changing, so store it again whenever they do.
- `app(DictionaryService::class)->store()` is what writes the rows — from a deploy step, a command, a seeder, or an application migration holding that one line. It is safe to run again: rows are upserted, and a case that no longer exists is dropped.
- Only registered enums are touched, so rows belonging to anything else are left where they are.

## Users
- `Fnp\ElStart\Models\AppUser` replaces the `App\Models\User` Laravel scaffolds, and `auth.providers.users.model` is overridden to point at it. Delete the scaffolded model rather than keeping two.
- It is a plain `Authenticatable` with `Notifiable`, the `hashed` password cast and the `HasTokens` trait of this module already on it.
- `app_users` carries `name` and `email` columns. Addresses are lowercased and trimmed on the way in, so one address is one account however it was typed.
- Look a user up with `app(UserService::class)->findByEmail($address)`, which normalises before it queries. Guards take the ordinary `['email' => $address, 'password' => $password]`.
- Every address a user had before is kept in `app_users_emails`, a row each with the `until` it stopped being theirs. Read them oldest first with `$user->previousEmails`, and reach the account of a row with `$row->user`.
- Nothing logs in through an old address: the `email` column is what a guard checks. The rows are there so a request naming an address someone used to have still finds the account.
- `changeEmail($user, $email)` moves the user and writes the row for the address they came off. Moving them to the address they already have does nothing and announces nothing.
- **There is no `remember_token` column and no password reset table.** Both live in `app_tokens` under `Fnp\ElStart\Enums\ESystemTokenType`, alongside every other token of the user.
- Remember me works through `getRememberToken()` and `setRememberToken()` on the model, which read and write `app_tokens` — one token per user, replaced on every login, exactly as the column behaved. Nothing in the guard changes.
- Change a password with `changePassword($user, $password)`, which drops every remember me token, so a browser left signed in elsewhere has to sign in again.
- Reset a forgotten one with `startPasswordReset($user)` — it returns the token in the clear for the notification to carry and stores only its digest — and then `resetPassword($token, $password)`, which spends the token and returns the user, or null when it is unknown or expired.
- Laravel's password broker is not wired up: it keeps addresses in a table of its own. Use `UserService`, and send the notification from the application with the token it hands back.
- Extend it when the application needs columns or behaviour of its own — `class User extends AppUser` — and point `auth.providers.users.model` at the subclass from the application module's `defineConfigOverride()`, which boots after this one.
- Add columns with an application migration that alters `app_users`, never with a second create migration.
- `AppUser::factory()` makes users with a name and an address nobody else has. States are `withEmail()`, `withName()`, `verified()` and `unverified()`, and `AppUserFactory::$password` is the password they all get.
- A subclass with a factory of its own should extend `AppUserFactory` and set `$model`.
- Users are soft deleted, so `app_users` carries `deleted_at` and every query hides the deleted ones. Reach for `withTrashed()` where they have to show up, and remember that a soft deleted user cannot log in.
- `Fnp\ElStart\Services\UserService` covers the moments a user record and a session meet: `register()`, `login()`, `findByEmail()`, `changeEmail()`, `changePassword()`, `verifyEmail()`, `startPasswordReset()`, `resetPassword()` and `delete()`. Resolve it with `app(UserService::class)`.
- `verifyEmail($user)` marks the address confirmed and returns whether that was the moment it happened — a user who is verified already is left alone and announces nothing.
- `register()` mass assigns, so further columns have to be fillable on the model. It leaves the user logged out and fires `Illuminate\Auth\Events\Registered` as well, so mail verification and anything else listening for it keeps working.
- Every state change announces itself with an `Auditable` event in `Fnp\ElStart\Events`: `UserRegistered`, `UserLoggedIn`, `UserLoginFailed`, `UserEmailChanged`, `UserEmailVerified`, `UserPasswordChanged`, `UserPasswordResetRequested`, `UserPasswordReset` and `UserDeleted`. The audit listener records them in `app_audit` without any wiring.
- They carry the account by its id and uuid. `UserEmailChanged` records both addresses and `UserLoginFailed` the one that was tried — never a password, never a token.
- `login()` regenerates the session id, and `delete()` ends the session when users delete themselves. Tokens and the addresses they had survive a soft delete, because the record can come back — drop them explicitly where a deleted account has to lose its access at once.

## Audit
- Any event implementing `Fnp\ElStart\Contracts\Auditable` is recorded in `app_audit` by the listener this module registers on `*`. Nothing else needs wiring: dispatch the event and return the payload from `audit()`.
- The `event` column holds the class map alias where there is one, so the trail reads as `user.registered` rather than a namespace. Query it by alias, and filter a whole area with `where('event', 'like', 'user.%')`.
- Every event of this module has an alias in `ElStartModule::defineClassMap()`, and a test fails if one is added without. Give an application event an alias there too, or it lands in the trail as a fully qualified class name.
- An alias is written into rows, so it is permanent. Renaming one rewrites what history says happened.
- An entry that cannot be written never breaks the dispatch that caused it. It goes to the log at debug level with a `[APP] ` prefix, naming the event and carrying the exception — and never the payload, which belongs in the table rather than in a log file.
- Every row records who was there in `user_id`: the id of the logged in user, or null when nobody was — a console command, a queued job, a schedule, anything running as the system.
- Keep secrets out of `audit()`. The payload is stored unencrypted; record identifiers and what changed, never the value it changed to.
