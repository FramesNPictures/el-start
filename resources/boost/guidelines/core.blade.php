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