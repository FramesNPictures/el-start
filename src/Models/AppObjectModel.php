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

    public function casts(): array
    {
        return [
            'uuid' => BinToUuid::class,
        ];
    }
}