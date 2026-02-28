<?php

namespace Fnp\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleMigrations;
use Fnp\ElModule\Features\ModuleRoutesWeb;
use Fnp\ElStart\Models\DAppUser;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

class ElStartModule extends ElModule
{
    use ModuleConfigOverride;
    use ModuleMigrations;

    public function defineConfigOverride(): array
    {
        return [
            'queue.connections.database.table' => 'app_jobs',
            'queue.failed.table' => 'app_jobs_failed',
            'queue.batching.table' => 'app_jobs_batches',
            'cache.stores.database.table' => 'app_cache',
            'cache.stores.database.lock_table' => 'app_cache_locks',
            'database.migrations.table' => 'app_migrations',
            'auth.providers.users.model' => DAppUser::class,
            'session.table' => 'app_sessions',
        ];
    }

    public function defineMigrationFolders(): array
    {
        return [
            __DIR__ . '/../database/migrations',
        ];
    }
}
