<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Models\AppObjectModel;

class AppObjects
{
    public static function register(string $objectPath): AppObjectModel
    {
        if (!method_exists($objectPath, 'registrationUUID')) {
            throw new \Exception("Class {$objectPath} must implement Registerable interface.");
        }

        $record = AppObjectModel::updateOrCreate(
            ['uuid' => $objectPath::registrationUUID()],
            ['uuid' => $objectPath::registrationUUID(), 'class' => $objectPath],
        );

        return $record;
    }
}