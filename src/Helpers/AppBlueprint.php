<?php

namespace FNP\ElStart\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class AppBlueprint extends Blueprint
{
    public function timestamps($precision = 0): void
    {
        $this->timestamp('created_at', $precision)->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->timestamp('updated_at', $precision)->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
    }

    public function binaryUniqueUuid($name)
    {
        $this->char($name,16)
            ->charset('binary')
            ->default(DB::raw('(UUID_TO_BIN(UUID()))'))
            ->unique();
    }
}