@php
    $page = page();
    $site = site();

    $title = trim(implode(' - ', array_filter([$page->title, $site->name])));
    $description = $page->description ?? $site->description;
    $ogTitle = $page->resolveOgTitle() ?? $site->name;
    $ogDescription = $page->resolveOgDescription() ?? $site->description;
    $ogImage = $page->ogImage ?? $site->ogImage;
    $ogUrl = $page->resolveOgUrl() ?? $site->url;
    $locale = $page->locale ?? $site->locale;
@endphp
@if ($title)
<title>{{ $title }}</title>
@endif
@if ($description)
<meta name="description" content="{{ $description }}">
@endif
@if ($page->keywords)
<meta name="keywords" content="{{ implode(', ', $page->keywords) }}">
@endif
@if ($page->author)
<meta name="author" content="{{ $page->author }}">
@endif
<meta name="robots" content="{{ $page->robots() }}">
@if ($site->themeColor)
<meta name="theme-color" content="{{ $site->themeColor }}">
@endif
@if ($page->canonical)
<link rel="canonical" href="{{ $page->canonical }}">
@endif
@if ($site->favicon)
<link rel="icon" href="{{ $site->favicon }}">
@endif
@if ($ogTitle)
<meta property="og:title" content="{{ $ogTitle }}">
@endif
@if ($ogDescription)
<meta property="og:description" content="{{ $ogDescription }}">
@endif
@if ($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif
<meta property="og:type" content="{{ $page->ogType }}">
@if ($ogUrl)
<meta property="og:url" content="{{ $ogUrl }}">
@endif
@if ($site->name)
<meta property="og:site_name" content="{{ $site->name }}">
@endif
@if ($locale)
<meta property="og:locale" content="{{ $locale }}">
@endif
@if ($page->publishedAt)
<meta property="article:published_time" content="{{ $page->publishedAt->format(DATE_ATOM) }}">
@endif
@if ($page->modifiedAt)
<meta property="article:modified_time" content="{{ $page->modifiedAt->format(DATE_ATOM) }}">
@endif
<meta name="twitter:card" content="{{ $page->twitterCard }}">
@if ($page->twitterSite)
<meta name="twitter:site" content="{{ $page->twitterSite }}">
@endif
@if ($page->twitterCreator)
<meta name="twitter:creator" content="{{ $page->twitterCreator }}">
@endif
@if ($ogTitle)
<meta name="twitter:title" content="{{ $ogTitle }}">
@endif
@if ($ogDescription)
<meta name="twitter:description" content="{{ $ogDescription }}">
@endif
@if ($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif
@foreach (array_merge($site->meta, $page->meta) as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
