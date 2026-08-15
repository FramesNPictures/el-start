<?php

namespace Fnp\ElStart\Traits;

use Fnp\ElStart\Contracts\VaultDetail;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppVault;
use Fnp\ElStart\Models\AppVaultGrant;
use Fnp\ElStart\Models\AppVaultKey;
use Fnp\ElStart\Services\VaultService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;

trait HasVault
{
    /**
     * Whether the model holds the given detail. Reading the presence of an
     * entry does not need the vault to be unlocked.
     *
     * @param  VaultDetail  $detail  Detail to look for
     */
    public function hasVault(VaultDetail $detail): bool
    {
        return app(VaultService::class)->has($this, $detail);
    }

    /**
     * Write a detail of the model into the vault.
     *
     * @param  VaultDetail  $detail  Detail to write
     * @param  mixed  $value  Anything that survives a JSON round trip
     *
     * @throws VaultException When the vault is locked
     */
    public function putVault(VaultDetail $detail, mixed $value): AppVault
    {
        return app(VaultService::class)->put($this, $detail, $value);
    }

    /**
     * Drop the entries of the model, either one detail or all of them.
     * Dropping an entry does not need the vault to be unlocked.
     *
     * @param  VaultDetail|null  $detail  Detail to drop, all of them when omitted
     * @return int Number of removed entries
     */
    public function removeVault(?VaultDetail $detail = null): int
    {
        return app(VaultService::class)->remove($this, $detail);
    }

    /**
     * Take a detail of the model away from another model, rotating the key so
     * the value stored from now on is out of its reach.
     *
     * @param  VaultDetail  $detail  Detail to revoke access to
     * @param  Model  $reader  Model losing access
     * @return bool Whether there was a grant to take away
     *
     * @throws VaultException When the entry is not there or this session cannot open it
     */
    public function revokeVault(VaultDetail $detail, Model $reader): bool
    {
        return app(VaultService::class)->revoke($this, $detail, $reader);
    }

    /**
     * Seal a detail of the model to another model, so it can read it too.
     *
     * @param  VaultDetail  $detail  Detail to share
     * @param  Model  $reader  Model gaining access, needs a key pair of its own
     *
     * @throws VaultException When the entry is not there, this session cannot
     *                        open it, or the reader never unlocked the vault
     */
    public function shareVault(VaultDetail $detail, Model $reader): AppVaultGrant
    {
        return app(VaultService::class)->share($this, $detail, $reader);
    }

    /**
     * All the vault entries attached to the model, encrypted as they are stored.
     */
    public function vault(): MorphMany
    {
        return $this->morphMany(AppVault::class, 'vaultable');
    }

    /**
     * The details the model holds. Details are stored in the clear so the
     * entries stay addressable while the vault is locked.
     *
     * @return Collection<int, int>
     */
    public function vaultDetails(): Collection
    {
        return $this->vault()->orderBy('detail_eid')->pluck('detail_eid');
    }

    /**
     * The grants the model holds, one per entry it may open.
     */
    public function vaultGrants(): MorphMany
    {
        return $this->morphMany(AppVaultGrant::class, 'keyable');
    }

    /**
     * The key pair of the model, minted the first time it unlocks the vault.
     */
    public function vaultKey(): MorphOne
    {
        return $this->morphOne(AppVaultKey::class, 'keyable');
    }

    /**
     * Read a detail of the model out of the vault.
     *
     * @param  VaultDetail  $detail  Detail to read
     * @param  mixed  $default  Returned when the model holds no such detail
     *
     * @throws VaultException When the vault is locked or nothing grants access
     */
    public function vaultValue(VaultDetail $detail, mixed $default = null): mixed
    {
        return app(VaultService::class)->get($this, $detail, $default);
    }
}
