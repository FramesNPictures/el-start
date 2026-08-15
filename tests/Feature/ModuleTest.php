<?php

use Fnp\ElStart\ElStartModule;
use Illuminate\Support\Facades\Schema;

it('can load the module', function (): void {
    expect(app()->getLoadedProviders())->toHaveKey(ElStartModule::class);
});

it('has migrations', function (): void {
    expect(Schema::hasTable('app_sessions'))->toBeTrue()
        ->and(Schema::hasTable('app_cache'))->toBeTrue()
        ->and(Schema::hasTable('app_cache_locks'))->toBeTrue()
        ->and(Schema::hasTable('app_jobs'))->toBeTrue()
        ->and(Schema::hasTable('app_jobs_batches'))->toBeTrue()
        ->and(Schema::hasTable('app_jobs_failed'))->toBeTrue();
});

it('overrides config', function (): void {
    expect(config('queue.connections.database.table'))->toBe('app_jobs')
        ->and(config('queue.failed.table'))->toBe('app_jobs_failed')
        ->and(config('queue.batching.table'))->toBe('app_jobs_batches')
        ->and(config('cache.stores.database.table'))->toBe('app_cache')
        ->and(config('cache.stores.database.lock_table'))->toBe('app_cache_locks')
        ->and(config('database.migrations.table'))->toBe('app_migrations')
        ->and(config('session.table'))->toBe('app_sessions');
});
