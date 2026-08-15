<?php

use Fnp\ElStart\Data\SiteModel;

it('resolves the site model out of the container', function (): void {
    expect(app(SiteModel::class))->toBeInstanceOf(SiteModel::class);
});

it('shares a single instance', function (): void {
    $site = app(SiteModel::class);
    $site->name = 'Example';

    expect(app(SiteModel::class))->toBe($site)
        ->and(app(SiteModel::class)->name)->toBe('Example');
});

it('resolves with the constructor defaults', function (): void {
    $site = app(SiteModel::class);

    expect($site->name)->toBeNull()
        ->and($site->social)->toBe([])
        ->and($site->copyrightSince)->toBeNull();
});

it('has an autoloaded site helper', function (): void {
    expect(function_exists('site'))->toBeTrue();
});

it('returns the shared instance from the helper', function (): void {
    site()->name = 'Example';

    expect(site())->toBe(app(SiteModel::class))
        ->and(app(SiteModel::class)->name)->toBe('Example');
});

it('keeps the page and site models apart', function (): void {
    expect(site())->not->toBe(page());
});
