<?php

namespace App\Http\Controllers;

use App\Support\Seo;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * /robots.txt and the XML sitemaps. Only listed, indexable URLs: active + visible products in active categories,
 * active categories/subcategories that list at least one such product, and the public pages. Never account, checkout,
 * cart, order, search or filter URLs. Built from a few column-limited queries and cached for 10 minutes.
 */
class SeoController extends Controller
{
    /** URLs per product sitemap file (protocol limit is 50,000). */
    public const PRODUCTS_PER_FILE = 5000;

    private const CACHE_SECONDS = 600;

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            // Private and utility areas (also noindex + server-side authorization; robots.txt is not access control).
            'Disallow: /account',
            'Disallow: /checkout',
            'Disallow: /auth/',
            'Disallow: /size-guides/',
            // Search and brand-filter result pages (noindex); category pages stay crawlable.
            'Disallow: /*?*search=',
            'Disallow: /*?*brand=',
            'Allow: /',
            '',
            'Sitemap: '.Seo::abs('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** Sitemap index: the pages sitemap plus one file per PRODUCTS_PER_FILE products. */
    public function index(): Response
    {
        $files = Cache::store('file')->remember('seo.sitemap.index', self::CACHE_SECONDS, function () {
            $count = $this->listedProducts()->count();
            $lastmod = $this->listedProducts()->max('p.updated_at');
            $out = [['loc' => Seo::abs('/sitemap-pages.xml'), 'lastmod' => $lastmod]];
            for ($i = 1; $i <= max(1, (int) ceil($count / self::PRODUCTS_PER_FILE)); $i++) {
                $out[] = ['loc' => Seo::abs("/sitemap-products-{$i}.xml"), 'lastmod' => $lastmod];
            }

            return $out;
        });

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($files as $f) {
            $xml .= '  <sitemap><loc>'.$this->x($f['loc']).'</loc>'.($f['lastmod'] ? '<lastmod>'.$this->date($f['lastmod']).'</lastmod>' : '').'</sitemap>'."\n";
        }

        return $this->xml($xml.'</sitemapindex>'."\n");
    }

    public function pages(): Response
    {
        $urls = Cache::store('file')->remember('seo.sitemap.pages', self::CACHE_SECONDS, function () {
            $urls = [['loc' => '/'], ['loc' => '/products'], ['loc' => '/bulk-order'], ['loc' => '/request']];
            // Categories (and their subcategories) that actually list products: an empty listing is not worth indexing.
            $cats = $this->listedProducts()->groupBy('c.id', 'c.slug', 'c.sort_order')->orderBy('c.sort_order')->orderBy('c.id')
                ->get(['c.slug', DB::raw('MAX(p.updated_at) as lastmod')]);
            foreach ($cats as $c) {
                $urls[] = ['loc' => Seo::listingUrl($c->slug), 'lastmod' => $c->lastmod];
            }
            $subs = $this->listedProducts()->join('subcategories as s', 's.id', '=', 'p.subcategory_id')->where('s.status', 'active')
                ->whereColumn('s.category_id', 'c.id')
                ->groupBy('s.id', 'c.slug', 's.slug', 'c.sort_order', 's.sort_order')->orderBy('c.sort_order')->orderBy('s.sort_order')->orderBy('s.id')
                ->get(['c.slug as category', 's.slug', DB::raw('MAX(p.updated_at) as lastmod')]);
            foreach ($subs as $s) {
                $urls[] = ['loc' => Seo::listingUrl($s->category, $s->slug), 'lastmod' => $s->lastmod];
            }

            return $urls;
        });

        return $this->urlset($urls);
    }

    public function products(int $page): Response
    {
        abort_if($page < 1, 404);
        $urls = Cache::store('file')->remember("seo.sitemap.products.{$page}", self::CACHE_SECONDS, function () use ($page) {
            return $this->listedProducts()->orderBy('p.id')->offset(($page - 1) * self::PRODUCTS_PER_FILE)->limit(self::PRODUCTS_PER_FILE)
                ->get(['p.slug', 'p.updated_at'])
                ->map(fn ($p) => ['loc' => Seo::productUrl($p->slug), 'lastmod' => $p->updated_at])->all();
        });
        abort_if(! $urls && $page > 1, 404);

        return $this->urlset($urls);
    }

    /** Same eligibility as the storefront catalog (App\Models\Catalog::products) and the product page. */
    private function listedProducts()
    {
        return DB::table('products as p')->join('categories as c', 'c.id', '=', 'p.category_id')
            ->where('p.status', 'active')->where('p.visibility', 'visible')->where('c.status', 'active');
    }

    private function urlset(array $urls): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        $seen = [];
        foreach ($urls as $u) {
            $loc = Seo::abs($u['loc']);
            if (isset($seen[$loc])) {
                continue;
            }
            $seen[$loc] = true;
            $xml .= '  <url><loc>'.$this->x($loc).'</loc>'.(! empty($u['lastmod']) ? '<lastmod>'.$this->date($u['lastmod']).'</lastmod>' : '').'</url>'."\n";
        }

        return $this->xml($xml.'</urlset>'."\n");
    }

    private function x(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function date(string $ts): string
    {
        return substr(str_replace(' ', 'T', $ts), 0, 10);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=600', 'X-Robots-Tag' => 'noindex']);
    }
}
