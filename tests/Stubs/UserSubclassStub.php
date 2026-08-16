<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Models\AppUser;

class UserSubclassStub extends AppUser
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];
}
