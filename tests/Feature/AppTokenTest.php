<?php

use Fnp\ElStart\Models\AppToken;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create(TokenableStub::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });
});

afterEach(function (): void {
    // The registered enum is static and would otherwise leak between tests.
    AppToken::useTokenTypes(null);

    Schema::dropIfExists(TokenableStub::TABLE);
});

it('has the tokens table', function (): void {
    expect(Schema::hasTable(AppToken::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppToken::TABLE))
        ->toEqualCanonicalizing([
            'id',
            'tokenable_type',
            'tokenable_id',
            'type_eid',
            'value',
            'expires_at',
            'created_at',
            'updated_at',
        ]);
});

it('attaches a token to any model', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $token = $stub->addToken(ETokenTypeStub::Api, 'secret');

    expect($token->exists)->toBeTrue()
        ->and($token->tokenable_type)->toBe(TokenableStub::class)
        ->and($token->tokenable_id)->toBe($stub->id)
        ->and($token->value)->toBe('secret')
        ->and($token->expires_at)->toBeNull();
});

it('stores the token type as an integer', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Reset, 'secret');

    expect(DB::table(AppToken::TABLE)->value('type_eid'))->toBe(ETokenTypeStub::Reset->value)
        ->and(AppToken::first()->type_eid)->toBe(ETokenTypeStub::Reset->value);
});

it('casts the token type back to the registered enum', function (): void {
    AppToken::useTokenTypes(ETokenTypeStub::class);

    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Invitation, 'secret');

    expect(AppToken::first()->type_eid)->toBe(ETokenTypeStub::Invitation);
});

it('generates a random value when none is given', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $token = $stub->addToken(ETokenTypeStub::Api);
    $other = $stub->addToken(ETokenTypeStub::Api);

    expect($token->value)->toHaveLength(64)
        ->and($token->value)->not->toBe($other->value);
});

it('takes an expiry date', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $token = $stub->addToken(ETokenTypeStub::Reset, 'secret', Carbon::parse('2026-01-01 10:00:00'));

    expect($token->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($token->fresh()->expires_at->toDateTimeString())->toBe('2026-01-01 10:00:00');
});

it('resolves the model the token belongs to', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Api, 'secret');

    $tokenable = AppToken::first()->tokenable;

    expect($tokenable)->toBeInstanceOf(TokenableStub::class)
        ->and($tokenable->id)->toBe($stub->id);
});

it('reads the tokens of the model only', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $other = TokenableStub::create(['name' => 'Other']);

    $stub->addToken(ETokenTypeStub::Api, 'mine');
    $other->addToken(ETokenTypeStub::Api, 'theirs');

    expect($stub->tokens()->pluck('value')->all())->toBe(['mine']);
});

it('tells whether a token is expired', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $never = $stub->addToken(ETokenTypeStub::Api, 'never');
    $future = $stub->addToken(ETokenTypeStub::Api, 'future', Carbon::now()->addDay());
    $past = $stub->addToken(ETokenTypeStub::Api, 'past', Carbon::now()->subDay());

    expect($never->isExpired())->toBeFalse()
        ->and($never->isValid())->toBeTrue()
        ->and($future->isExpired())->toBeFalse()
        ->and($past->isExpired())->toBeTrue()
        ->and($past->isValid())->toBeFalse();
});

it('scopes the tokens by validity, type and value', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'never');
    $stub->addToken(ETokenTypeStub::Api, 'future', Carbon::now()->addDay());
    $stub->addToken(ETokenTypeStub::Invitation, 'past', Carbon::now()->subDay());

    expect(AppToken::valid()->pluck('value')->all())->toEqualCanonicalizing(['never', 'future'])
        ->and(AppToken::expired()->pluck('value')->all())->toBe(['past'])
        ->and(AppToken::ofType(ETokenTypeStub::Invitation)->pluck('value')->all())->toBe(['past'])
        ->and(AppToken::withValue('never')->count())->toBe(1);
});

it('reads the valid tokens of the model', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'api');
    $stub->addToken(ETokenTypeStub::Invitation, 'invitation');
    $stub->addToken(ETokenTypeStub::Invitation, 'expired', Carbon::now()->subDay());

    expect($stub->validTokens()->pluck('value')->all())->toEqualCanonicalizing(['api', 'invitation'])
        ->and($stub->validTokens(ETokenTypeStub::Invitation)->pluck('value')->all())->toBe(['invitation'])
        ->and($stub->expiredTokens()->pluck('value')->all())->toBe(['expired'])
        ->and($stub->expiredTokens(ETokenTypeStub::Api)->all())->toBe([]);
});

it('reads the newest valid token of a type', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'first');
    $stub->addToken(ETokenTypeStub::Api, 'second');
    $stub->addToken(ETokenTypeStub::Api, 'newest-but-expired', Carbon::now()->subMinute());

    expect($stub->token(ETokenTypeStub::Api)->value)->toBe('second')
        ->and($stub->tokenValue(ETokenTypeStub::Api))->toBe('second')
        ->and($stub->token(ETokenTypeStub::Invitation))->toBeNull()
        ->and($stub->tokenValue(ETokenTypeStub::Invitation))->toBeNull();
});

it('finds a token of the model by its value', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $other = TokenableStub::create(['name' => 'Other']);

    $stub->addToken(ETokenTypeStub::Api, 'secret');
    $stub->addToken(ETokenTypeStub::Reset, 'stale', Carbon::now()->subDay());
    $other->addToken(ETokenTypeStub::Api, 'theirs');

    expect($stub->findToken('secret')->value)->toBe('secret')
        ->and($stub->findToken('secret', ETokenTypeStub::Api))->not->toBeNull()
        ->and($stub->findToken('secret', ETokenTypeStub::Reset))->toBeNull()
        ->and($stub->findToken('stale'))->toBeNull()
        ->and($stub->findToken('stale', validOnly: false)->value)->toBe('stale')
        ->and($stub->findToken('theirs'))->toBeNull();
});

it('checks whether the model holds a token', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $other = TokenableStub::create(['name' => 'Other']);

    $stub->addToken(ETokenTypeStub::Api, 'secret');
    $stub->addToken(ETokenTypeStub::Reset, 'stale', Carbon::now()->subDay());
    $other->addToken(ETokenTypeStub::Invitation, 'theirs');

    expect($stub->hasToken(ETokenTypeStub::Api))->toBeTrue()
        ->and($stub->hasToken(ETokenTypeStub::Api, 'secret'))->toBeTrue()
        ->and($stub->hasToken(ETokenTypeStub::Api, 'other'))->toBeFalse()
        ->and($stub->hasToken(ETokenTypeStub::Reset))->toBeFalse()
        ->and($stub->hasToken(ETokenTypeStub::Invitation))->toBeFalse();
});

it('removes a token by its model', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $token = $stub->addToken(ETokenTypeStub::Api, 'secret');
    $stub->addToken(ETokenTypeStub::Api, 'kept');

    expect($stub->removeToken($token))->toBe(1)
        ->and($stub->tokens()->pluck('value')->all())->toBe(['kept']);
});

it('removes a token by its value', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'secret');
    $stub->addToken(ETokenTypeStub::Invitation, 'secret');

    expect($stub->removeToken('secret', ETokenTypeStub::Api))->toBe(1)
        ->and($stub->tokens()->pluck('type_eid')->all())->toBe([ETokenTypeStub::Invitation->value])
        ->and($stub->removeToken('secret'))->toBe(1)
        ->and($stub->tokens()->count())->toBe(0);
});

it('leaves the tokens of other models alone when removing', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $other = TokenableStub::create(['name' => 'Other']);

    $theirs = $other->addToken(ETokenTypeStub::Api, 'theirs');

    expect($stub->removeToken($theirs))->toBe(0)
        ->and($stub->removeToken('theirs'))->toBe(0)
        ->and($stub->removeTokens())->toBe(0)
        ->and(AppToken::count())->toBe(1);
});

it('removes every token, optionally of a single type', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'api');
    $stub->addToken(ETokenTypeStub::Invitation, 'invitation');
    $stub->addToken(ETokenTypeStub::Invitation, 'invitation-2');

    expect($stub->removeTokens(ETokenTypeStub::Invitation))->toBe(2)
        ->and($stub->tokens()->pluck('value')->all())->toBe(['api'])
        ->and($stub->removeTokens())->toBe(1)
        ->and($stub->tokens()->count())->toBe(0);
});

it('removes the expired tokens', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'never');
    $stub->addToken(ETokenTypeStub::Api, 'future', Carbon::now()->addDay());
    $stub->addToken(ETokenTypeStub::Api, 'past', Carbon::now()->subDay());

    expect($stub->removeExpiredTokens())->toBe(1)
        ->and($stub->tokens()->pluck('value')->all())->toEqualCanonicalizing(['never', 'future']);
});
