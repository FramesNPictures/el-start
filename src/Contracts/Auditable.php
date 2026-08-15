<?php

namespace Fnp\ElStart\Contracts;

interface Auditable
{
    /**
     * Returns the payload to be stored with the audit entry.
     */
    public function audit(): array;
}
