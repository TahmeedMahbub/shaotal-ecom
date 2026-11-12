<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Customer order history, order details/tracking and cancellation. Routes are behind the "customer" middleware. */
class OrderController extends Controller
{
    private function require(): int
    {
        $id = $this->customerId();
        abort_unless($id, 401, 'Please sign in to continue.');

        return $id;
    }

    /** GET /account/orders?page=N (JSON for the account page). */
    public function index(Request $request): JsonResponse
    {
        $page = CustomerOrders::page($this->require(), (int) $request->query('page', 1));

        return response()->json($page)->header('Cache-Control', 'private, no-store');
    }

    /** GET /account/orders/{number}: server-rendered details page. Another customer's order is a plain 404. */
    public function show(string $number): Response
    {
        $order = CustomerOrders::find($this->require(), $number);
        abort_unless($order, 404);

        return response()->view('pages.order-detail', ['order' => $order])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** POST /account/orders/{number}/cancel */
    public function cancel(string $number): JsonResponse
    {
        $result = CustomerOrders::cancel($this->require(), $number);
        abort_if($result === null, 404);
        if ($result === false) {
            return response()->json(['message' => 'This order can no longer be cancelled online. Please contact us for help.'], 422);
        }

        return response()->json(['ok' => true, 'order' => CustomerOrders::find($this->customerId(), $number)]);
    }
}
