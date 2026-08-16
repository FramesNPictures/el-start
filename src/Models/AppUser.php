<?php

namespace Fnp\ElStart\Models;

use Fnp\ElStart\Contracts\VaultIdentity;
use Fnp\ElStart\Database\Factories\AppUserFactory;
use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Enums\ESystemVaultDetail;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Traits\HasTokens;
use Fnp\ElStart\Traits\HasVault;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The user record of the application, replacing the one Laravel scaffolds.
 *
 * Neither the name nor the email address is among its columns. `email_hash`
 * holds a keyed hash of the address, which is what the table is searched by,
 * and both the address and the name live in the vault of the user — readable
 * while their vault is unlocked, and by the system user where one is
 * registered. What is left in the table identifies an account without saying
 * whose it is.
 *
 * Neither is there a `remember_token` column: remember me tokens go into
 * `app_tokens` with every other token of the user. A `uuid` rides alongside
 * the primary key and is what the outside world sees.
 *
 * It is wired in through `auth.providers.users.model`, so `Auth::user()` and
 * every guard return this model. Extend it in the application when it needs
 * columns or behaviour of its own, and point the config at the subclass from
 * the module of the application.
 *
 * @property int $id
 * @property string $uuid What the user is known by outside the application
 * @property string $email_hash Keyed hash of the address, what the table is searched by
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string|null $name  Out of the vault, null while it is locked
 * @property-read string|null $email  Out of the vault, null while it is locked
 * @property-read array<int, array{email: string, until: string}> $previous_emails
 * @property-read Collection<int, AppToken> $tokens
 * @property-read Collection<int, AppVault> $vault
 * @property-read Collection<int, AppVaultGrant> $vaultGrants
 * @property-read AppVaultKey|null $vaultKey
 */
class AppUser extends Authenticatable implements VaultIdentity
{
    use HasFactory;
    use HasTokens;
    use HasUuids;
    use HasVault;
    use Notifiable;
    use SoftDeletes;

    const TABLE = 'app_users';

    /**
     * The attributes that are mass assignable. `email_hash` is deliberately
     * not among them: it is what the user is found by, so it is set from the
     * service rather than from whatever a request happens to carry.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'password',
    ];

    /**
     * The attributes hidden from array and JSON output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'email_hash',
    ];

    protected $table = self::TABLE;

    /**
     * The keyed hash an email address is stored and searched by.
     *
     * Keyed with the application key, so a stolen table cannot be walked
     * through a list of addresses to see who is registered. Rotating that key
     * makes every hash unreachable — recompute them from the vault before it
     * changes.
     *
     * @param  string  $email  Address to hash, case and padding are normalised
     */
    public static function hashEmail(string $email): string
    {
        return hash_hmac('sha256', Str::lower(trim($email)), (string) config('app.key'));
    }

    /**
     * The hash is what password resets are keyed by, so the address stays out
     * of any table that records one.
     */
    public function getEmailForPasswordReset(): string
    {
        return (string) $this->email_hash;
    }

    /**
     * And what verification links are signed against, for the same reason and
     * one more: the link is followed by somebody who is not logged in, whose
     * vault is therefore shut. A hash of the address is readable regardless.
     */
    public function getEmailForVerification(): string
    {
        return (string) $this->email_hash;
    }

    /**
     * The remember me token of the user, out of `app_tokens`.
     */
    public function getRememberToken(): ?string
    {
        return $this->tokenValue(ESystemTokenType::Remember);
    }

    /**
     * There is no column behind the remember me token.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * The uuid is what a user is known by outside the application, so a URL
     * never carries a row number that can be counted or walked.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Keep the remember me token in `app_tokens`, one per user, replacing
     * whichever was there — the same as the column it stands in for.
     *
     * @param  string|null  $value  Token to keep, null to drop the one there
     */
    public function setRememberToken($value): void
    {
        if (! $this->exists) {
            return;
        }

        $this->removeTokens(ESystemTokenType::Remember);

        if ($value !== null && $value !== '') {
            $this->addToken(ESystemTokenType::Remember, $value);
        }
    }

    /**
     * The columns filled with a generated id. The primary key stays an
     * auto incrementing integer, the uuid rides alongside it.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Salts the vault key derivation. The hash of the address rather than the
     * address itself, which the vault is the one holding.
     */
    public function vaultIdentity(): string
    {
        return (string) $this->email_hash;
    }

    /**
     * The factory of this module, which knows to put the name and the address
     * in the vault. A subclass wanting its own should extend `AppUserFactory`
     * rather than start from nothing.
     */
    protected static function newFactory(): AppUserFactory
    {
        return AppUserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The email address, read out of the vault rather than off a column.
     *
     * `Notifiable` routes mail through it, so a user whose vault is locked is
     * simply not written to.
     */
    protected function email(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->vaultDetail(ESystemVaultDetail::Email));
    }

    /**
     * The name, read out of the vault rather than off a column.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->vaultDetail(ESystemVaultDetail::Name));
    }

    /**
     * Every address the user had before this one, oldest first, each with the
     * moment it stopped being theirs. Kept in the vault like the current one,
     * so a change of address loses nothing and reveals nothing.
     *
     * @return array<int, array{email: string, until: string}>
     */
    protected function previousEmails(): Attribute
    {
        return Attribute::get(function (): array {
            try {
                return $this->vaultValue(ESystemVaultDetail::EmailHistory, []) ?? [];
            } catch (VaultException) {
                return [];
            }
        });
    }

    /**
     * A detail of the user out of the vault, null where it cannot be read at
     * all — a locked vault, or a key pair with no grant on the entry.
     *
     * Nothing here is worth breaking a template or a notification over. Call
     * `vaultValue()` where the difference between "not readable" and "not set"
     * has to be told apart.
     */
    protected function vaultDetail(ESystemVaultDetail $detail): ?string
    {
        try {
            return $this->vaultValue($detail);
        } catch (VaultException) {
            return null;
        }
    }
}
