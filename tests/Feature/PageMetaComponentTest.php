<?php

use Illuminate\Support\Facades\Blade;

it('renders the component through the package namespace', function (): void {
    page()->title = 'About us';
    site()->name = 'Example';

    expect(Blade::render('<x-el-start::page-meta />'))
        ->toContain('<title>About us - Example</title>');
});

it('renders the page metadata', function (): void {
    page()->title = 'About us';
    page()->description = 'Who we are';
    page()->keywords = ['about', 'company'];
    page()->author = 'Chris';
    page()->canonical = 'https://example.test/about';
    page()->noindex = true;

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="description" content="Who we are">')
        ->toContain('<meta name="keywords" content="about, company">')
        ->toContain('<meta name="author" content="Chris">')
        ->toContain('<meta name="robots" content="noindex, follow">')
        ->toContain('<link rel="canonical" href="https://example.test/about">');
});

it('renders the site metadata', function (): void {
    site()->name = 'Example';
    site()->themeColor = '#000000';
    site()->favicon = 'https://example.test/favicon.ico';

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="theme-color" content="#000000">')
        ->toContain('<link rel="icon" href="https://example.test/favicon.ico">')
        ->toContain('<meta property="og:site_name" content="Example">');
});

it('falls back from the page to the site', function (): void {
    site()->description = 'The default description';
    site()->ogImage = 'https://example.test/og.jpg';
    site()->url = 'https://example.test';
    site()->locale = 'en_GB';

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="description" content="The default description">')
        ->toContain('<meta property="og:image" content="https://example.test/og.jpg">')
        ->toContain('<meta property="og:url" content="https://example.test">')
        ->toContain('<meta property="og:locale" content="en_GB">');
});

it('prefers the page values over the site ones', function (): void {
    site()->description = 'The default description';
    site()->ogImage = 'https://example.test/og.jpg';
    page()->description = 'Who we are';
    page()->ogImage = 'https://example.test/about.jpg';

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="description" content="Who we are">')
        ->toContain('<meta property="og:image" content="https://example.test/about.jpg">')
        ->not->toContain('The default description');
});

it('mirrors the open graph values onto the twitter card', function (): void {
    page()->title = 'About us';
    page()->description = 'Who we are';
    page()->ogImage = 'https://example.test/about.jpg';

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="twitter:card" content="summary_large_image">')
        ->toContain('<meta name="twitter:title" content="About us">')
        ->toContain('<meta name="twitter:description" content="Who we are">')
        ->toContain('<meta name="twitter:image" content="https://example.test/about.jpg">');
});

it('renders the article timestamps', function (): void {
    page()->publishedAt = new DateTimeImmutable('2026-08-15 10:00:00', new DateTimeZone('UTC'));

    expect(Blade::render('<x-el-start::page-meta />'))
        ->toContain('<meta property="article:published_time" content="2026-08-15T10:00:00+00:00">');
});

it('merges the extra meta pairs with the page winning', function (): void {
    site()->meta = ['generator' => 'el-start', 'rating' => 'general'];
    page()->meta = ['generator' => 'custom'];

    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->toContain('<meta name="rating" content="general">')
        ->toContain('<meta name="generator" content="custom">')
        ->not->toContain('content="el-start"');
});

it('escapes the values', function (): void {
    page()->title = 'About "us" & them';

    expect(Blade::render('<x-el-start::page-meta />'))
        ->toContain('About &quot;us&quot; &amp; them')
        ->not->toContain('About "us" & them');
});

it('omits the tags it has no values for', function (): void {
    $html = Blade::render('<x-el-start::page-meta />');

    expect($html)
        ->not->toContain('<title>')
        ->not->toContain('name="description"')
        ->not->toContain('name="keywords"')
        ->not->toContain('rel="canonical"')
        ->toContain('<meta name="robots" content="index, follow">');
});
