<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Models\AppVault;
use Illuminate\Database\Eloquent\Model;

class VaultShared implements Auditable
{
    /**
     * @param  AppVault  $entry  Entry that was sealed to another key pair
     * @param  Model  $reader  Model that gained access
     */
    public function __construct(
        public readonly AppVault $entry,
        public readonly Model $reader,
    ) {}

    /**
     * Who was let in, and to which detail of which model. Never the value,
     * never a key.
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
