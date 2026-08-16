<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppUser;

class UserEmailChanged extends UserEvent
{
    /**
     * @param  AppUser  $user  User that was renamed
     * @param  string  $from  Hash of the address it was
     * @param  string  $to  Hash of the address it is
     */
    public function __construct(
        AppUser $user,
        public readonly string $from,
        public readonly string $to,
    ) {
        parent::__construct($user);
    }

    /**
     * Both addresses as their hashes, so the change can be followed without
     * either of them being written down.
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
