<?php

namespace FNP\ElStart\Models;

use FNP\ElStart\Casts\BinToHex;
use FNP\ElStart\Casts\BinToUuid;
use Illuminate\Database\Eloquent\Model;

class AppSettingModel extends Model
{
    const TABLE_NAME = 'app_settings';
    protected $table = self::TABLE_NAME;
    protected $fillable = ['key', 'value'];
    public $incrementing = false;
    protected $keyType = 'string';

    public function casts()
    {
        return [
            'uuid' => BinToUuid::class,
            'key' => BinToHex::class,
        ];
    }
}