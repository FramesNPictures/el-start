<?php

namespace Fnp\ElStart\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class VaultException extends RuntimeException
{
    /**
     * The vault holds no key for the current session.
     */
    public static function locked(): static
    {
        return new static('The vault is locked. Unlock it with the user record and the password first.');
    }

    /**
     * There is no entry to work with.
     */
    public static function noEntry(): static
    {
        return new static('The model holds no such detail in the vault.');
    }

    /**
     * The model never unlocked the vault, so it has no key pair to seal to.
     */
    public static function noPublicKey(Model $model): static
    {
        return new static(sprintf(
            'The %s has no vault key pair yet. It gets one the first time it unlocks the vault.',
            $model::class,
        ));
    }

    /**
     * Nothing grants the current key pair access to the entry.
     */
    public static function notGranted(): static
    {
        return new static('The vault entry was never sealed to the key pair this process holds.');
    }

    /**
     * The vault is unlocked as the system user, which never writes entries.
     */
    public static function systemIsReadOnly(): static
    {
        return new static('The vault is unlocked as the system user, which cannot write entries.');
    }

    /**
     * A system key of the wrong size was registered.
     */
    public static function systemKeyInvalid(string $which, int $expected): static
    {
        return new static(sprintf(
            'The %s system key has to be %d bytes, base64 encoded.',
            $which,
            $expected,
        ));
    }

    /**
     * There is no system key pair to work with.
     */
    public static function systemUnavailable(): static
    {
        return new static('No system key pair is registered in this process. Register one with a module feature.');
    }

    /**
     * The user record cannot be turned into a key.
     */
    public static function unidentified(Model $user): static
    {
        return new static(sprintf(
            'A vault key needs a saved %s with an email address.',
            $user::class,
        ));
    }

    /**
     * The stored value does not decrypt with the current key.
     */
    public static function unreadable(): static
    {
        return new static('The vault entry cannot be read with the current key.');
    }
}
