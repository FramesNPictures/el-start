<?php

namespace Fnp\ElStart\Traits;

use DateTimeInterface;
use Fnp\ElStart\Contracts\TokenType;
use Fnp\ElStart\Models\AppToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

trait HasTokens
{
    /**
     * Attach a new token to the model.
     *
     * @param  TokenType  $type  Type of the token
     * @param  string|null  $value  Token value, a random one is generated when omitted
     * @param  DateTimeInterface|string|null  $expiresAt  Expiry date, null for a token that never expires
     */
    public function addToken(
        TokenType $type,
        ?string $value = null,
        DateTimeInterface|string|null $expiresAt = null,
    ): AppToken {
        return $this->tokens()->create([
            'type_eid' => $type->value,
            'value' => $value ?? Str::random(64),
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Every token of the model that has already expired.
     *
     * @param  TokenType|null  $type  Type to fetch, all types when omitted
     * @return Collection<int, AppToken>
     */
    public function expiredTokens(?TokenType $type = null): Collection
    {
        return $this->tokens()
            ->expired()
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->get();
    }

    /**
     * Find a token of the model by its value.
     *
     * @param  string  $value  Token value to look up
     * @param  TokenType|null  $type  Limit the lookup to a single type
     * @param  bool  $validOnly  Skip the tokens that have already expired
     */
    public function findToken(string $value, ?TokenType $type = null, bool $validOnly = true): ?AppToken
    {
        return $this->tokens()
            ->withValue($value)
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->when($validOnly, fn (Builder $query) => $query->valid())
            ->latest('id')
            ->first();
    }

    /**
     * Whether the model holds a token of the given type that has not expired.
     *
     * @param  TokenType  $type  Type of the token
     * @param  string|null  $value  Token value to match, any value when omitted
     */
    public function hasToken(TokenType $type, ?string $value = null): bool
    {
        return $this->tokens()
            ->ofType($type)
            ->valid()
            ->when($value, fn (Builder $query, string $tokenValue) => $query->withValue($tokenValue))
            ->exists();
    }

    /**
     * Remove every token of the model that has already expired.
     *
     * @return int Number of removed tokens
     */
    public function removeExpiredTokens(): int
    {
        return $this->tokens()->expired()->delete();
    }

    /**
     * Remove a single token, either by its model or by its value.
     *
     * @param  AppToken|string  $token  Token model or token value
     * @param  TokenType|null  $type  Limit the removal to a single type when matching by value
     * @return int Number of removed tokens
     */
    public function removeToken(AppToken|string $token, ?TokenType $type = null): int
    {
        if ($token instanceof AppToken) {
            return $this->tokens()->whereKey($token->getKey())->delete();
        }

        return $this->tokens()
            ->withValue($token)
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->delete();
    }

    /**
     * Remove every token of the model, optionally limited to a single type.
     *
     * @param  TokenType|null  $type  Type to remove, all types when omitted
     * @return int Number of removed tokens
     */
    public function removeTokens(?TokenType $type = null): int
    {
        return $this->tokens()
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->delete();
    }

    /**
     * The most recent token of the given type that has not expired.
     *
     * @param  TokenType  $type  Type of the token
     */
    public function token(TokenType $type): ?AppToken
    {
        return $this->tokens()
            ->ofType($type)
            ->valid()
            ->latest('id')
            ->first();
    }

    /**
     * All the tokens attached to the model.
     */
    public function tokens(): MorphMany
    {
        return $this->morphMany(AppToken::class, 'tokenable');
    }

    /**
     * The value of the most recent token of the given type that has not expired.
     *
     * @param  TokenType  $type  Type of the token
     */
    public function tokenValue(TokenType $type): ?string
    {
        return $this->token($type)?->value;
    }

    /**
     * Every token of the model that has not expired.
     *
     * @param  TokenType|null  $type  Type to fetch, all types when omitted
     * @return Collection<int, AppToken>
     */
    public function validTokens(?TokenType $type = null): Collection
    {
        return $this->tokens()
            ->valid()
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->get();
    }
}
