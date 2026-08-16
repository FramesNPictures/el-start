<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One line of the audit trail, written by the listener of this module for
 * every dispatched event that implements `Auditable`.
 *
 * Rows are only ever appended, which is why there is no `updated_at`.
 *
 * @property int $id
 * @property string $event Class map alias of the event, or its class name
 * @property int|null $user_id Who was logged in, null for anything running as the system
 * @property array<string, mixed>|null $payload What the event chose to record, never a secret
 * @property Carbon $created_at
 */
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
