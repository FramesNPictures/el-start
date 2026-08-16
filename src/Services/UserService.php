<?php

namespace Fnp\ElStart\Services;

use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Enums\ESystemVaultDetail;
use Fnp\ElStart\Events\UserDeleted;
use Fnp\ElStart\Events\UserEmailChanged;
use Fnp\ElStart\Events\UserEmailVerified;
use Fnp\ElStart\Events\UserLoggedIn;
use Fnp\ElStart\Events\UserLoginFailed;
use Fnp\ElStart\Events\UserPasswordChanged;
use Fnp\ElStart\Events\UserPasswordReset;
use Fnp\ElStart\Events\UserPasswordResetRequested;
use Fnp\ElStart\Events\UserRegistered;
use Fnp\ElStart\Exceptions\VaultException;
use Fnp\ElStart\Models\AppUser;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * The moments a user record and a session meet: registering, logging in,
 * changing the address they are known by, and going away.
 *
 * Each of them holds the plain password for the only instant it exists, which
 * is also the only instant the vault can be unlocked — so all of them do it.
 * The email address never reaches a column: the table stores its hash, and the
 * address itself goes into the vault of the user.
 */
class UserService
{
    /**
     * Change the address a user is known by.
     *
     * The hash salts their vault key, so the key pair has to be wrapped anew,
     * which is why the password is asked for. Call it with the vault unlocked
     * as that same user.
     *
     * @param  AppUser  $user  User to rename
     * @param  string  $email  New email address
     * @param  string  $password  Current password of the user
     *
     * @throws VaultException When the vault is locked
     */
    public function changeEmail(AppUser $user, string $email, #[SensitiveParameter] string $password): AppUser
    {
        $from = (string) $user->email_hash;
        $previous = $user->email;

        $user->email_hash = $this->model()::hashEmail($email);

        // Derives the key from the new hash, so the order matters.
        app(VaultService::class)->rekey($user, $password);

        $user->save();
        $user->putVault(ESystemVaultDetail::Email, $email);

        // The one they had goes to the back of the vault rather than nowhere.
        if ($previous !== null && $previous !== $email) {
            $user->putVault(ESystemVaultDetail::EmailHistory, [
                ...$user->previous_emails,
                ['email' => $previous, 'until' => Carbon::now()->toDateTimeString()],
            ]);
        }

        Event::dispatch(new UserEmailChanged($user, $from, $user->email_hash));

        return $user;
    }

    /**
     * Change the password of a user who still knows the old one.
     *
     * The password wraps their vault key pair, so it is rewritten first — call
     * it with the vault unlocked as that same user. Every remember me token
     * goes, so a browser left logged in elsewhere has to sign in again.
     *
     * @param  AppUser  $user  User to give a new password
     * @param  string  $password  New password
     *
     * @throws VaultException When the vault is locked
     */
    public function changePassword(AppUser $user, #[SensitiveParameter] string $password): AppUser
    {
        app(VaultService::class)->rekey($user, $password);

        $user->password = $password;
        $user->save();

        $user->removeTokens(ESystemTokenType::Remember);
        $user->removeTokens(ESystemTokenType::PasswordReset);

        Event::dispatch(new UserPasswordChanged($user));

        return $user;
    }

    /**
     * Soft delete a user, ending their session when it is their own.
     *
     * Their tokens and vault entries are left alone: the record can come back
     * with `restore()`, and the data has to be there when it does. Drop them
     * explicitly where a deleted account has to lose its access at once.
     *
     * @param  AppUser  $user  User to delete
     * @return bool Whether the record was deleted
     */
    public function delete(AppUser $user): bool
    {
        $isSelf = Auth::check() && Auth::id() === $user->getKey();

        $deleted = (bool) $user->delete();

        if ($deleted && $isSelf) {
            // Logging out locks the vault through the listener of this module.
            Auth::logout();
        }

        if ($deleted) {
            Event::dispatch(new UserDeleted($user));
        }

        return $deleted;
    }

    /**
     * Find a user by their email address, through its hash.
     *
     * @param  string  $email  Address to look up
     * @param  bool  $withTrashed  Whether to look among the deleted ones too
     */
    public function findByEmail(string $email, bool $withTrashed = false): ?AppUser
    {
        $model = $this->model();

        return $model::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->where('email_hash', $model::hashEmail($email))
            ->first();
    }

    /**
     * Log a user in and unlock their vault.
     *
     * @param  string  $email  Email address of the user
     * @param  string  $password  Password only the user knows
     * @param  bool  $remember  Whether to issue a remember me cookie
     * @return AppUser|null The user, or null when the credentials do not match
     */
    public function login(string $email, #[SensitiveParameter] string $password, bool $remember = false): ?AppUser
    {
        $credentials = [
            'email_hash' => $this->model()::hashEmail($email),
            'password' => $password,
        ];

        if (! Auth::attempt($credentials, $remember)) {
            Event::dispatch(new UserLoginFailed($credentials['email_hash']));

            return null;
        }

        // A fresh id for a fresh session, keeping what is already in it.
        Session::regenerate();

        $user = Auth::user();

        $unlocked = $this->unlockVault($user, $password);

        Event::dispatch(new UserLoggedIn($user, $remember, $unlocked));

        return $user;
    }

    /**
     * The model behind the user provider, this one unless the application
     * points the config at a subclass.
     *
     * @return class-string<AppUser>
     */
    public function model(): string
    {
        return config('auth.providers.users.model') ?? AppUser::class;
    }

    /**
     * Register a user, mint their vault key pair and put their email address
     * in it.
     *
     * The user is not logged in — call `login()` after it, or `Auth::login()`
     * where the password should not be checked again.
     *
     * @param  string  $name  Name of the user
     * @param  string  $email  Email address, unique across the table by its hash
     * @param  string  $password  Password only the user knows, hashed on the way in
     * @param  array<string, mixed>  $attributes  Any further columns the model makes fillable
     */
    public function register(
        string $name,
        string $email,
        #[SensitiveParameter]
        string $password,
        array $attributes = [],
    ): AppUser {
        $model = $this->model();

        $user = new $model();

        $user->fill([...$attributes, 'password' => $password]);
        $user->email_hash = $model::hashEmail($email);
        $user->save();

        // Who the account belongs to is only ever readable through the vault.
        if ($this->unlockVault($user, $password)) {
            $user->putVault(ESystemVaultDetail::Email, $email);
            $user->putVault(ESystemVaultDetail::Name, $name);
        }

        // The Laravel one first, so anything listening for it keeps working.
        Event::dispatch(new Registered($user));
        Event::dispatch(new UserRegistered($user));

        return $user;
    }

    /**
     * Set a new password from a reset token, which is spent in the process.
     *
     * There is no old password here, so the vault key pair cannot be rewritten
     * — the system user is the one that can hand it over, and does when this
     * runs unlocked as it. Anywhere else the entries of that user stay closed
     * until a recovery runs, while the account itself works again.
     *
     * @param  string  $token  Token handed out by `startPasswordReset()`
     * @param  string  $password  New password
     * @return AppUser|null The user, or null when the token is unknown or expired
     */
    public function resetPassword(string $token, #[SensitiveParameter] string $password): ?AppUser
    {
        $user = app(TokenService::class)->findTarget(
            $this->hashToken($token),
            ESystemTokenType::PasswordReset,
        );

        if (! $user instanceof AppUser) {
            return null;
        }

        $vault = app(VaultService::class);
        $recovered = $vault->isSystem();

        if ($recovered) {
            $vault->recover($user, $password);
        }

        $user->password = $password;
        $user->save();

        $user->removeTokens(ESystemTokenType::PasswordReset);
        $user->removeTokens(ESystemTokenType::Remember);

        Event::dispatch(new UserPasswordReset($user, $recovered));

        return $user;
    }

    /**
     * Hand out a password reset token, replacing any that is still around.
     *
     * The token is returned in the clear for the notification to carry, and
     * only its digest is stored — a stolen `app_tokens` cannot be turned into
     * a password reset.
     *
     * @param  AppUser  $user  User the token belongs to
     * @param  int|null  $minutes  How long it lives, `auth.passwords.users.expire` by default
     * @return string The token to send
     */
    public function startPasswordReset(AppUser $user, ?int $minutes = null): string
    {
        $minutes ??= (int) config('auth.passwords.users.expire', 60);
        $token = Str::random(64);
        $expiresAt = Carbon::now()->addMinutes($minutes);

        $user->removeTokens(ESystemTokenType::PasswordReset);

        $user->addToken(
            ESystemTokenType::PasswordReset,
            $this->hashToken($token),
            $expiresAt,
        );

        Event::dispatch(new UserPasswordResetRequested($user, $expiresAt));

        return $token;
    }

    /**
     * Mark the address of a user as confirmed.
     *
     * Nothing here needs the vault: the address is not read, only the column
     * saying it was reached is written. A user who is already verified is left
     * alone and announces nothing.
     *
     * @param  AppUser  $user  User whose address was reached
     * @return bool Whether this was the moment it got verified
     */
    public function verifyEmail(AppUser $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $user->markEmailAsVerified();

        // The Laravel one first, so anything listening for it keeps working.
        Event::dispatch(new Verified($user));
        Event::dispatch(new UserEmailVerified($user));

        return true;
    }

    /**
     * The digest a reset token is stored under. A plain one is enough: the
     * token is 64 random characters, so there is nothing to guess back.
     */
    protected function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Unlock the vault of a user, leaving it locked when its key pair does not
     * open. A vault nobody can read is no reason to refuse a valid password —
     * check `vault()->isUnlocked()` where the entries are actually needed.
     *
     * @return bool Whether the vault was opened
     */
    protected function unlockVault(AppUser $user, #[SensitiveParameter] string $password): bool
    {
        try {
            app(VaultService::class)->unlock($user, $password);
        } catch (VaultException) {
            return false;
        }

        return true;
    }
}
