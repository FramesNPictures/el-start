<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppVaultGrant;

class VaultClosed implements Auditable
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
     * Whose key pair was dropped. Never the password, never a key.
     */
    public function audit(): array
    {
        return [
            'identity' => $this->identity,
            'identity_id' => $this->identityId,
        ];
    }

    /**
     * Whether the vault was open as the system user.
     */
    public function isSystem(): bool
    {
        return $this->identity === AppVaultGrant::SYSTEM_TYPE;
    }
}
