<?php

namespace Fnp\ElStart\Models;

use Fnp\ElStart\Database\Factories\AppUserFactory;
use Fnp\ElStart\Enums\ESystemTokenType;
use Fnp\ElStart\Traits\HasTokens;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * The user record of the application, replacing the one Laravel scaffolds.
 *
 * The name and the address are columns of `app_users`, and the address is what
 * a guard checks and what the table is searched by. Every address they had
 * before is kept in `app_users_emails` rather than thrown away.
 *
 * There is no `remember_token` column: remember me tokens go into `app_tokens`
 * with every other token of the user. A `uuid` rides alongside the primary key
 * and is what the outside world sees.
 *
 * It is wired in through `auth.providers.users.model`, so `Auth::user()` and
 * every guard return this model. Extend it in the application when it needs
 * columns or behaviour of its own, and point the config at the subclass from
 * the module of the application.
 *
 * @property int $id
 * @property string $uuid What the user is known by outside the application
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, AppToken> $tokens
 * @property-read Collection<int, AppUserEmail> $previousEmails
 *
 * @method static AppUserFactory factory($count = null, $state = [])
 */
class AppUser extends Authenticatable
{
    use HasFactory;
    use HasTokens;
    use HasUuids;
    use Notifiable;
    use SoftDeletes;

    const TABLE = 'app_users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes hidden from array and JSON output.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    protected $table = self::TABLE;

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
     * Every address the user had before this one, oldest first.
     */
    public function previousEmails(): HasMany
    {
        return $this->hasMany(AppUserEmail::class, 'user_id')->orderBy('until');
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
     * The factory of this module. A subclass wanting its own should extend
     * `AppUserFactory` rather than start from nothing.
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
}
