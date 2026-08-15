<?php

namespace Fnp\ElStart\Features;

use Fnp\ElStart\Services\VaultService;

trait ModuleVaultSystem
{
    /**
     * Returns the key pair of the system user, both halves base64 encoded.
     *
     * Give every process the `public` key so new entries are escrowed, and the
     * `secret` one only where the system user is allowed to read — a console
     * or queue process reading `env()`, never a web request. Set `unlock` to
     * open the vault as the system user the moment the module boots.
     *
     * @return array{public: string|null, secret: string|null, unlock: bool}
     */
    abstract public function defineVaultSystem(): array;

    public function bootModuleVaultSystemFeature(VaultService $vault): void
    {
        $keys = $this->defineVaultSystem();

        VaultService::useSystemKeys($keys['public'] ?? null, $keys['secret'] ?? null);

        if ($keys['unlock'] ?? false) {
            $vault->unlockAsSystem();
        }
    }
}
