<?php

use Fnp\ElStart\Models\AppAudit;
use Fnp\ElStart\Tests\Stubs\AuditableStubEvent;
use Fnp\ElStart\Tests\Stubs\PlainStubEvent;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

afterEach(function (): void {
    // Relation::$morphMap is static and would otherwise leak between tests.
    Relation::morphMap([], false);
});

it('has the audit table', function (): void {
    expect(Schema::hasTable(AppAudit::TABLE))->toBeTrue()
        ->and(Schema::getColumnListing(AppAudit::TABLE))
        ->toEqualCanonicalizing(['id', 'event', 'user_id', 'payload', 'created_at']);
});

it('records an auditable event', function (): void {
    Event::dispatch(new AuditableStubEvent(['order' => 15]));

    $entry = AppAudit::first();

    expect($entry)->not->toBeNull()
        ->and($entry->event)->toBe(AuditableStubEvent::class)
        ->and($entry->payload)->toBe(['order' => 15])
        ->and($entry->created_at)->not->toBeNull();
});

it('leaves the user empty when nobody is logged in', function (): void {
    Event::dispatch(new AuditableStubEvent(['a' => 1]));

    expect(AppAudit::first()->user_id)->toBeNull();
});

it('ignores events that are not auditable', function (): void {
    Event::dispatch(new PlainStubEvent());
    Event::dispatch('some.string.event');

    expect(AppAudit::count())->toBe(0);
});

it('stores the class map alias when one is registered', function (): void {
    Relation::morphMap(['stub-event' => AuditableStubEvent::class]);

    Event::dispatch(new AuditableStubEvent(['a' => 1]));

    expect(AppAudit::first()->event)->toBe('stub-event');
});

it('stores the id of the authenticated user', function (): void {
    Auth::shouldReceive('check')->andReturnTrue();
    Auth::shouldReceive('id')->andReturn(42);

    Event::dispatch(new AuditableStubEvent(['a' => 1]));

    expect(AppAudit::first()->user_id)->toBe(42);
});

it('logs an entry it cannot write instead of breaking the dispatch', function (): void {
    Log::spy();
    Schema::drop(AppAudit::TABLE);

    Event::dispatch(new AuditableStubEvent(['a' => 1]));

    Log::shouldHaveReceived('debug')->withArgs(
        fn (string $message, array $context): bool => str_starts_with($message, '[APP] ')
            && str_contains($message, AuditableStubEvent::class)
            && $context['exception'] instanceof Throwable,
    );
});

it('keeps the payload out of the log', function (): void {
    Log::spy();
    Schema::drop(AppAudit::TABLE);

    Event::dispatch(new AuditableStubEvent(['card' => 'the secret itself']));

    Log::shouldHaveReceived('debug')->withArgs(
        fn (string $message): bool => ! str_contains($message, 'the secret itself'),
    );
});
