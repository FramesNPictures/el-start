<?php

namespace FNP\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleMigrations;

class StartModule extends ElModule
{
    use ModuleConfigOverride;
    use ModuleMigrations;

    public function defineConfigOverride(): array
    {
        return [
            'queue.connections.database.table' => 'app_jobs',
            'queue.failed.table'               => 'app_jobs_failed',
            'queue.batching.table'             => 'app_jobs_batches',
            'cache.stores.database.table'      => 'app_cache',
            'cache.stores.database.lock_table' => 'app_cache_locks',
        ];
    }

    public function defineMigrationFolders(): array
    {
        return [
            __DIR__.'/../migrations',
        ];
    }
}