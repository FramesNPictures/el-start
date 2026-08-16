<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppUser;

/**
 * What every user event of this module carries: the account it happened to.
 *
 * Never a password and never a token: an audit row says what happened to
 * which account, not what the account is worth to whoever reads it.
 */
abstract class UserEvent implements Auditable
{
    /**
     * @param  AppUser  $user  User it happened to
     */
    public function __construct(
        public readonly AppUser $user,
    ) {}

    /**
     * Which account it happened to, by the two ids that name it.
     */
    public function audit(): array
    {
        return [
            'id' => $this->user->getKey(),
            'uuid' => $this->user->uuid,
        ];
    }
}
