<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElModule\ElModule;
use Fnp\ElStart\Features\ModuleVaultSystem;

class VaultSystemStubModule extends ElModule
{
    use ModuleVaultSystem;
    /**
     * @var array{public: string|null, secret: string|null, unlock: bool}
     */
    public static array $keys = [
        'public' => null,
        'secret' => null,
        'unlock' => false,
    ];

    public function defineVaultSystem(): array
    {
        return static::$keys;
    }
}
