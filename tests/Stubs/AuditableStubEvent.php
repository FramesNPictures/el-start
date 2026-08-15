<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Contracts\Auditable;

class AuditableStubEvent implements Auditable
{
    public function __construct(private array $data = []) {}

    public function audit(): array
    {
        return $this->data;
    }
}
