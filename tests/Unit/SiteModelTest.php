<?php

use Fnp\ElStart\Data\SiteModel;

it('defaults to an empty site', function (): void {
    $site = new SiteModel();

    expect($site->name)->toBeNull()
        ->and($site->url)->toBeNull()
        ->and($site->copyrightSince)->toBeNull()
        ->and($site->social)->toBe([])
        ->and($site->meta)->toBe([]);
});

it('takes named arguments', function (): void {
    $site = SiteModel::make(
        name: 'Example',
        tagline: 'We make things',
        url: 'https://example.test',
        social: ['x' => 'https://x.com/example'],
    );

    expect($site->name)->toBe('Example')
        ->and($site->tagline)->toBe('We make things')
        ->and($site->url)->toBe('https://example.test')
        ->and($site->social)->toBe(['x' => 'https://x.com/example']);
});

it('builds a single year copyright notice', function (): void {
    $site = new SiteModel(owner: 'Example Ltd');

    expect($site->copyright(2026))->toBe('© 2026 Example Ltd');
});

it('builds a copyright range when the site is older', function (): void {
    $site = new SiteModel(owner: 'Example Ltd', copyrightSince: 2020);

    expect($site->copyright(2026))->toBe('© 2020-2026 Example Ltd');
});

it('collapses the copyright range in its first year', function (): void {
    $site = new SiteModel(owner: 'Example Ltd', copyrightSince: 2026);

    expect($site->copyright(2026))->toBe('© 2026 Example Ltd');
});

it('falls back to the site name in the copyright notice', function (): void {
    expect((new SiteModel(name: 'Example'))->copyright(2026))->toBe('© 2026 Example')
        ->and((new SiteModel())->copyright(2026))->toBe('© 2026');
});

it('defaults the copyright to the current year', function (): void {
    $site = new SiteModel(owner: 'Example Ltd');

    expect($site->copyright())->toBe('© ' . date('Y') . ' Example Ltd');
});

it('resolves everything into an array for the template', function (): void {
    $site = new SiteModel(
        name: 'Example',
        tagline: 'We make things',
        url: 'https://example.test',
        themeColor: '#000000',
        owner: 'Example Ltd',
        copyrightSince: 2020,
        social: ['x' => 'https://x.com/example'],
        meta: ['generator' => 'el-start'],
    );

    expect($site->toArray())->toBe([
        'name' => 'Example',
        'tagline' => 'We make things',
        'description' => null,
        'url' => 'https://example.test',
        'locale' => null,
        'logo' => null,
        'favicon' => null,
        'theme_color' => '#000000',
        'og_image' => null,
        'email' => null,
        'phone' => null,
        'address' => null,
        'owner' => 'Example Ltd',
        'copyright_since' => 2020,
        'social' => ['x' => 'https://x.com/example'],
        'meta' => ['generator' => 'el-start'],
    ]);
});
