<?php

namespace FNP\ElStart\Contracts;

use FNP\ElStart\Models\AppUserModel;

interface Loggable
{
    public function logProperties(): array;

    public function logUser(): ?AppUserModel;
}