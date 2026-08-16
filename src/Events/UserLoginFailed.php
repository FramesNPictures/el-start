<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;

class UserLoginFailed implements Auditable
{
    /**
     * @param  string  $emailHash  Hash of the address that was tried
     */
    public function __construct(
        public readonly string $emailHash,
    ) {}

    /**
     * What was tried, by the same hash the table is searched by — enough to
     * count the attempts against one account without writing the address of
     * it anywhere.
     */
    public function audit(): array
    {
        return [
            'email_hash' => $this->emailHash,
        ];
    }
}
