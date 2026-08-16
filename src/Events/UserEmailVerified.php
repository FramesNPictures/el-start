<?php

namespace Fnp\ElStart\Events;

class UserEmailVerified extends UserEvent
{
    /**
     * When the address was confirmed. The address itself stays where it is —
     * in the vault.
     */
    public function audit(): array
    {
        return [
            ...parent::audit(),
            'verified_at' => $this->user->email_verified_at?->format('Y-m-d H:i:s'),
        ];
    }
}
