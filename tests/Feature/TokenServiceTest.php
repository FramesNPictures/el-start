<?php

use Fnp\ElStart\Services\TokenService;
use Fnp\ElStart\Tests\Stubs\ETokenTypeStub;
use Fnp\ElStart\Tests\Stubs\TokenableStub;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create(TokenableStub::TABLE, function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists(TokenableStub::TABLE);
});

it('resolves out of the container as a single instance', function (): void {
    expect(app(TokenService::class))->toBeInstanceOf(TokenService::class)
        ->and(app(TokenService::class))->toBe(app(TokenService::class));
});

it('has an autoloaded token helper', function (): void {
    expect(function_exists('token'))->toBeTrue()
        ->and(token())->toBe(app(TokenService::class));
});

it('finds a token by its value', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $token = $stub->addToken(ETokenTypeStub::Api, 'secret');

    expect(token()->find('secret')->id)->toBe($token->id)
        ->and(token()->find('nothing'))->toBeNull();
});

it('finds a token of any model', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $other = TokenableStub::create(['name' => 'Other']);

    $stub->addToken(ETokenTypeStub::Api, 'mine');
    $other->addToken(ETokenTypeStub::Api, 'theirs');

    expect(token()->find('mine')->tokenable_id)->toBe($stub->id)
        ->and(token()->find('theirs')->tokenable_id)->toBe($other->id);
});

it('limits the lookup to a single type', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Api, 'secret');

    expect(token()->find('secret', ETokenTypeStub::Api))->not->toBeNull()
        ->and(token()->find('secret', ETokenTypeStub::Invitation))->toBeNull();
});

it('skips the expired tokens unless asked otherwise', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Reset, 'stale', Carbon::now()->subDay());

    expect(token()->find('stale'))->toBeNull()
        ->and(token()->find('stale', validOnly: false)->value)->toBe('stale');
});

it('returns the newest token when the value repeats', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);

    $stub->addToken(ETokenTypeStub::Api, 'secret');
    $newest = $stub->addToken(ETokenTypeStub::Api, 'secret');

    expect(token()->find('secret')->id)->toBe($newest->id);
});

it('finds the model a token belongs to', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Api, 'secret');

    $target = token()->findTarget('secret');

    expect($target)->toBeInstanceOf(TokenableStub::class)
        ->and($target->id)->toBe($stub->id)
        ->and($target->name)->toBe('Example');
});

it('returns no target for an unknown, expired or mistyped token', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Reset, 'stale', Carbon::now()->subDay());

    expect(token()->findTarget('nothing'))->toBeNull()
        ->and(token()->findTarget('stale'))->toBeNull()
        ->and(token()->findTarget('stale', ETokenTypeStub::Api, validOnly: false))->toBeNull()
        ->and(token()->findTarget('stale', ETokenTypeStub::Reset, validOnly: false))
        ->toBeInstanceOf(TokenableStub::class);
});

it('returns no target when the model is gone', function (): void {
    $stub = TokenableStub::create(['name' => 'Example']);
    $stub->addToken(ETokenTypeStub::Api, 'secret');
    $stub->delete();

    expect(token()->find('secret'))->not->toBeNull()
        ->and(token()->findTarget('secret'))->toBeNull();
});
