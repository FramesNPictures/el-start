<?php

use Fnp\ElStart\Data\PageModel;

it('is autoloaded', function (): void {
    expect(function_exists('page'))->toBeTrue();
});

it('returns the page model', function (): void {
    expect(page())->toBeInstanceOf(PageModel::class);
});

it('returns the shared instance', function (): void {
    page()->title = 'About us';

    expect(page())->toBe(app(PageModel::class))
        ->and(app(PageModel::class)->title)->toBe('About us');
});
