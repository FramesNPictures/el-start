<?php

namespace Fnp\ElStart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An address a user had before the one they carry now, kept from the moment
 * `changeEmail()` moves them off it.
 *
 * The row says until when it was theirs, so a support request naming an old
 * address still finds the account. Nothing logs in through one: only the
 * address on `app_users` is what a guard checks.
 *
 * @property int $id
 * @property int $user_id
 * @property string $email
 * @property Carbon $until When it stopped being theirs
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AppUser|null $user
 */
class AppUserEmail extends Model
{
    const TABLE = 'app_users_emails';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'until',
    ];

    protected $table = self::TABLE;

    /**
     * The user the address belonged to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'user_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'until' => 'datetime',
        ];
    }
}
