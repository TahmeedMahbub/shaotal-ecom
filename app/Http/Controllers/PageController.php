<?php

namespace App\Http\Controllers;

use App\Models\Catalog;
use Illuminate\Support\Facades\DB;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.index');
    }

    public function products()
    {
        return view('pages.products', [
            'brands' => Catalog::brands(),
            'categories' => Catalog::categories(),
        ]);
    }

    public function product(string $slug)
    {
        $exists = DB::table('products')->where('slug', $slug)->where('status', 'active')->where('visibility', 'visible')->first(['id', 'size_guide_id']);
        abort_unless($exists, 404);

        return view('pages.product-detail', ['page' => ['slug' => $slug]]);
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
