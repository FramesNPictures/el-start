<?php

namespace Fnp\ElStart\Services;

use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Events\UserDeleted;
use Fnp\ElStart\Events\UserEmailChanged;
use Fnp\ElStart\Events\UserEmailVerified;
use Fnp\ElStart\Events\UserLoggedIn;
use Fnp\ElStart\Events\UserLoginFailed;
use Fnp\ElStart\Events\UserPasswordChanged;
use Fnp\ElStart\Events\UserPasswordReset;
use Fnp\ElStart\Events\UserPasswordResetRequested;
use Fnp\ElStart\Events\UserRegistered;
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
 * Addresses are normalised on the way in — lowercased and trimmed — so one
 * address is one account no matter how it was typed.
 */
class UserService
{
    /**
     * Change the address a user is known by.
     *
     * The one they had is kept in `app_users_emails` rather than dropped, so a
     * request naming an old address still finds the account. Moving a user to
     * the address they already have does nothing and announces nothing.
     *
     * @param  AppUser  $user  User to rename
     * @param  string  $email  New email address
     */
    public function changeEmail(AppUser $user, string $email): AppUser
    {
        $from = (string) $user->email;
        $to = $this->normalizeEmail($email);

        if ($from === $to) {
            return $user;
        }

        $user->email = $to;
        $user->save();

        $user->previousEmails()->create([
            'email' => $from,
            'until' => Carbon::now(),
        ]);

        // Anything holding the user has the addresses of a moment ago.
        $user->unsetRelation('previousEmails');

        Event::dispatch(new UserEmailChanged($user, $from, $to));

        return $user;
    }

    /**
     * Change the password of a user.
     *
     * Every remember me token goes, so a browser left logged in elsewhere has
     * to sign in again.
     *
     * @param  AppUser  $user  User to give a new password
     * @param  string  $password  New password
     */
    public function changePassword(AppUser $user, #[SensitiveParameter] string $password): AppUser
    {
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
     * Their tokens and the addresses they had are left alone: the record can
     * come back with `restore()`, and the data has to be there when it does.
     * Drop them explicitly where a deleted account has to lose its access at
     * once.
     *
     * @param  AppUser  $user  User to delete
     * @return bool Whether the record was deleted
     */
    public function delete(AppUser $user): bool
    {
        $isSelf = Auth::check() && Auth::id() === $user->getKey();

        $deleted = (bool) $user->delete();

        if ($deleted && $isSelf) {
            Auth::logout();
        }

        if ($deleted) {
            Event::dispatch(new UserDeleted($user));
        }

        return $deleted;
    }

    /**
     * Find a user by their email address.
     *
     * @param  string  $email  Address to look up
     * @param  bool  $withTrashed  Whether to look among the deleted ones too
     */
    public function findByEmail(string $email, bool $withTrashed = false): ?AppUser
    {
        $model = $this->model();

        return $model::query()
            ->when($withTrashed, fn ($query) => $query->withTrashed())
            ->where('email', $this->normalizeEmail($email))
            ->first();
    }

    /**
     * Log a user in.
     *
     * @param  string  $email  Email address of the user
     * @param  string  $password  Password only the user knows
     * @param  bool  $remember  Whether to issue a remember me cookie
     * @return AppUser|null The user, or null when the credentials do not match
     */
    public function login(string $email, #[SensitiveParameter] string $password, bool $remember = false): ?AppUser
    {
        $credentials = [
            'email' => $this->normalizeEmail($email),
            'password' => $password,
        ];

        if (! Auth::attempt($credentials, $remember)) {
            Event::dispatch(new UserLoginFailed($credentials['email']));

            return null;
        }

        // A fresh id for a fresh session, keeping what is already in it.
        Session::regenerate();

        $user = Auth::user();

        Event::dispatch(new UserLoggedIn($user, $remember));

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
     * Register a user.
     *
     * The user is not logged in — call `login()` after it, or `Auth::login()`
     * where the password should not be checked again.
     *
     * @param  string  $name  Name of the user
     * @param  string  $email  Email address, unique across the table
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

        $user->fill([
            ...$attributes,
            'name' => $name,
            'email' => $this->normalizeEmail($email),
            'password' => $password,
        ]);

        $user->save();

        // The Laravel one first, so anything listening for it keeps working.
        Event::dispatch(new Registered($user));
        Event::dispatch(new UserRegistered($user));

        return $user;
    }

    /**
     * Set a new password from a reset token, which is spent in the process.
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

        $user->password = $password;
        $user->save();

        $user->removeTokens(ESystemTokenType::PasswordReset);
        $user->removeTokens(ESystemTokenType::Remember);

        Event::dispatch(new UserPasswordReset($user));

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
     * A user who is already verified is left alone and announces nothing.
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
     * The form an address is stored and searched by, so one address is one
     * account however it was typed.
     */
    protected function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }
}
