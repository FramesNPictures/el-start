<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppVault;

class VaultUpdated implements Auditable
{
    /**
     * @param  AppVault  $entry  Entry that was written
     * @param  bool  $created  Whether it was stored for the first time
     */
    public function __construct(
        public readonly AppVault $entry,
        public readonly bool $created = false,
    ) {}

    /**
     * What was written and where. Never the value itself — an audit trail of
     * the vault says that a detail changed, never what it changed to.
     */
    public function audit(): array
    {
        return [
            'vaultable' => $this->entry->vaultable_type,
            'vaultable_id' => $this->entry->vaultable_id,
            'detail' => $this->entry->detailValue(),
            'entry_id' => $this->entry->getKey(),
            'created' => $this->created,
        ];
    }
}
