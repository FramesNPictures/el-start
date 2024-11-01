<?php

namespace FNP\ElStart\Casts;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BinToHex implements Castable
{
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes)
            {
                return bin2hex($value);
            }

            public function set($model, string $key, $value, array $attributes)
            {
                return hex2bin($value);
            }
        };
    }
}
