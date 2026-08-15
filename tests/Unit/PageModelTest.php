<?php

use Fnp\ElStart\Data\PageModel;

it('defaults to an empty page', function (): void {
    $page = new PageModel();

    expect($page->title)->toBeNull()
        ->and($page->description)->toBeNull()
        ->and($page->keywords)->toBe([])
        ->and($page->meta)->toBe([])
        ->and($page->noindex)->toBeFalse()
        ->and($page->nofollow)->toBeFalse()
        ->and($page->ogType)->toBe('website')
        ->and($page->twitterCard)->toBe('summary_large_image');
});

it('takes named arguments', function (): void {
    $page = PageModel::make(
        title: 'About us',
        description: 'Who we are',
        keywords: ['about', 'company'],
        canonical: 'https://example.test/about',
    );

    expect($page->title)->toBe('About us')
        ->and($page->description)->toBe('Who we are')
        ->and($page->keywords)->toBe(['about', 'company'])
        ->and($page->canonical)->toBe('https://example.test/about');
});

it('falls back to the page values for open graph', function (): void {
    $page = new PageModel(
        title: 'About us',
        description: 'Who we are',
        canonical: 'https://example.test/about',
    );

    expect($page->resolveOgTitle())->toBe('About us')
        ->and($page->resolveOgDescription())->toBe('Who we are')
        ->and($page->resolveOgUrl())->toBe('https://example.test/about');
});

it('prefers explicit open graph values over the fallbacks', function (): void {
    $page = new PageModel(
        title: 'About us',
        description: 'Who we are',
        canonical: 'https://example.test/about',
        ogTitle: 'About our company',
        ogDescription: 'The long story',
        ogUrl: 'https://example.test/about?utm_source=og',
    );

    expect($page->resolveOgTitle())->toBe('About our company')
        ->and($page->resolveOgDescription())->toBe('The long story')
        ->and($page->resolveOgUrl())->toBe('https://example.test/about?utm_source=og');
});

it('builds the robots value', function (): void {
    expect((new PageModel())->robots())->toBe('index, follow')
        ->and((new PageModel(noindex: true))->robots())->toBe('noindex, follow')
        ->and((new PageModel(nofollow: true))->robots())->toBe('index, nofollow')
        ->and((new PageModel(noindex: true, nofollow: true))->robots())->toBe('noindex, nofollow');
});

it('resolves everything into an array for the template', function (): void {
    $published = new DateTimeImmutable('2026-08-15 10:00:00');

    $page = new PageModel(
        title: 'About us',
        description: 'Who we are',
        canonical: 'https://example.test/about',
        siteName: 'Example',
        noindex: true,
        ogImage: 'https://example.test/og.jpg',
        publishedAt: $published,
        meta: ['theme-color' => '#000000'],
    );

    expect($page->toArray())->toBe([
        'title' => 'About us',
        'description' => 'Who we are',
        'keywords' => [],
        'canonical' => 'https://example.test/about',
        'locale' => null,
        'author' => null,
        'site_name' => 'Example',
        'robots' => 'noindex, follow',
        'og_title' => 'About us',
        'og_description' => 'Who we are',
        'og_image' => 'https://example.test/og.jpg',
        'og_type' => 'website',
        'og_url' => 'https://example.test/about',
        'twitter_card' => 'summary_large_image',
        'twitter_site' => null,
        'twitter_creator' => null,
        'published_at' => $published,
        'modified_at' => null,
        'meta' => ['theme-color' => '#000000'],
    ]);
});
