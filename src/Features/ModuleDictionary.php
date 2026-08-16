<?php

namespace Fnp\ElStart\Features;

use BackedEnum;
use Fnp\ElStart\Services\DictionaryService;

trait ModuleDictionary
{
    /**
     * Returns the integer backed enums of this module worth writing down, so
     * the database can say what the numbers in its `_eid` columns mean.
     *
     * @return array<int, class-string<BackedEnum>>
     */
    abstract public function defineDictionary(): array;

    /**
     * Registers them, and only when somebody asks: nothing here runs at boot.
     * `DictionaryService::store()` calls every module holding this method
     * through `ElModuleService::initOnDemand()`, so the enums are gathered at
     * the moment they are about to be written and never before.
     *
     * The name is what the group `DictionaryService::ON_DEMAND` resolves to,
     * so the two have to change together.
     */
    public function initElStartDictionaryOnDemand(DictionaryService $dictionary): void
    {
        foreach ($this->defineDictionary() as $enum) {
            $dictionary->register($enum);
        }
    }
}
