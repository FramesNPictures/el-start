<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppVaultGrant;

class VaultOpened implements Auditable
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
     * Who opened the vault. Never the password, never a key.
     */
    public function audit(): array
    {
        return [
            'identity' => $this->identity,
            'identity_id' => $this->identityId,
            'minted' => $this->minted,
        ];
    }

    /**
     * Whether the vault was opened as the system user.
     */
    public function isSystem(): bool
    {
        return $this->identity === AppVaultGrant::SYSTEM_TYPE;
    }
}
