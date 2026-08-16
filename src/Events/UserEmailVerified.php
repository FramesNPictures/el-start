<?php

namespace Fnp\ElStart\Events;

class UserEmailVerified extends UserEvent
{
    /**
     * When the address was confirmed, which is all that changed about it.
     */
    public function audit(): array
    {
        return [
            ...parent::audit(),
            'verified_at' => $this->user->email_verified_at?->format('Y-m-d H:i:s'),
        ];
    }
}
