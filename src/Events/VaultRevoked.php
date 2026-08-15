<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppVault;
use Illuminate\Database\Eloquent\Model;

class VaultRevoked implements Auditable
{
    /**
     * @param  AppVault  $entry  Entry the key pair lost access to
     * @param  Model  $reader  Model that lost access
     */
    public function __construct(
        public readonly AppVault $entry,
        public readonly Model $reader,
    ) {}

    /**
     * Who was shut out, and out of which detail of which model. Never the
     * value, never a key.
     */
    public function audit(): array
    {
        return [
            'vaultable' => $this->entry->vaultable_type,
            'vaultable_id' => $this->entry->vaultable_id,
            'detail' => $this->entry->detailValue(),
            'entry_id' => $this->entry->getKey(),
            'reader' => $this->reader->getMorphClass(),
            'reader_id' => $this->reader->getKey(),
        ];
    }
}
