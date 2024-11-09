<?php

namespace FNP\ElStart\Console;

use Fnp\ElModule\Services\ElModuleService;
use Illuminate\Console\Command;

class AppRegisterObjectsCommand extends Command
{
    protected $signature = 'app:register:objects';
    protected $description = 'Register all modules and objects';

    public function handle(ElModuleService $moduleService)
    {
        $moduleService->initOnDemand('RegisterObjects');
    }
}