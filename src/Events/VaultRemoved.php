<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Fnp\ElStart\Contracts\VaultDetail;
use Illuminate\Database\Eloquent\Model;

class VaultRemoved implements Auditable
{
    /**
     * @param  Model  $model  Model the entries belonged to
     * @param  VaultDetail|null  $detail  Detail that was dropped, null for all of them
     * @param  int  $entries  Number of removed entries
     */
    public function __construct(
        public readonly Model $model,
        public readonly ?VaultDetail $detail = null,
        public readonly int $entries = 0,
    ) {}

    /**
     * What was dropped and where. The value went with it and was never read.
     */
    public function audit(): array
    {
        return [
            'vaultable' => $this->model->getMorphClass(),
            'vaultable_id' => $this->model->getKey(),
            'detail' => $this->detail?->value,
            'entries' => $this->entries,
        ];
    }
}
