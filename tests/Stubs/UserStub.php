<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Traits\HasVault;
use Illuminate\Database\Eloquent\Model;

class UserStub extends Model
{
    use HasVault;

    const TABLE = 'stub_users';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
    ];

    protected $table = self::TABLE;
}
