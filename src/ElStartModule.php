<?php

namespace FNP\ElStart;

use Fnp\ElModule\ElModule;
use Fnp\ElModule\Features\ModuleConfigOverride;
use Fnp\ElModule\Features\ModuleConsoleCommands;
use Fnp\ElModule\Features\ModuleMigrations;
use Fnp\ElModule\Features\ModuleRoutesWeb;
use FNP\ElStart\Console\AppRegisterObjectsCommand;
use FNP\ElStart\Console\AppSettingCommand;
use FNP\ElStart\Models\AppUserModel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

class ElStartModule extends ElModule
{
    use ModuleConfigOverride;
    use ModuleMigrations;
    use ModuleRoutesWeb;
    use ModuleConsoleCommands;

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
            'auth.passwords.users.table' => 'app_passwords_resets',
        ];
    }

    public function defineMigrationFolders(): array
    {
        return [
            __DIR__ . '/../database/migrations',
        ];
    }

    public function defineWebRoutes(Router $router): void
    {
        // Resend email verification
        $router->post('/app/email/verify', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('message', 'Verification link sent!');
        })->middleware(['auth', 'throttle:6,1'])->name('verification.send');

        // Verify Email
        $router->get('/app/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return redirect('/app');
        })->middleware(['auth', 'signed'])->name('verification.verify');
    }

    public function defineConsoleCommands(): array
    {
        return [
            AppSettingCommand::class,
            AppRegisterObjectsCommand::class,
        ];
    }
}
