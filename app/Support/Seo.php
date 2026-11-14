<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * SEO helpers: absolute canonical URLs, plain-text meta descriptions and JSON-LD built only from real catalog data.
 * Absolute URLs use config('app.url') (never the request Host header, which a client controls), so APP_URL must be the
 * public https origin in production.
 */
class Seo
{
    public const DESCRIPTION_LIMIT = 158;

    public static function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /** Absolute URL for a site path ("/products?category=x") or an already absolute http(s) URL. */
    public static function abs(?string $pathOrUrl): ?string
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $pathOrUrl)) {
            return $pathOrUrl;
        }
        if (str_starts_with($pathOrUrl, '//') || ! str_starts_with($pathOrUrl, '/')) {
            return null;
        }

        return self::base().$pathOrUrl;
    }

    /** Plain-text, single-line, length-limited description from admin text (which may hold HTML). */
    public static function text(?string $html, int $limit = self::DESCRIPTION_LIMIT): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], ' ', (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return Str::limit($text, $limit, '…', preserveWords: true);
    }

    public static function productUrl(string $slug): string
    {
        return '/products/'.rawurlencode($slug);
    }

    /** Stable listing URL: only category, then subcategory, in a fixed order (no sort, filters, tracking or paging). */
    public static function listingUrl(?string $category = null, ?string $subcategory = null): string
    {
        if (! $category) {
            return '/products';
        }
        $q = ['category' => $category];
        if ($subcategory) {
            $q['subcategory'] = $subcategory;
        }

        return '/products?'.http_build_query($q, '', '&', PHP_QUERY_RFC3986);
    }

    /** <script type="application/ld+json"> body: cannot close the script tag or break out of it. */
    public static function jsonLd(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    public static function organization(array $social = []): array
    {
        $org = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => self::base().'/#organization',
            'name' => config('storefront.brand'),
            'url' => self::base().'/',
            'logo' => self::abs('/assets/logos/logo.png'),
        ];
        $same = array_values(array_filter($social, fn ($u) => is_string($u) && preg_match('#^https://#i', $u)));
        if ($same) {
            $org['sameAs'] = $same;
        }
        // Only details the owner configured (config_data contact_details); nothing is emitted while they are empty.
        $c = StoreConfig::get('contact_details');
        $contact = array_filter([
            'email' => trim((string) $c['email']),
            'telephone' => trim((string) $c['phone']),
        ]);
        if ($contact) {
            $org['contactPoint'] = ['@type' => 'ContactPoint', 'contactType' => 'customer service'] + $contact + ['areaServed' => config('storefront.country_code')];
        }

        return $org;
    }

    /** ContactPage/WebPage for an approved information page (drafts get no structured data). */
    public static function webPage(string $type, string $path, string $name, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $type,
            'name' => $name,
            'url' => self::abs($path),
            'description' => $description,
            'inLanguage' => 'en-AU',
            'isPartOf' => ['@id' => self::base().'/#website'],
        ];
    }

    /**
     * CollectionPage + ItemList for one listing page: exactly the products rendered on that page, in order, with their
     * position in the whole listing (so page 2 starts after page 1).
     *
     * @param  array<int, array{slug:string, name:string}>  $products
     */
    public static function collectionPage(string $canonical, string $name, string $description, array $products, int $offset, int $total): array
    {
        $items = [];
        foreach (array_values($products) as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $offset + $i + 1, 'url' => self::abs(self::productUrl($p['slug'])), 'name' => $p['name']];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $canonical,
            'description' => $description,
            'inLanguage' => 'en-AU',
            'isPartOf' => ['@id' => self::base().'/#website'],
            'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $total, 'itemListElement' => $items],
        ];
    }

    /**
     * Shipping for one unit, from the owner-confirmed rule only (Part 6): Australia only, the flat fee below the threshold, free
     * at or above it. No delivery time (none approved) and no carrier.
     */
    private static function shipping(float $price): array
    {
        return [
            '@type' => 'OfferShippingDetails',
            'shippingRate' => ['@type' => 'MonetaryAmount', 'value' => self::money(Delivery::freeShipping($price) ? 0.0 : Shipping::fee(Shipping::AUSTRALIA)), 'currency' => 'AUD'],
            'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => config('storefront.country_code')],
        ];
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => self::base().'/#website',
            'name' => config('storefront.brand'),
            'url' => self::base().'/',
            'inLanguage' => 'en-AU',
            'publisher' => ['@id' => self::base().'/#organization'],
        ];
    }

    /** @param  array<int, array{0:string,1:?string}>  $crumbs  [name, path|null for the current page] */
    public static function breadcrumbs(array $crumbs): array
    {
        $items = [];
        foreach (array_values($crumbs) as $i => [$name, $path]) {
            $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name];
            if ($path !== null) {
                $item['item'] = self::abs($path);
            }
            $items[] = $item;
        }

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    /**
     * Product + Offer from the storefront product shape (App\Models\Catalog). Availability reflects whether the product
     * can be ordered on the site (status/visibility/admin availability flag), never the numeric stock count, which is not
     * enforced. No rating, review, condition or GTIN is emitted: the catalog does not hold verified values for them.
     */
    public static function product(array $p): array
    {
        $url = self::abs(self::productUrl($p['slug']));
        $images = array_values(array_filter(array_map(fn ($u) => self::abs($u), array_slice($p['images'] ?? [], 0, 10))));

        $availability = match (true) {
            ($p['availability'] ?? null) === 'preorder' => 'https://schema.org/PreOrder',
            (bool) ($p['soldOut'] ?? false) => 'https://schema.org/OutOfStock',
            default => 'https://schema.org/InStock',
        };
        $prices = array_values(array_unique(array_map(fn ($v) => round((float) $v['price'], 2), $p['variants'] ?? [])));
        $base = ['priceCurrency' => 'AUD', 'availability' => $availability, 'url' => $url];
        if (! empty($p['hasVariants']) && count($prices) > 1) {
            $offers = ['@type' => 'AggregateOffer', 'lowPrice' => self::money(min($prices)), 'highPrice' => self::money(max($prices)), 'offerCount' => count($p['variants'])] + $base;
        } else {
            $price = (! empty($p['hasVariants']) && $prices) ? $prices[0] : (float) $p['price'];
            // One price: the shipping cost for one unit is known exactly. (A price range can straddle the free-shipping
            // threshold, so AggregateOffer gets no shipping details.)
            $offers = ['@type' => 'Offer', 'price' => self::money($price)] + $base + ['shippingDetails' => self::shipping($price)];
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            '@id' => $url.'#product',
            'name' => $p['name'],
            'url' => $url,
            'description' => self::text(($p['fullDesc'] ?? '') ?: ($p['desc'] ?? ''), 5000) ?: null,
            'image' => $images ?: null,
            'sku' => $p['sku'] ?: null,
            'brand' => ! empty($p['brand']) ? ['@type' => 'Brand', 'name' => $p['brand']] : null,
            'category' => $p['categoryName'] ?? null,
            'offers' => $offers,
        ];

        return array_filter($data, fn ($v) => $v !== null && $v !== '');
    }

    private static function money(float $v): string
    {
        return number_format($v, 2, '.', '');
    }
}
