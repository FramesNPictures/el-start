<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Casts\BinToUuid;
use FNP\ElStart\Models\AppObjectModel;

class AppObjects
{
    public static function register(string $objectPath): AppObjectModel
    {
        $record = AppObjectModel::updateOrCreate(
            ['class' => $objectPath],
            ['class' => $objectPath],
        );

        return $record;
    }
}