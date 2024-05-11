<?php

namespace FNP\ElStart\Helpers;

use FNP\ElStart\Enums\I10Lang;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Model;

class I10String implements Castable, Jsonable
{
    protected array   $elements = [];
    protected I10Lang $default;

    public function __construct(?string $raw = '')
    {
        $this->default = I10Lang::active();

        if (is_null($raw)) {
            $raw = '';
        }

        if (!empty($raw)) {
            $this->elements = json_decode($raw, true);
        }
    }

    public function set(I10Lang $lang, string $value): self
    {
        $this->elements[$lang->name] = $value;
        return $this;
    }

    public function get(I10Lang $lang): ?string
    {
        if (!isset($this->elements[$lang->name])) {
            return null;
        }

        return $this->elements[$lang->name];
    }

    public function __toString(): string
    {
        return $this->get($this->default) ?: '';
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->elements, $options);
    }

    public function getFallback(I10Lang $lang): ?string
    {
        if (!isset($this->elements[$lang->name]) &&
            !isset($this->elements[$this->default->name])) {
            return null;
        }

        if (!isset($this->elements[$lang->name])) {
            return $this->elements[$this->default->name];
        }

        return $this->elements[$lang->name];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes {
            public function get(Model $model, string $key, mixed $value, array $attributes): I10String
            {
                return new I10String($value);
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): string
            {
                return $value->toJson();
            }
        };
    }
}
