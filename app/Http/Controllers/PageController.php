<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use App\Models\Storefront;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.index');
    }

    public function products()
    {
        return view('pages.products');
    }

    public function product(string $slug)
    {
        $exists = DB::table('products')->where('slug', $slug)->where('status', 'active')->where('visibility', 'visible')->first(['id', 'size_guide_id']);
        abort_unless($exists, 404);

        return view('pages.product-detail', ['page' => ['slug' => $slug], 'preloadImage' => $this->firstGalleryImage($slug)]);
    }

    /** First gallery image the detail page will show (same rule as generateGalleryImages() in product-detail.js). */
    private function firstGalleryImage(string $slug): ?string
    {
        foreach (Storefront::catalog()['products'] as $p) {
            if ($p['slug'] !== $slug) {
                continue;
            }
            $byColor = $p['colorImages'] ?? [];
            $specific = array_merge([], ...array_values($byColor));
            $color = $p['colors'][0] ?? null;
            $gallery = ($color !== null && ! empty($byColor[$color]))
                ? array_merge(array_values(array_diff($p['images'], $specific)), $byColor[$color])
                : $p['images'];

            return $gallery[0] ?? null;
        }

        return null;
    }

    public function sizeGuide(string $key)
    {
        $guide = Catalog::sizeGuide($key);
        abort_unless($guide, 404);

        return response()->json($guide);
    }

    public function checkout()
    {
        return view('pages.checkout', ['methods' => config('storefront.payment_methods')]);
    }

    public function account()
    {
        return view('pages.account');
    }

    public function bulkOrder()
    {
        return view('pages.bulk-order');
    }

    public function sourcing()
    {
        return view('pages.request');
    }
}
