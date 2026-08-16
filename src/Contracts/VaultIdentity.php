<?php

namespace Fnp\ElStart\Contracts;

/**
 * Marks a model that salts its vault key derivation with something of its own
 * rather than with an email address column.
 *
 * The value has to be stable and unique to the model, and it is read on every
 * unlock — so it cannot be anything the vault itself holds. `AppUser` returns
 * the hash of its email address, which stays in the table while the address
 * itself moves into the vault.
 */
interface VaultIdentity
{
    public function vaultIdentity(): string;
}
