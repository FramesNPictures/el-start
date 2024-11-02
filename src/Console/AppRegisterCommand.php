<?php

namespace FNP\ElStart\Console;

use Fnp\ElModule\Services\ElModuleService;

class AppRegisterCommand
{
    protected $signature = 'app:register';
    protected $description = 'Register all modules and objects';

    public function handle(ElModuleService $moduleService)
    {
        $moduleService->initOnDemand('RegisterObjects');
    }
}