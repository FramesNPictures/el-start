<?php

namespace FNP\ElStart\Console;

use FNP\ElStart\Helpers\AppSettings;
use Illuminate\Console\Command;

class AppSettingCommand extends Command
{
    protected $signature = 'app:setting {key} {value?}';
    protected $description = 'Set or get application setting';

    public function handle()
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        if ($value) {
            AppSettings::set($key, $value);
            $this->info('Setting saved');
        } else {
            echo AppSettings::get($key). PHP_EOL;
        }
    }
}