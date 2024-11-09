<?php

namespace FNP\ElStart\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * @extends Blueprint
 */
class AppBlueprint
{
    public function __construct(protected Blueprint $table)
    {
    }

    public static function make(Blueprint $table): AppBlueprint
    {
        return new AppBlueprint($table);
    }

    public function __call($name, $arguments)
    {
        call_user_func_array([$this->table, $name], $arguments);
    }

    public function timestamps($precision = 0): void
    {
        $this->table->timestamp('created_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->table->timestamp('updated_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
    }

    public function binaryUniqueUuid($name)
    {
        $this->table->char($name, 16)
            ->charset('binary')
            ->default(DB::raw('(UUID_TO_BIN(UUID()))'))
            ->unique();
    }
}