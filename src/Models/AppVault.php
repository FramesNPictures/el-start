<?php

namespace Fnp\ElStart\Models;

use Fnp\ElStart\Contracts\VaultDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One detail of one model, encrypted with a key of its own. That key is sealed
 * to every reader in `app_vault_grants`, so the row on its own opens to nobody.
 *
 * @property int $id
 * @property string $vaultable_type Class map alias of the model it belongs to
 * @property int $vaultable_id
 * @property int $detail_eid Case value of the vault detail enum, never cast
 * @property string $value The ciphertext, hidden from array and JSON output
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $vaultable
 * @property-read Collection<int, AppVaultGrant> $grants
 *
 * @method static Builder for(Model $model)
 * @method static Builder ofDetail(VaultDetail $detail)
 */
class AppVault extends Model
{
    const TABLE = 'app_vault';

    /**
     * The attributes hidden from array and JSON output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'value',
    ];

    protected $table = self::TABLE;

    /**
     * The data the ciphertext is bound to, passed to the cipher as associated
     * data so an entry cannot be moved to another model or another detail.
     */
    public function context(): string
    {
        return implode('|', [$this->vaultable_type, $this->vaultable_id, $this->detailValue()]);
    }

    /**
     * The stored detail, always a plain integer.
     */
    public function detailValue(): int
    {
        return (int) $this->detail_eid;
    }

    /**
     * The keys of the entry, one per model that may open it.
     */
    public function grants(): HasMany
    {
        return $this->hasMany(AppVaultGrant::class, 'vault_id');
    }

    /**
     * Limit the query to the entries of a single model.
     */
    public function scopeFor(Builder $query, Model $model): void
    {
        $query->where('vaultable_type', $model->getMorphClass())
            ->where('vaultable_id', $model->getKey());
    }

    /**
     * Limit the query to a single detail.
     */
    public function scopeOfDetail(Builder $query, VaultDetail $detail): void
    {
        $query->where('detail_eid', $detail->value);
    }

    /**
     * The model the entry is attached to.
     */
    public function vaultable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Drop the keys of an entry with the entry itself, whether or not the
     * database enforces the foreign key.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $entry): void {
            $entry->grants()->delete();
        });
    }
}
