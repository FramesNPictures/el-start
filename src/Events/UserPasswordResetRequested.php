<?php

namespace Fnp\ElStart\Events;

use DateTimeInterface;
use Fnp\ElStart\Models\AppUser;

class UserPasswordResetRequested extends UserEvent
{
    /**
     * @param  AppUser  $user  User the token was handed out for
     * @param  DateTimeInterface  $expiresAt  When it stops working
     */
    public function __construct(
        AppUser $user,
        public readonly DateTimeInterface $expiresAt,
    ) {
        parent::__construct($user);
    }

    /**
     * That a reset was asked for and until when it stands. The token itself
     * is not here — it is not even stored, only its digest is.
     */
    public function audit(): array
    {
        return [
            ...parent::audit(),
            'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
        ];
    }
}
