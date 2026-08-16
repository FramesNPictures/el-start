<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The key pair of a model, minted the first time it unlocks the vault.
 *
 * The public half is in the clear so anything can seal an entry to it. The
 * secret half is encrypted with a key derived from the password of the model,
 * which is why changing that password rewrites this row and nothing else.
 *
 * @property int $id
 * @property string $keyable_type Class map alias of the model it belongs to
 * @property int $keyable_id
 * @property string $public_key Base64 X25519 public key
 * @property string $secret_key Base64 payload, encrypted with the password derived key
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $keyable
 *
 * @method static Builder for(Model $model)
 */
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
