<?php

namespace Fnp\ElStart\Data;

class SiteModel
{
    /**
     * @param  string|null  $name  Name of the site
     * @param  string|null  $tagline  Short tagline shown next to the name
     * @param  string|null  $description  Default description used when a page does not carry one
     * @param  string|null  $url  Base URL of the site
     * @param  string|null  $locale  Default locale the site is served in
     * @param  string|null  $logo  URL of the site logo
     * @param  string|null  $favicon  URL of the favicon
     * @param  string|null  $themeColor  Browser theme color
     * @param  string|null  $ogImage  Default social sharing image
     * @param  string|null  $email  Public contact email
     * @param  string|null  $phone  Public contact phone
     * @param  string|null  $address  Public postal address
     * @param  string|null  $owner  Legal owner, used in the copyright notice
     * @param  int|null  $copyrightSince  First year of the copyright range
     * @param  array<string, string>  $social  Social profile URLs keyed by network
     * @param  array<string, string>  $meta  Any additional name/content meta pairs
     */
    public function __construct(
        public ?string $name = null,
        public ?string $tagline = null,
        public ?string $description = null,
        public ?string $url = null,
        public ?string $locale = null,
        public ?string $logo = null,
        public ?string $favicon = null,
        public ?string $themeColor = null,
        public ?string $ogImage = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $address = null,
        public ?string $owner = null,
        public ?int $copyrightSince = null,
        public array $social = [],
        public array $meta = [],
    ) {}

    public static function make(mixed ...$arguments): static
    {
        return new static(...$arguments);
    }

    /**
     * Copyright notice for the given year, defaulting to the current one.
     */
    public function copyright(?int $year = null): string
    {
        $year ??= (int) date('Y');

        $years = $this->copyrightSince !== null && $this->copyrightSince !== $year
            ? $this->copyrightSince . '-' . $year
            : (string) $year;

        return implode(' ', array_filter(['©', $years, $this->owner ?? $this->name]));
    }

    /**
     * Every value, ready to be handed over to a template.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'url' => $this->url,
            'locale' => $this->locale,
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'theme_color' => $this->themeColor,
            'og_image' => $this->ogImage,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'owner' => $this->owner,
            'copyright_since' => $this->copyrightSince,
            'social' => $this->social,
            'meta' => $this->meta,
        ];
    }
}
