<?php

use Fnp\ElStart\Data\PageModel;

it('resolves the page model out of the container', function (): void {
    expect(app(PageModel::class))->toBeInstanceOf(PageModel::class);
});

it('shares a single instance', function (): void {
    $page = app(PageModel::class);
    $page->title = 'About us';

    expect(app(PageModel::class))->toBe($page)
        ->and(app(PageModel::class)->title)->toBe('About us');
});

it('resolves with the constructor defaults', function (): void {
    $page = app(PageModel::class);

    expect($page->title)->toBeNull()
        ->and($page->keywords)->toBe([])
        ->and($page->publishedAt)->toBeNull()
        ->and($page->ogType)->toBe('website');
});
