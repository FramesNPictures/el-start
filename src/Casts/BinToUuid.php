<?php

namespace FNP\ElStart\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Ramsey\Uuid\Uuid;

class BinToUuid implements Castable
{
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes)
            {
                return Uuid::fromBytes($value)->toString();
            }

            public function set($model, string $key, $value, array $attributes)
            {
                return Uuid::fromString($value)->getBytes();
            }
        };
    }
}
