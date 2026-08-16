<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppVaultGrant;

class VaultClosed
{
    /**
     * @param  string  $identity  Morph type of the key pair that was open, or `system`
     * @param  int|string|null  $identityId  Morph id of the key pair
     */
    public function __construct(
        public readonly string $identity,
        public readonly int|string|null $identityId = null,
    ) {}

    /**
     * Whether the vault was open as the system user.
     */
    public function isSystem(): bool
    {
        return $this->identity === AppVaultGrant::SYSTEM_TYPE;
    }
}
