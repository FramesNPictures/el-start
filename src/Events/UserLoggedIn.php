<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppUser;

class UserLoggedIn extends UserEvent
{
    /**
     * @param  AppUser  $user  User that logged in
     * @param  bool  $remember  Whether a remember me token was issued
     */
    public function __construct(
        AppUser $user,
        public readonly bool $remember = false,
    ) {
        parent::__construct($user);
    }

    public function audit(): array
    {
        return [
            ...parent::audit(),
            'remember' => $this->remember,
        ];
    }
}
