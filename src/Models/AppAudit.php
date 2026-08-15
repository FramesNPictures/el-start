<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Model;

class AppAudit extends Model
{
    const TABLE = 'app_audit';
    const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'event',
        'user_id',
        'payload',
    ];

    protected $table = self::TABLE;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
