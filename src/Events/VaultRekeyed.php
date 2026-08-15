<?php

namespace Fnp\ElStart\Events;

use Fnp\ElStart\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

class VaultRekeyed implements Auditable
{
    /**
     * @param  Model  $user  Model the key pair belongs to
     * @param  bool  $recovered  Whether the system user handed the entries over
     * @param  int  $entries  Number of re-sealed entries, none on a plain rekey
     */
    public function __construct(
        public readonly Model $user,
        public readonly bool $recovered = false,
        public readonly int $entries = 0,
    ) {}

    /**
     * Whose key pair was rewritten, and whether it was a recovery. Never the
     * password, never a key.
     */
    public function audit(): array
    {
        return [
            'identity' => $this->user->getMorphClass(),
            'identity_id' => $this->user->getKey(),
            'recovered' => $this->recovered,
            'entries' => $this->entries,
        ];
    }
}
