<?php

namespace Fnp\ElStart\Data;

use DateTimeInterface;

class PageModel
{
    /**
     * @param  string|null  $title  Page title, usually rendered in the title tag
     * @param  string|null  $description  Meta description
     * @param  array<int, string>  $keywords  Meta keywords
     * @param  string|null  $canonical  Canonical URL of the page
     * @param  string|null  $locale  Locale the page is served in
     * @param  string|null  $author  Content author
     * @param  string|null  $siteName  Name of the site the page belongs to
     * @param  bool  $noindex  Ask crawlers not to index the page
     * @param  bool  $nofollow  Ask crawlers not to follow the links
     * @param  string|null  $ogTitle  Open Graph title, falls back to the title
     * @param  string|null  $ogDescription  Open Graph description, falls back to the description
     * @param  string|null  $ogImage  Open Graph image URL
     * @param  string  $ogType  Open Graph object type
     * @param  string|null  $ogUrl  Open Graph URL, falls back to the canonical
     * @param  string  $twitterCard  Twitter card type
     * @param  string|null  $twitterSite  Twitter handle of the site
     * @param  string|null  $twitterCreator  Twitter handle of the author
     * @param  DateTimeInterface|null  $publishedAt  Date the content was published
     * @param  DateTimeInterface|null  $modifiedAt  Date the content was last modified
     * @param  array<string, string>  $meta  Any additional name/content meta pairs
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public array $keywords = [],
        public ?string $canonical = null,
        public ?string $locale = null,
        public ?string $author = null,
        public ?string $siteName = null,
        public bool $noindex = false,
        public bool $nofollow = false,
        public ?string $ogTitle = null,
        public ?string $ogDescription = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public ?string $ogUrl = null,
        public string $twitterCard = 'summary_large_image',
        public ?string $twitterSite = null,
        public ?string $twitterCreator = null,
        public ?DateTimeInterface $publishedAt = null,
        public ?DateTimeInterface $modifiedAt = null,
        public array $meta = [],
    ) {}

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Open Graph description with the meta description as a fallback.
     */
    public function resolveOgDescription(): ?string
    {
        return $this->ogDescription ?? $this->description;
    }

    /**
     * Open Graph title with the page title as a fallback.
     */
    public function resolveOgTitle(): ?string
    {
        return $this->ogTitle ?? $this->title;
    }

    /**
     * Open Graph URL with the canonical URL as a fallback.
     */
    public function resolveOgUrl(): ?string
    {
        return $this->ogUrl ?? $this->canonical;
    }

    /**
     * Value of the robots meta tag.
     */
    public function robots(): string
    {
        return implode(', ', [
            $this->noindex ? 'noindex' : 'index',
            $this->nofollow ? 'nofollow' : 'follow',
        ]);
    }

    /**
     * Every resolved value, ready to be handed over to a template.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'canonical' => $this->canonical,
            'locale' => $this->locale,
            'author' => $this->author,
            'site_name' => $this->siteName,
            'robots' => $this->robots(),
            'og_title' => $this->resolveOgTitle(),
            'og_description' => $this->resolveOgDescription(),
            'og_image' => $this->ogImage,
            'og_type' => $this->ogType,
            'og_url' => $this->resolveOgUrl(),
            'twitter_card' => $this->twitterCard,
            'twitter_site' => $this->twitterSite,
            'twitter_creator' => $this->twitterCreator,
            'published_at' => $this->publishedAt,
            'modified_at' => $this->modifiedAt,
            'meta' => $this->meta,
        ];
    }
}
