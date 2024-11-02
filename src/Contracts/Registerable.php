<?php

namespace FNP\ElStart\Contracts;

interface Registerable
{
    /**
     * Returns unique object UUID
     * @return string The UUID of the registration.
     */
    public static function registrationUUID(): string;
}