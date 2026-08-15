<?php

namespace Fnp\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleEventListeners;
use Fnp\ElModule\Features\ModuleMigrations;
use Fnp\ElModule\Features\ModuleNamespacedViews;
use Fnp\ElModule\Features\ModuleSingletons;
use Fnp\ElStart\Data\PageModel;
use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Listeners\AuditEventListener;
use Fnp\ElStart\Listeners\VaultLogoutListener;
use Fnp\ElStart\Services\TokenService;
use Fnp\ElStart\Services\VaultService;
use Illuminate\Auth\Events\Logout;

class ElStartModule extends ElModule
{
    use ModuleConfigOverride;
    use ModuleEventListeners;
    use ModuleMigrations;
    use ModuleNamespacedViews;
    use ModuleSingletons;

    public const VIEW_NAMESPACE = 'el-start';

    public function defineConfigOverride(): array
    {
        return [
            'queue.connections.database.table' => 'app_jobs',
            'queue.failed.table' => 'app_jobs_failed',
            'queue.batching.table' => 'app_jobs_batches',
            'cache.stores.database.table' => 'app_cache',
            'cache.stores.database.lock_table' => 'app_cache_locks',
            'database.migrations.table' => 'app_migrations',
            'session.table' => 'app_sessions',
        ];
    }

    public function defineEventListeners(): array
    {
        return [
            '*' => AuditEventListener::class,
            Logout::class => VaultLogoutListener::class,
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
            PageModel::class => PageModel::class,
            SiteModel::class => SiteModel::class,
            TokenService::class => TokenService::class,
            VaultService::class => VaultService::class,
        ];
    }
}
