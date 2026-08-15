<?php

namespace Fnp\ElStart\Models;

use Fnp\ElStart\Contracts\TokenType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

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
     * Application enum the `type_eid` column is cast to.
     *
     * @var class-string<TokenType>|null
     */
    protected static ?string $tokenTypes = null;

    /**
     * Register the application enum describing the token types.
     *
     * @param  class-string<TokenType>|null  $enum  Integer backed enum, null to store raw integers
     */
    public static function useTokenTypes(?string $enum): void
    {
        static::$tokenTypes = $enum;
    }

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
        $casts = ['expires_at' => 'datetime'];

        if (static::$tokenTypes !== null) {
            $casts['type_eid'] = static::$tokenTypes;
        }

        return $casts;
    }
}
