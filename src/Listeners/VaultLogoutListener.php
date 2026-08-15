<?php

namespace Fnp\ElStart\Listeners;

use Fnp\ElStart\Services\VaultService;
use Illuminate\Auth\Events\Logout;

class VaultLogoutListener
{
    public function __construct(protected VaultService $vault) {}

    /**
     * Drops the vault key the moment the user logs out.
     */
    public function handle(Logout $event): void
    {
        $this->vault->lock();
    }
}
