<?php

namespace Fnp\ElStart\Models;

use Fnp\ElStart\Contracts\TokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A token attached to any model through a polymorphic relation: an api key, a
 * remember me token, the digest of a password reset, whatever the application
 * numbers in its own `TokenType` enum.
 *
 * @property int $id
 * @property string $tokenable_type Class map alias of the model it belongs to
 * @property int $tokenable_id
 * @property int $type_eid Case value of the token type enum, never cast
 * @property string $value
 * @property Carbon|null $expires_at Null for a token that never expires
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model|null $tokenable
 *
 * @method static Builder expired()
 * @method static Builder ofType(TokenType $type)
 * @method static Builder valid()
 * @method static Builder withValue(string $value)
 */
class AppToken extends Model
{
    const TABLE = 'app_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type_eid',
        'value',
        'expires_at',
    ];

    protected $table = self::TABLE;

    /**
     * Whether the token carries an expiry date that has already passed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Whether the token never expires or has not expired yet.
     */
    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Limit the query to the tokens that have already expired.
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now());
        });
    }

    /**
     * Limit the query to a single token type.
     */
    public function scopeOfType(Builder $query, TokenType $type): void
    {
        $query->where('type_eid', $type->value);
    }

    /**
     * Limit the query to the tokens that have not expired.
     */
    public function scopeValid(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', Carbon::now());
        });
    }

    /**
     * Limit the query to the tokens carrying the given value.
     */
    public function scopeWithValue(Builder $query, string $value): void
    {
        $query->where('value', $value);
    }

    /**
     * The model the token is attached to.
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}
