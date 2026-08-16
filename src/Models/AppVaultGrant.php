<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Permission to open one entry, in the only form that means anything: the key
 * of that entry, sealed to the public key of whoever may read it.
 *
 * One row per reader per entry. The system user appears as the `system` type
 * with id zero, since it is a key pair rather than a record.
 *
 * @property int $id
 * @property int $vault_id
 * @property string $keyable_type Class map alias of the reader, or `system`
 * @property int $keyable_id
 * @property string $sealed_key Entry key sealed to that public key, hidden from output
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $keyable
 * @property-read AppVault $vault
 *
 * @method static Builder for(Model $model)
 * @method static Builder forSystem()
 */
class AppVaultGrant extends Model
{
    /**
     * Morph id of the system user.
     */
    const SYSTEM_ID = 0;

    /**
     * Morph type of the system user, which is a key pair rather than a record.
     */
    const SYSTEM_TYPE = 'system';

    const TABLE = 'app_vault_grants';

    /**
     * The attributes hidden from array and JSON output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'sealed_key',
    ];

    protected $table = self::TABLE;

    /**
     * The model the grant was issued to.
     */
    public function keyable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit the query to the grants issued to a single model.
     */
    public function scopeFor(Builder $query, Model $model): void
    {
        $query->where('keyable_type', $model->getMorphClass())
            ->where('keyable_id', $model->getKey());
    }

    /**
     * Limit the query to the grants issued to the system user.
     */
    public function scopeForSystem(Builder $query): void
    {
        $query->where('keyable_type', self::SYSTEM_TYPE)
            ->where('keyable_id', self::SYSTEM_ID);
    }

    /**
     * The entry the grant opens.
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(AppVault::class, 'vault_id');
    }
}
