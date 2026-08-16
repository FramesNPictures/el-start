<?php

namespace Fnp\ElStart\Tests\Stubs;

use Fnp\ElStart\Traits\HasTokens;
use Illuminate\Database\Eloquent\Model;

class TokenableStub extends Model
{
    use HasTokens;

    const TABLE = 'stub_tokenables';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
    ];

    protected $table = self::TABLE;
}
