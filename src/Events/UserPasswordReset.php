<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppUser;

class UserPasswordReset extends UserEvent
{
    /**
     * @param  AppUser  $user  User that got a new password
     * @param  bool  $recovered  Whether the system user handed their vault over
     */
    public function __construct(
        AppUser $user,
        public readonly bool $recovered = false,
    ) {
        parent::__construct($user);
    }

    /**
     * Whether the entries of the user came along. A reset that did not
     * recover them leaves a vault nobody can open behind, which is worth
     * being able to find later.
     */
    public function audit(): array
    {
        return [
            ...parent::audit(),
            'recovered' => $this->recovered,
        ];
    }
}
