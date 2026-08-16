<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Models\AppVaultGrant;

class VaultOpened
{
    /**
     * @param  string  $identity  Morph type of the key pair, or `system`
     * @param  int|string|null  $identityId  Morph id of the key pair
     * @param  bool  $minted  Whether the key pair was created on this unlock
     */
    public function __construct(
        public readonly string $identity,
        public readonly int|string|null $identityId = null,
        public readonly bool $minted = false,
    ) {}

    /**
     * Whether the vault was opened as the system user.
     */
    public function isSystem(): bool
    {
        return $this->identity === AppVaultGrant::SYSTEM_TYPE;
    }
}
