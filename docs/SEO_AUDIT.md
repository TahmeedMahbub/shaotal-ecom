# Shaotal storefront: SEO audit and architecture

_Part 7 (2026-11-12). Builds on Part 4 (STOREFRONT_PRODUCTION_READINESS_PLAN.md §12) and records the Part 7 audit, changes and evidence (plan §19). Every number here was measured **locally** (synthetic, headless Chrome, local `*_test` databases). This is not field data, and **nothing here has been checked in Google Search Console**: the site isn't live and nobody has Search Console access yet._

Status labels used below:
- **Verified:** automated test plus a browser or live-server check.
- **Implemented:** code and test, not fully verified (needs production or external tools).
- **Blocked:** needs code or content that doesn't exist yet.
- **Pending:** needs owner approval or external access.

---

## 1. Architecture in one page

| Concern | Where | Rule |
|---|---|---|
| Absolute URLs | `App\Support\Seo::abs()` | Built from `APP_URL`, **never** from the request's `Host` header. `APP_URL` must be the public `https://` origin. `storefront:deploy-check` fails on `http`, `localhost`, `127.*`, `*.test` and `*.local`. |
| Title, description, robots, canonical, Open Graph, Twitter | `layouts/store.blade.php`, from view data (`pageTitle`, `pageDescription`, `robots`, `canonical`, `ogImage`, `jsonLd`) | Escaped once. No canonical on `noindex` pages. |
| Structured data | `App\Support\Seo` | JSON-LD only, `JSON_HEX_TAG\|JSON_HEX_AMP`, one block per type per page, built only from database values or confirmed business rules. |
| Sitemaps | `SeoController` | `/sitemap.xml` index → `/sitemap-pages.xml` + `/sitemap-products-{n}.xml` (5,000 per file, with images). Cached for 10 minutes. |
| `robots.txt` | `SeoController::robots` | Dynamic. Keep `public/robots.txt` absent (deploy-check fails if it exists). |
| URL normalisation | `RedirectTrailingSlash` (global middleware), `PageController::product` | Trailing slash → 301. Slug case → 301. Unknown → 404. |
| Server-rendered content | Blade views | Everything search engines need is in the HTML before JavaScript runs. The scripts take over the same markup. |

## 2. Page-type indexing policy

| Page | Robots | Canonical | Structured data | In sitemap |
|---|---|---|---|---|
| Home `/` | index | `/` | Organization (contactPoint only when `STOREFRONT_CONTACT_*` is set), WebSite | yes |
| All products `/products` | index | `/products` (`?page=N` for page N) | BreadcrumbList, CollectionPage + ItemList | page 1 |
| Category `/products?category=x` | index | Same, plus `&page=N` | BreadcrumbList, CollectionPage + ItemList | page 1, only if it lists products |
| Subcategory `…&subcategory=y` (under its own category) | index | Same | Same | page 1, only if it lists products |
| Sort, `utm_*`, `gclid` variants | index | The clean URL (parameters dropped) | — | no |
| Search `?search=`, brand `?brand=`, pre-order view, unknown category or subcategory | **noindex, follow** | none | none | no |
| Product `/products/{slug}` | index | Lower-case slug (other case → 301) | Product + Offer/AggregateOffer, BreadcrumbList | yes, with main image |
| Bulk order, Request sourcing | index | self | — | yes |
| Contact, Shipping, Returns, Terms, Privacy | **noindex** while draft. Index once listed in `STOREFRONT_APPROVED_PAGES` | Only when approved | ContactPage / WebPage, only when approved | only when approved |
| Account, orders, order details, checkout | **noindex, nofollow** (meta + `X-Robots-Tag`), `private` caching, server-side access control | none | none | no |
| Sign-in and registration | Modals on public pages, not separate URLs. `/auth/*` endpoints are JSON and disallowed. | — | — | no |
| Cart and wishlist | Drawers, not URLs | — | — | no |
| 404 / 419 / 429 / 500 / 503 | noindex, real status code | none | none | no |
| `/catalog/*`, `/size-guides/*` (JSON) | `X-Robots-Tag: noindex` | — | — | no |

**About / Our Story:** no page exists, because no approved content exists (the home page has two sentences). **Blocked (content).** Don't create a thin page to fill the gap.

## 3. Canonical URL strategy
- **One host:** whatever `APP_URL` is. Choose www or non-www once, redirect the other at the web server or DNS (301), and use the same choice in Search Console.
- **HTTPS only:**
  - production forces `https` URLs (`URL::forceHttps`);
  - the web server must 301 `http` → `https` (deployment checklist §0.2);
  - CSP `upgrade-insecure-requests` applies in production.
- **No trailing slash:**
  - `RedirectTrailingSlash` 301s `GET`/`HEAD` `/path/` → `/path`, keeping the query string, on any web server;
  - Apache's `.htaccess` rule still exists and does the same;
  - the redirect target is always built from `APP_URL` and a collapsed path, so `//evil.example/` can't become an open redirect (mutation-tested).
- **Case:** product slugs are matched case-insensitively and 301 to the stored slug. Other paths are case-sensitive and return a real 404 (no redirect to home).
- **Listing parameters:**
  - only `category`, then `subcategory`, then `page` (> 1) form a canonical URL, in that fixed order;
  - `page=1`, `page=0`, `page=abc`, `page=02` and array values all canonicalise to the page-1 URL;
  - a page past the end returns **404** (no soft-404 empty pages).
- **Paginated pages are self-canonical (not pointed at page 1).** Pointing page 2 and beyond at page 1 would tell Google to ignore the products only listed there.

## 4. Product discovery and internal linking

| Path to a product | How |
|---|---|
| Home → category | Server-rendered navigation, mega-menu, "Shop by Category" and footer links |
| Category → products | Every listing page's grid links its products. Page links (`Previous / 1 … 5 6 7 8 9 … 17 / Next`) make every page reachable without JavaScript. |
| Product → product | "You May Also Like" shows the **next 4 products in the same category** (wrapping round), so each product is linked from the four before it |
| Product → category | Visible breadcrumb and BreadcrumbList |
| Subcategory → category | The visible breadcrumb now includes the category level (it matches the JSON-LD) |

How page links look to visitors:
- **Page 1, with JavaScript:** infinite scroll pages the grid, so the links stay in the HTML but are hidden. The design is unchanged.
- **Page N > 1** (for example, arriving from Google): the links stay visible so the visitor can reach earlier pages. Infinite scroll continues from page N + 1.
- **Without JavaScript:** the links are the pager.

**Measured on the 405-product local catalog** with a link-only crawler from `/` (no JavaScript, no sitemap):

| | Before Part 7 | After Part 7 |
|---|---|---|
| Products reachable by links | **71 / 405** (334 orphans) | **405 / 405** |
| Click depth from home | ≤ 2 for those 71 | ≤ 3 for all |

## 5. Structured data

| Type | Page | Built from | Never includes |
|---|---|---|---|
| Organization | Home | Brand name, logo, `https` social links in `site_settings`; `contactPoint` only from configured `STOREFRONT_CONTACT_EMAIL/PHONE` | Invented phone, address or founding details |
| WebSite | Home | Brand, `en-AU` | SearchAction (Google retired the sitelinks search box in 2024) |
| BreadcrumbList | Listings, products | The same crumbs as the visible breadcrumb | — |
| CollectionPage + ItemList | Indexable listing pages | Exactly the products rendered on that page, positions continuing across pages, `numberOfItems` = listing total | Products not shown on the page |
| Product | Product page | Name, description (plain text), images, SKU, brand, category from the database | Ratings or reviews (G9), GTIN/MPN, condition, colour/material claims |
| Offer / AggregateOffer | Product page | AUD price (one variant price → Offer; a range → AggregateOffer). **Availability from the admin flag, never stock quantity:** PreOrder / OutOfStock (`out_of_stock`, `coming_soon`) / InStock. | — |
| OfferShippingDetails | Single-price Offers | Your confirmed rule: Australia only, A$10 below A$100, A$0 at or above A$100, for one unit | `deliveryTime` (not approved), carrier |
| ContactPage / WebPage | Approved info pages only | Page name, description, URL | Anything on draft pages |

Not emitted, waiting on decisions:
- `hasMerchantReturnPolicy`: no returns policy is approved.
- `aggregateRating`: G9.
- `deliveryTime`: no delivery times are approved.

## 6. Sitemap architecture
- **Index:** `/sitemap.xml`. Absolute URLs; `lastmod` = newest `products.updated_at`.
- **Pages sitemap** `/sitemap-pages.xml` contains:
  - home, `/products`, `/bulk-order`, `/request`;
  - approved info pages;
  - each active category and subcategory that lists at least one product (`lastmod` = its newest product).
- **Product sitemaps** `/sitemap-products-{n}.xml`: up to 5,000 products per file (protocol limit 50,000), ordered by id. Each product has its main image (else its first image) as `<image:image>` at 1200 px.
- **Eligibility:** the same rule as the storefront (`status=active`, `visibility=visible`, active category). Excluded:
  - account, checkout, cart, search, filter and sort URLs;
  - paginated listing URLs (they're found through links);
  - drafts;
  - noindex pages.
- **Responses:** `application/xml; charset=UTF-8`, `Cache-Control: public, max-age=600`, `X-Robots-Tag: noindex` (the sitemap file itself isn't a search result). Cached for 10 minutes. A fixed number of queries per file (one correlated, indexed subquery per product for the image).
- **`lastmod` reliability:** it comes from `updated_at`, which changes on admin edits. It's only as reliable as the admin app's updates to that column.

## 7. robots.txt policy
```
User-agent: *
Disallow: /auth/
Disallow: /size-guides/
Disallow: /*?*search=
Disallow: /*?*brand=
Allow: /

Sitemap: {APP_URL}/sitemap.xml
```
- **Changed in Part 7:** `/account` and `/checkout` are **no longer disallowed**.
  - A crawler only obeys `noindex` on pages it's allowed to fetch.
  - Blocking them could get a linked URL indexed with no content.
  - They stay `noindex, nofollow` and are protected server-side.
- **Still disallowed:** search and brand result URLs (unbounded crawl space, also noindex) and JSON endpoints. Assets, products, categories, `/catalog/*` (needed to render pages) and `/storage/*` are not blocked.
- **robots.txt is crawl control, not access control.** Private data is protected by authentication.

## 8. Image SEO rules
- **Alt text:**
  - product images use the product name;
  - cards use the product name;
  - home images have descriptive alt text;
  - decorative images use `alt=""`.
  - No image is missing `alt` (crawled on every page type).
- **Dimensions:**
  - product main image 800×800;
  - cards 400×450;
  - hero 1600×533;
  - logo 365×66 (added in Part 7, which removed 3 dimension-less images per page);
  - order thumbnails.
  - Still without dimensions: the three home editorial images (lazy, below the fold, in CSS-sized boxes; no CLS measured), and the modal images (no `src` until opened).
- **Priority:** the LCP image (hero slide 1, product main image) is eager with `fetchpriority="high"` and preloaded. Everything below the fold is `loading="lazy"`.
- **Formats and size:** Cloudinary URLs get `f_auto,q_auto` (WebP/AVIF where the browser supports it) and width transforms with `srcset`. Other hosts are passed through unchanged; nothing is rehosted.
- **Filenames:** they're the admin's upload names on Cloudinary. Renaming needs the admin app or Cloudinary (not done; would mean rehosting).

## 9. Performance findings (local, synthetic)
- **Harness:** `tests/browser/perf.mjs`, headless Chrome over CDP, median of 3 runs, cache disabled.
  - Desktop: 1280×900, unthrottled.
  - Mobile: 375×812, 4× CPU slowdown, 150 ms RTT, 1.6 Mbps.
- **Data:** the local 405-product catalog, served by PHP's built-in server with gzip.
- **Lighthouse** isn't installed here. Nothing below is a Lighthouse or field (CrUX) score.

| Mobile (throttled) | LCP before | LCP after (3 runs) | CLS before → after | TTFB |
|---|---|---|---|---|
| Home | 1796 ms | 1812–1820 ms | 0.037 → 0.037 | 151 → 151 ms |
| All products | 2056 ms | 2068–2072 ms | 0.004 → 0.000 | 151 → 151 ms |
| Category | 1924 ms | 1940–1948 ms | 0 → 0 | 151 → 151 ms |
| Product | 2088 ms | 2084–2088 ms (one run missed LCP) | 0 → 0 | 154 → 151 ms |

**Conclusion: Part 7 changed nothing measurable.**
- Mobile is within 0–2.5% of baseline.
- CLS is equal or lower.
- 0 JS errors and 0 CSP violations on every page.
- HTML grew by up to 0.3 KB (the page links).
- **Desktop unthrottled timings aren't conclusive here:** two runs of identical code differed by about 55 ms in DOMContentLoaded (104–140 ms vs 152–206 ms), so local desktop numbers can't detect changes this small.

INP can't be measured without real interaction. The harness reports total blocking time: 0 ms on every page in both profiles.

**SEO-relevant performance notes:**
- Server-rendered HTML is 8–16 KB gzipped (bulk-order 50 KB: it embeds the whole catalog for the order form).
- The LCP element is the hero image or product image, both prioritised.
- The biggest remaining production win is still **gzip and cache headers at the web server** (F5; rules ready).

## 10. Indexing safety
- **Customer data:** none appears in `<head>`, metadata or JSON-LD of public pages for a signed-in customer (tested with a name, email and phone).
- **Private pages:** noindex plus `X-Robots-Tag`, `Cache-Control: private` (`no-store` on checkout and orders).
- **Search terms:** never echoed into titles, descriptions or canonicals. Search pages are noindex, and the term only appears in the visible results header.
- **Local or staging URLs:** deploy-check fails a local or test `APP_URL`. All absolute URLs come from it.
- **Test data:** the sitemap and schema list only live database rows (`status=active`, `visibility=visible`). Never point production at a seeded `*_test` database.
- **User content:** sourcing, bulk and contact submissions are never rendered on public pages. Product rich text is sanitised (A5). JSON-LD can't break out of its `<script>` tag (tested).
- **Unapproved policy pages:** noindex, no canonical, no structured data, not in the sitemap (tested).

## 11. Test coverage

| Requirement | Test |
|---|---|
| Home metadata, H1, Organization/WebSite | `SeoTest::test_home_has_h1_canonical_organization_and_website`, `SeoCrawlabilityTest::test_organization_contact_point_only_from_configured_details` |
| Product metadata, canonical, Open Graph | `SeoTest::test_product_metadata_canonical_and_social_tags`, `…admin_seo_fields_override…`, `…titles_and_descriptions_are_unique` |
| Category metadata and pagination | `SeoTest::test_listing_renders…`, `SeoCrawlabilityTest::test_listing_pages_beyond_the_first_have_crawlable_self_canonical_urls`, `…test_category_listing_shares_the_category_image` |
| Canonical rules and duplicate prevention | `SeoTest::test_listing_canonicals_ignore_sort_tracking_and_param_order`, `SeoCrawlabilityTest::test_paging_parameters_cannot_create_duplicate_or_soft_404_urls`, `…trailing_slash…` (2) |
| Robots directives, private noindex | `SeoTest::test_private_pages_are_noindex`, `…search_filter_and_unknown_listing_urls_are_noindex…`, `SeoCrawlabilityTest::test_robots_txt_lets_crawlers_read_noindex_on_private_pages` |
| Sitemap inclusion and exclusion | `SeoTest::test_sitemap_*` (3), `SeoCrawlabilityTest::test_product_sitemap_lists_the_main_image_of_each_product` |
| Product and breadcrumb structured data | `SeoTest::test_product_json_ld_uses_real_data_and_no_ratings`, `…availability_follows_eligibility_not_stock_count`, `…variant_price_range…`, `…category_breadcrumb_json_ld`, `SeoCrawlabilityTest::test_single_price_offers_carry_the_confirmed_australian_shipping_rule`, `…subcategory_breadcrumb_is_visible_and_matches_the_json_ld` |
| Listing structured data | `SeoCrawlabilityTest::test_listing_collection_page_lists_the_products_shown_on_that_page` |
| Draft page exclusion | `SeoCrawlabilityTest::test_info_pages_get_canonical_and_page_schema_only_once_approved`, `BusinessPolicyTest` |
| Invalid product/category URLs | `SeoTest::test_unlisted_products_are_404`, `…slug_case_variants_redirect…`, `SeoCrawlabilityTest` trailing-slash test (`/Products` → 404) |
| Server-rendered content, no orphans | `SeoTest::test_product_content_is_in_the_initial_html`, `SeoCrawlabilityTest::test_every_listed_product_is_reachable_through_listing_pages`, `…related_products_rotate…` |
| JSON-LD validity, safety, privacy | `SeoTest::test_json_ld_and_meta_cannot_be_broken_out_of`, `SeoCrawlabilityTest::test_every_public_page_has_valid_json_ld_without_duplicate_types`, `…no_customer_data_in_metadata…` |
| Deploy safety | `SeoCrawlabilityTest::test_deploy_check_rejects_a_local_or_test_app_url` |
| **Browser** (rendered DOM after scripts) | `tests/browser/seo.mjs` at 1280 and 375 px: title kept, one visible H1, canonical, valid JSON-LD, related products, page links hidden on page 1 and visible on page N, infinite scroll continues from N+1, noindex on draft and private pages, live trailing-slash 301 and 404s. Also in CI. |

**Mutation checks:**
- Restoring "first 4 related products" makes the orphan test fail.
- Building the redirect from the raw path makes the open-redirect test fail.

## 12. Google Search Console and external validation (manual: needs owner access)
Nothing below has been done. No verification token exists in this repository, and none should be invented.

1. **Before submitting:**
   - Production must be live on its final `https` domain.
   - `APP_URL` is set to it, and `storefront:deploy-check` is green.
   - The other host (www / non-www) and `http` 301 to it.
2. **Verify ownership:** prefer a **Domain property** (DNS TXT record at the registrar; covers http/https and every subdomain). The owner adds the TXT value Search Console gives them. A URL-prefix property (HTML file or meta tag) also works, but would need a code or deploy change; ask before adding one.
3. **Submit the sitemap:** in Sitemaps, submit `https://<domain>/sitemap.xml`. Expect the pages sitemap plus one product sitemap per 5,000 products. Check "Discovered URLs" matches the product count.
4. **URL Inspection:** inspect `/`, a category, a category page 2, a product, `/products?search=x` (expect "Excluded by noindex") and `/account` (expect noindex). For indexable URLs, use "Test live URL" → "View tested page" to confirm the rendered HTML has the title, H1, price and links. Then "Request indexing" for the home page and main categories only.
5. **Rich results:**
   - Run a product URL through the [Rich Results Test](https://search.google.com/test/rich-results): expect Product (merchant listing / product snippet) and Breadcrumb.
   - Expected warnings: missing `aggregateRating`/`review` (G9), `hasMerchantReturnPolicy` (no approved policy), `deliveryTime` (not approved). These are optional; **don't fill them with invented values.**
   - Also run one through the [Schema Markup Validator](https://validator.schema.org/).
6. **Monitoring after launch** (field data needs real traffic; takes weeks):
   - Pages report (indexed vs excluded: noindex/canonical exclusions are expected for search, filter, account and draft pages);
   - Core Web Vitals report (CrUX field data);
   - HTTPS report;
   - Shopping / Merchant listings;
   - Breadcrumbs.
   - Also run PageSpeed Insights on home, a category and a product for lab plus field data.
7. **Bing Webmaster Tools:** can import the Search Console property. Submit the same sitemap.

## 13. Remaining SEO issues

| # | Issue | Status | Needs |
|---|---|---|---|
| S1 | No About / Our Story page; footer "Our Story" goes to a home anchor (D5) | **Blocked** | Approved content from the owner |
| S2 | Policy pages are drafts (noindex) | **Pending** | Owner approval, plus legal review of terms and privacy, then `STOREFRONT_APPROVED_PAGES` |
| S3 | No `aggregateRating` / reviews in markup (G9) | **Pending** | Decide whether to server-render real reviews first |
| S4 | No `hasMerchantReturnPolicy`, no `deliveryTime` | **Pending** | Approved returns policy and delivery times |
| S5 | Heading order: header overlays (mega-menu `h4`s, the search overlay's "No products found" `h3`, empty quick-view/cart `h3`s) come before the page `h1` in the DOM | Open (low) | Changing those tags changes design markup; not done. Search engines use the `h1` and content; no ranking impact is claimed. |
| S6 | Desktop/mobile LCP, INP and CLS field data | **Pending** | Production traffic plus Search Console / PageSpeed Insights |
| S7 | Image filenames are upload names on Cloudinary | Open (low) | Admin-side naming on upload; no rehosting |
| S8 | Product schema doesn't include colour/size/material | Open (low) | Only if those fields become reliable, visible attributes |
| S9 | `lastmod` relies on the admin app updating `products.updated_at` | **Pending** | Admin integration (release blocker) |
| S10 | www vs non-www and http → https redirects | **Pending** | Production web server / DNS |
| S11 | Lighthouse not run; synthetic numbers are local only | **Pending** | Lighthouse or PageSpeed Insights against the deployed site |
| S12 | The CI browser SEO job runs on the 5-product seed, so the pagination checks are SKIP there | Noted | Run locally on the perf catalog (done); optionally seed more products in CI |
