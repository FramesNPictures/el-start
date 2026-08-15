<?php

use Fnp\ElStart\Data\SiteModel;
use Fnp\ElStart\Tests\Stubs\SiteStubModule;

it('fills the shared site model when the module boots', function (): void {
    expect(site()->name)->toBeNull();

    app()->register(SiteStubModule::class);

    expect(site()->name)->toBe('Example')
        ->and(site()->url)->toBe('https://example.test')
        ->and(site()->owner)->toBe('Example Ltd')
        ->and(site()->social)->toBe(['x' => 'https://x.com/example']);
});

it('fills the very instance held by the container', function (): void {
    $site = app(SiteModel::class);

    app()->register(SiteStubModule::class);

    expect($site->name)->toBe('Example')
        ->and(app(SiteModel::class))->toBe($site);
});

it('leaves the site untouched without the feature', function (): void {
    expect(site()->name)->toBeNull()
        ->and(site()->owner)->toBeNull();
});
