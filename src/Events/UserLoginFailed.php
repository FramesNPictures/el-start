<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;

class UserLoginFailed implements Auditable
{
    /**
     * @param  string  $email  Address that was tried
     */
    public function __construct(
        public readonly string $email,
    ) {}

    /**
     * What was tried, which is enough to count the attempts against one
     * account. Never what was typed as the password.
     */
    public function audit(): array
    {
        return [
            'email' => $this->email,
        ];
    }
}
