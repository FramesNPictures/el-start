<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppUser;

class UserLoggedIn extends UserEvent
{
    /**
     * @param  AppUser  $user  User that logged in
     * @param  bool  $remember  Whether a remember me token was issued
     * @param  bool  $vaultUnlocked  Whether their vault opened with the password
     */
    public function __construct(
        AppUser $user,
        public readonly bool $remember = false,
        public readonly bool $vaultUnlocked = true,
    ) {
        parent::__construct($user);
    }

    public function audit(): array
    {
        return [
            ...parent::audit(),
            'remember' => $this->remember,
            'vault_unlocked' => $this->vaultUnlocked,
        ];
    }
}
