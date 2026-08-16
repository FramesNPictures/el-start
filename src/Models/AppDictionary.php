<?php

namespace Fnp\ElStart\Models;

use Fnp\ElModule\Helpers\HClassMap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One case of one registered enum, written down so the database can say what
 * a number in an `_eid` column means without asking the application.
 *
 * The rows are a copy, not a source: `DictionaryService::store()` writes them
 * from the enums themselves, and nothing reads them back into PHP.
 *
 * @property int $id
 * @property string $entity Class map alias of the enum, or its class name
 * @property string $name Name of the case in kebab-case
 * @property int $value What the case is backed by, as stored in `_eid` columns
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder ofEnum(string $enum)
 */
class AppDictionary extends Model
{
    const TABLE = 'app_dictionary';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity',
        'name',
        'value',
    ];

    protected $table = self::TABLE;

    /**
     * What an enum is written down as: its class map alias where it has one,
     * and its class name where it does not.
     *
     * @param  string  $enum  Class name of the enum
     */
    public static function entityOf(string $enum): string
    {
        return HClassMap::getAlias($enum) ?? $enum;
    }

    /**
     * Limit the query to the cases of a single enum, named either way.
     */
    public function scopeOfEnum(Builder $query, string $enum): void
    {
        $query->where('entity', static::entityOf($enum));
    }
}
