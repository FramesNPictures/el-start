<?php

namespace FNP\ElStart\Models;

use FNP\ElStart\Enums\UserDetailType;
use Illuminate\Database\Eloquent\Model;

class AppUserDetailModel extends Model
{
    const TABLE = 'app_users_details';
    protected $primaryKey = 'user_id';
    protected $table = self::TABLE;
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'type',
        'tab',
        'value',
    ];

    public function casts() {
        return [
            'type' => UserDetailType::class,
        ];
    }
}