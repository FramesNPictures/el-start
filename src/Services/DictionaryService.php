<?php

namespace Fnp\ElStart\Services;

use BackedEnum;
use Fnp\ElModule\Services\ElModuleService;
use Fnp\ElStart\Models\AppDictionary;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionEnum;

/**
 * Keeps a written record of what the numbers in `_eid` columns mean.
 *
 * Modules register the enums worth writing down, and `store()` copies their
 * cases into `app_dictionary`. Nothing reads that table back into PHP — the
 * enums stay the source. It is there for everything that looks at the database
 * without the application in front of it: a report, a query by hand, a tool
 * that only speaks SQL.
 *
 * An enum is written down under its class map alias where it has one, so the
 * dictionary names things the way the rest of the database does.
 */
class DictionaryService
{
    /**
     * The on demand group modules answer with their enums.
     *
     * `ElModuleService` turns it into the method it looks for, so this and
     * `ModuleDictionary::initElStartDictionaryOnDemand()` name the same thing.
     */
    public const ON_DEMAND = 'ElStartDictionary';

    /**
     * The enums to write down, keyed by class so registering twice is once.
     *
     * @var array<class-string<BackedEnum>, class-string<BackedEnum>>
     */
    protected array $registered = [];

    /**
     * Register an integer backed enum to be written down.
     *
     * @param  class-string<BackedEnum>  $enum  Enum to register
     *
     * @throws InvalidArgumentException When it is not an integer backed enum
     */
    public function register(string $enum): void
    {
        if (! enum_exists($enum)) {
            throw new InvalidArgumentException(sprintf('%s is not an enum.', $enum));
        }

        $backing = (new ReflectionEnum($enum))->getBackingType();

        if ((string) $backing !== 'int') {
            throw new InvalidArgumentException(sprintf(
                'The dictionary writes down integer backed enums, and %s is %s.',
                $enum,
                $backing === null ? 'backed by nothing' : 'backed by ' . $backing,
            ));
        }

        $this->registered[$enum] = $enum;
    }

    /**
     * The enums registered so far.
     *
     * @return array<int, class-string<BackedEnum>>
     */
    public function registered(): array
    {
        return array_values($this->registered);
    }

    /**
     * Write down every case of every registered enum.
     *
     * Every module holding an `initElStartDictionaryOnDemand()` is asked for
     * its enums first, so nothing has to be registered ahead of time and
     * nothing is gathered on a request that never gets here.
     *
     * Cases that have gone are dropped, so the table says what the enums say
     * today. Enums nobody registered are left alone entirely.
     *
     * @return int Number of cases written down
     */
    public function store(): int
    {
        app(ElModuleService::class)->initOnDemand(self::ON_DEMAND);

        return DB::transaction(function (): int {
            $stored = 0;

            foreach ($this->registered as $enum) {
                $entity = AppDictionary::entityOf($enum);
                $cases = [];

                foreach ($enum::cases() as $case) {
                    $cases[] = [
                        'entity' => $entity,
                        'name' => Str::kebab($case->name),
                        'value' => $case->value,
                    ];
                }

                AppDictionary::query()->upsert($cases, ['entity', 'name'], ['value']);

                AppDictionary::query()
                    ->ofEnum($enum)
                    ->whereNotIn('name', array_column($cases, 'name'))
                    ->delete();

                $stored += count($cases);
            }

            return $stored;
        });
    }
}
