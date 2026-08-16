<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppUser;

class UserEmailChanged extends UserEvent
{
    /**
     * @param  AppUser  $user  User that was renamed
     * @param  string  $from  Address it was
     * @param  string  $to  Address it is
     */
    public function __construct(
        AppUser $user,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct($user);
    }

    /**
     * Both addresses, which is the whole of what changed.
     */
    public function audit(): array
    {
        return [
            ...parent::audit(),
            'from' => $this->from,
            'to' => $this->to,
        ];
    }
}
