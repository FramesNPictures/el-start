<?php

namespace FNP\ElStart\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * @extends Blueprint
 */
class BlueprintExtra
{
    public function __construct(protected Blueprint $table)
    {
    }

    public static function make(Blueprint $table): BlueprintExtra
    {
        return new BlueprintExtra($table);
    }

    public function __call($name, $arguments): Fluent
    {
        return call_user_func_array([$this->table, $name], $arguments);
    }

    public function binaryUuid(string $name): Fluent
    {
        return $this->table->char($name, 16)
            ->charset('binary');
    }

    public function mySQLGeneratedTimeStamps($precision = 0): void
    {
        $this->table->timestamp('created_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->table->timestamp('updated_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
    }


    public function binaryUniqueUuid(string $name = 'uuid'): Fluent
    {
        return $this->table->char($name, 16)
            ->charset('binary')
            ->default(
                DB::raw("(UUID_TO_BIN(LOWER(CONCAT(" .
                    "LPAD(HEX(ROUND(rand()*POW(2,32))), 8, '0'), '-'," .
                    "LPAD(HEX(ROUND(rand()*POW(2,16))), 4, '0'), '-'," .
                    "LPAD(HEX(ROUND(rand()*POW(2,16))), 4, '0'), '-'," .
                    "LPAD(HEX(ROUND(rand()*POW(2,16))), 4, '0'), '-'," .
                    "LPAD(HEX(ROUND(rand()*POW(2,48))), 12, '0')" .
                    "))))")
            )
            ->unique();
    }
}