<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElModule\ElModule;
use Fnp\ElStart\Features\ModuleDictionary;

class DictionaryStubModule extends ElModule
{
    use ModuleDictionary;

    /**
     * @var array<int, string>
     */
    public static array $enums = [];

    public function defineDictionary(): array
    {
        return static::$enums;
    }
}
