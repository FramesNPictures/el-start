<?php

namespace Fnp\ElStart\Services;

use Fnp\ElStart\Contracts\TokenType;
use Fnp\ElStart\Models\AppToken;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TokenService
{
    /**
     * Find a token by its value, no matter which model it belongs to.
     *
     * @param  string  $value  Token value to look up
     * @param  TokenType|null  $type  Limit the lookup to a single type
     * @param  bool  $validOnly  Skip the tokens that have already expired
     */
    public function find(string $value, ?TokenType $type = null, bool $validOnly = true): ?AppToken
    {
        return AppToken::query()
            ->withValue($value)
            ->when($type, fn (Builder $query, TokenType $tokenType) => $query->ofType($tokenType))
            ->when($validOnly, fn (Builder $query) => $query->valid())
            ->latest('id')
            ->first();
    }

    /**
     * Find the model a token is attached to.
     *
     * @param  string  $value  Token value to look up
     * @param  TokenType|null  $type  Limit the lookup to a single type
     * @param  bool  $validOnly  Skip the tokens that have already expired
     */
    public function findTarget(string $value, ?TokenType $type = null, bool $validOnly = true): ?Model
    {
        return $this->find($value, $type, $validOnly)?->tokenable;
    }
}
