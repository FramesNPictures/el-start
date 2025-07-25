<?php

namespace FNP\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleConsoleCommands;
use Fnp\ElModule\Features\ModuleMigrations;
use Fnp\ElModule\Features\ModuleRoutesWeb;
use FNP\ElStart\Console\AppRegisterObjectsCommand;
use FNP\ElStart\Console\AppSettingCommand;

class ElStartModule extends ElModule
{
    use ModuleConfigOverride;
    use ModuleConsoleCommands;
    use ModuleMigrations;
    use ModuleRoutesWeb;

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
            'auth.passwords.users.table' => 'app_auth_resets',
        ];
    }

    public function defineMigrationFolders(): array
    {
        return [
            __DIR__.'/../database/migrations',
        ];
    }

    public function defineConsoleCommands(): array
    {
        return [
            AppSettingCommand::class,
            AppRegisterObjectsCommand::class,
        ];
    }
}
