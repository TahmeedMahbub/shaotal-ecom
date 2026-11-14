<?php

namespace App\Http\Controllers;

use App\Models\CardPayments;
use App\Models\CustomerOrders;
use App\Models\CustomerReturns;
use App\Support\StoreConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /** POST /account/orders/{number}/return — only when enabled, for the customer's own delivered order, once. */
    public function requestReturn(Request $request, string $number): JsonResponse
    {
        $id = $this->require();
        $d = $request->validate([
            'reason' => ['required', 'string', Rule::in(StoreConfig::get('return_reasons'))],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $result = CustomerReturns::request($id, $number, $d['reason'], isset($d['details']) ? trim($d['details']) : null);
        abort_if($result === null, 404);

        return response()->json(['message' => $result['message']], $result['ok'] ? 201 : 422);
    }

    /** POST /account/orders/{number}/cancel */
    public function cancel(string $number): JsonResponse
    {
        $id = $this->require();
        // Card order: close its Stripe sessions first, so nothing can be paid after it is cancelled.
        if (! CardPayments::prepareCancel($id, $number)) {
            return response()->json(['message' => 'A payment for this order has already been received, so it can no longer be cancelled online. Please contact us for help.'], 422);
        }
        $result = CustomerOrders::cancel($id, $number);
        abort_if($result === null, 404);
        if ($result === false) {
            return response()->json(['message' => 'This order can no longer be cancelled online. Please contact us for help.'], 422);
        }

        return response()->json(['ok' => true, 'order' => CustomerOrders::find($this->customerId(), $number)]);
    }
}
