<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AppVaultKey extends Model
{
    const TABLE = 'app_vault_keys';

    /**
     * The attributes hidden from array and JSON output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'secret_key',
    ];

    protected $table = self::TABLE;

    /**
     * The data the secret key is bound to as associated data. It holds no
     * credential of the owner, so a password or an email address may change
     * without touching the key pair.
     */
    public function context(): string
    {
        return implode('|', [$this->keyable_type, $this->keyable_id]);
    }

    /**
     * The model the key pair belongs to.
     */
    public function keyable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Limit the query to the key pair of a single model.
     */
    public function scopeFor(Builder $query, Model $model): void
    {
        $query->where('keyable_type', $model->getMorphClass())
            ->where('keyable_id', $model->getKey());
    }
}
