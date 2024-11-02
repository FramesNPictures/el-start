<?php

namespace FNP\ElStart\Models;

use FNP\ElStart\Casts\BinToUuid;
use Illuminate\Database\Eloquent\Model;

class AppObjectModel extends Model
{
    const TABLE = 'app_objects';
    protected $table = self::TABLE;
    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'uuid',
        'class',
    ];

    public function casts(): array
    {
        return [
            'uuid' => BinToUuid::class,
        ];
    }

    public function theInstance()
    {
        return app($this->class);
    }
}