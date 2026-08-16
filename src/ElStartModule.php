<?php

namespace Fnp\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleClassMap;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleEventListeners;
use Fnp\ElModule\Features\ModuleMigrations;
use Fnp\ElModule\Features\ModuleNamespacedViews;
use Fnp\ElModule\Features\ModuleSingletons;
use Fnp\ElStart\Data\PageModel;
use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Events\UserDeleted;
use Fnp\ElStart\Events\UserEmailChanged;
use Fnp\ElStart\Events\UserEmailVerified;
use Fnp\ElStart\Events\UserLoggedIn;
use Fnp\ElStart\Events\UserLoginFailed;
use Fnp\ElStart\Events\UserPasswordChanged;
use Fnp\ElStart\Events\UserPasswordReset;
use Fnp\ElStart\Events\UserPasswordResetRequested;
use Fnp\ElStart\Events\UserRegistered;
use Fnp\ElStart\Features\ModuleDictionary;
use Fnp\ElStart\Listeners\AuditEventListener;
use Fnp\ElStart\Models\AppUser;
use Fnp\ElStart\Services\DictionaryService;
use Fnp\ElStart\Services\TokenService;
use Fnp\ElStart\Services\UserService;

class ElStartModule extends ElModule
{
    use ModuleClassMap;
    use ModuleConfigOverride;
    use ModuleDictionary;
    use ModuleEventListeners;
    use ModuleMigrations;
    use ModuleNamespacedViews;
    use ModuleSingletons;

    public const VIEW_NAMESPACE = 'el-start';

    public function defineClassMap(): array
    {
        return [
            // Events
            // ------
            // The alias is what `app_audit` records, so it stays put once it
            // has been written to a row. Renaming one rewrites history.
            'user.registered' => UserRegistered::class,
            'user.logged-in' => UserLoggedIn::class,
            'user.login-failed' => UserLoginFailed::class,
            'user.email-changed' => UserEmailChanged::class,
            'user.email-verified' => UserEmailVerified::class,
            'user.password-changed' => UserPasswordChanged::class,
            'user.password-reset-requested' => UserPasswordResetRequested::class,
            'user.password-reset' => UserPasswordReset::class,
            'user.deleted' => UserDeleted::class,

            // Models
            // ------
            'user' => AppUser::class,

            // Enums
            // -----
            // What `app_dictionary` writes them down as, so the table names
            // them the way the rest of the database does.
            'token.type' => ESystemTokenType::class,
        ];
    }

    public function defineConfigOverride(): array
    {
        return [
            'auth.providers.users.model' => AppUser::class,
            'queue.connections.database.table' => 'app_jobs',
            'queue.failed.table' => 'app_jobs_failed',
            'queue.batching.table' => 'app_jobs_batches',
            'cache.stores.database.table' => 'app_cache',
            'cache.stores.database.lock_table' => 'app_cache_locks',
            'database.migrations.table' => 'app_migrations',
            'session.table' => 'app_sessions',
        ];
    }

    /**
     * The enums of this module the database should be able to read.
     */
    public function defineDictionary(): array
    {
        return [
            ESystemTokenType::class,
        ];
    }

    public function defineEventListeners(): array
    {
        return [
            '*' => AuditEventListener::class,
        ];
    }

    public function defineMigrationFolders(): array
    {
        return [
            __DIR__ . '/../database/migrations',
        ];
    }

    public function defineNamespacedViewFolders(): array
    {
        return [
            self::VIEW_NAMESPACE => __DIR__ . '/../resources/views',
        ];
    }

    public function defineSingletons(): array
    {
        return [
            DictionaryService::class => DictionaryService::class,
            PageModel::class => PageModel::class,
            SiteModel::class => SiteModel::class,
            TokenService::class => TokenService::class,
            UserService::class => UserService::class,
        ];
    }
}
