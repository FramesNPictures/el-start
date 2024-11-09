<?php

namespace FNP\ElStart\Helpers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

/**
 * @method \Illuminate\Support\Fluent bigIncrements(string $column)
 * @method \Illuminate\Support\Fluent bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
 * @method \Illuminate\Support\Fluent binary(string $column)
 * @method \Illuminate\Support\Fluent boolean(string $column)
 * @method \Illuminate\Support\Fluent char(string $column, int $length = 255)
 * @method \Illuminate\Support\Fluent date(string $column)
 * @method \Illuminate\Support\Fluent dateTime(string $column, $precision = 0)
 * @method \Illuminate\Support\Fluent dateTimeTz(string $column, $precision = 0)
 * @method \Illuminate\Support\Fluent decimal(string $column, int $total, int $places)
 * @method \Illuminate\Support\Fluent double(string $column, int $total = 8, int $places = 2)
 * @method \Illuminate\Support\Fluent enum(string $column, array $allowed)
 * @method \Illuminate\Support\Fluent float(string $column, int $total = 8, int $places = 2)
 * @method \Illuminate\Support\Fluent foreignId(string $column)
 * @method \Illuminate\Support\Fluent geometry(string $column)
 * @method \Illuminate\Support\Fluent geometryCollection(string $column)
 * @method \Illuminate\Support\Fluent increments(string $column)
 * @method \Illuminate\Support\Fluent integer(string $column, bool $autoIncrement = false, bool $unsigned = false)
 * @method \Illuminate\Support\Fluent ipAddress(string $column)
 * @method \Illuminate\Support\Fluent json(string $column)
 * @method \Illuminate\Support\Fluent jsonb(string $column)
 * @method \Illuminate\Support\Fluent lineString(string $column)
 * @method \Illuminate\Support\Fluent longText(string $column)
 * @method \Illuminate\Support\Fluent macAddress(string $column)
 * @method \Illuminate\Support\Fluent mediumIncrements(string $column)
 * @method \Illuminate\Support\Fluent mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
 * @method \Illuminate\Support\Fluent mediumText(string $column)
 * @method \Illuminate\Support\Fluent morphs(string $name, string $indexName = null)
 * @method \Illuminate\Support\Fluent multiLineString(string $column)
 * @method \Illuminate\Support\Fluent multiPoint(string $column)
 * @method \Illuminate\Support\Fluent multiPolygon(string $column)
 * @method \Illuminate\Support\Fluent nullableMorphs(string $name, string $indexName = null)
 * @method \Illuminate\Support\Fluent nullableTimestamps(int $precision = 0)
 * @method \Illuminate\Support\Fluent point(string $column, int $srid = null)
 * @method \Illuminate\Support\Fluent polygon(string $column)
 * @method \Illuminate\Support\Fluent rememberToken()
 * @method \Illuminate\Support\Fluent set(string $column, array $values)
 * @method \Illuminate\Support\Fluent smallIncrements(string $column)
 * @method \Illuminate\Support\Fluent smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
 * @method \Illuminate\Support\Fluent softDeletes(string $column = 'deleted_at', int $precision = 0)
 * @method \Illuminate\Support\Fluent softDeletesTz(string $column = 'deleted_at', int $precision = 0)
 * @method \Illuminate\Support\Fluent string(string $column, int $length = 255)
 * @method \Illuminate\Support\Fluent text(string $column)
 * @method \Illuminate\Support\Fluent time(string $column, int $precision = 0)
 * @method \Illuminate\Support\Fluent timeTz(string $column, int $precision = 0)
 * @method \Illuminate\Support\Fluent timestamp(string $column, int $precision = 0)
 * @method \Illuminate\Support\Fluent timestampTz(string $column, int $precision = 0)
 * @method \Illuminate\Support\Fluent timestampsTz(int $precision = 0)
 * @method \Illuminate\Support\Fluent tinyIncrements(string $column)
 * @method \Illuminate\Support\Fluent tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false)
 * @method \Illuminate\Support\Fluent unsignedBigInteger(string $column, bool $autoIncrement = false)
 * @method \Illuminate\Support\Fluent unsignedDecimal(string $column, int $total, int $places)
 * @method \Illuminate\Support\Fluent unsignedInteger(string $column, bool $autoIncrement = false)
 * @method \Illuminate\Support\Fluent unsignedMediumInteger(string $column, bool $autoIncrement = false)
 * @method \Illuminate\Support\Fluent unsignedSmallInteger(string $column, bool $autoIncrement = false)
 * @method \Illuminate\Support\Fluent unsignedTinyInteger(string $column, bool $autoIncrement = false)
 * @method \Illuminate\Support\Fluent uuid(string $column)
 * @method \Illuminate\Support\Fluent year(string $column)
 */
class AppBlueprint
{
    public function __construct(protected Blueprint $table)
    {
    }

    public static function make(Blueprint $table): AppBlueprint
    {
        return new AppBlueprint($table);
    }

    public function __call($name, $arguments)
    {
        call_user_func_array([$this->table, $name], $arguments);
    }

    public function timestamps($precision = 0): void
    {
        $this->table->timestamp('created_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP'));
        $this->table->timestamp('updated_at', $precision)
            ->index()
            ->default(DB::raw('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'));
    }

    public function binaryUniqueUuid($name)
    {
        $this->table->char($name, 16)
            ->charset('binary')
            ->default(DB::raw('(UUID_TO_BIN(UUID()))'))
            ->unique();
    }
}