<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppUser;

/**
 * What every user event of this module carries: the account it happened to.
 *
 * Never the name, never the address, never a password and never a token — the
 * name and the address are in the vault precisely so they stay out of a table
 * like `app_audit`.
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
