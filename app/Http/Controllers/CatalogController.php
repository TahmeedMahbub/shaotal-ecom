<?php

namespace App\Http\Controllers;

use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public catalog JSON used by the page scripts instead of embedding the whole catalog in every page. Same data for
 * every visitor and served without session or cookies (see routes/web.php), so it can be cached by browsers and CDNs.
 */
class CatalogController extends Controller
{
    public const MAX_IDS = 60;

    /** GET /catalog/products?ids=1,2,3 — cart, wishlist and recently-viewed lookups. Unlisted ids are omitted. */
    public function products(Request $request): JsonResponse
    {
        $raw = $request->query('ids');
        $ids = is_string($raw) ? array_slice(array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), fn ($i) => $i > 0))), 0, self::MAX_IDS) : [];

        return $this->json(['products' => $ids ? Listing::byIds($ids) : []]);
    }

    /** GET /catalog/search?q=… — header quick search (empty q: newest products as suggestions). */
    public function search(Request $request): JsonResponse
    {
        $q = $request->query('q');
        $limit = max(1, min((int) $request->query('limit', 20), 20));

        return $this->json(Listing::search(is_string($q) ? mb_substr($q, 0, 120) : '', $limit));
    }

    /** GET /catalog/feed?category=…&page=N — listing filters, sort and infinite scroll. */
    public function feed(Request $request): JsonResponse
    {
        return $this->json(Listing::page(Listing::filters($request->query()), (int) $request->query('page', 1), (int) $request->query('perPage', Listing::PAGE_SIZE)));
    }

    private function json(array $data): JsonResponse
    {
        // Catalog freshness is ~60 s server-side, so a short public cache adds no meaningful staleness.
        return response()->json($data)->header('Cache-Control', 'public, max-age=60')->header('X-Robots-Tag', 'noindex');
    }
}
