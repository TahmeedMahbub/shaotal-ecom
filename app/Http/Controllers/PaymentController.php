<?php

namespace App\Http\Controllers;

use App\Models\CardPayments;
use App\Models\CustomerOrders;
use App\Payments\OnlinePaymentPlan;
use App\Payments\Stripe;
use App\Payments\StripeException;
use App\Support\OrderStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe card payments: the customer-facing return pages, "Pay now" retry, a status endpoint the return page polls,
 * and the webhook. A payment becomes paid only from Stripe itself: the signature-verified webhook, or the session read
 * from Stripe's API on the success return (never from the URL).
 */
class PaymentController extends Controller
{
    private function require(): int
    {
        $id = $this->customerId();
        abort_unless($id, 401, 'Please sign in to continue.');

        return $id;
    }

    /** POST /account/orders/{number}/pay → { redirectUrl } to Stripe Checkout (reuses an open session). */
    public function pay(string $number): JsonResponse
    {
        return response()->json(['redirectUrl' => CardPayments::start($this->require(), $number)['redirectUrl']]);
    }

    /** GET /account/orders/{number}/payment-status (polled by the return page; reads our DB only). */
    public function status(string $number): JsonResponse
    {
        $o = DB::table('orders')->where('order_number', $number)->where('customer_id', $this->require())
            ->first(['id', 'payment_status', 'payment_method', 'order_status', 'grand_total']);
        abort_unless($o, 404);
        $paid = CardPayments::paidTotal($o->id);

        return response()->json([
            'paymentStatus' => $o->payment_status,
            'orderStatus' => $o->order_status,
            // Money confirmed by the webhook (deposit or full): the return page uses this, not "paid", to finish.
            'received' => $paid > 0,
            'label' => OrderStatus::paymentLabel($o->payment_status, $o->payment_method, $o->order_status, $paid, (float) $o->grand_total),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * GET /checkout/payment/success?session_id=cs_… — Stripe's return URL after the hosted page.
     *
     * The URL proves nothing: the session id only picks the customer's own payment row. If our webhook hasn't recorded
     * the payment yet, ask Stripe (server-side, secret key) for the session and apply it through the same idempotent
     * path as the webhook (CardPayments::applySession). Once a paid payment row exists the customer goes to the checkout
     * success modal (the same one COD uses), also on refresh or a repeated visit. Only when Stripe cannot confirm it
     * (unsettled async payment, Stripe unreachable) is the "confirming" page shown.
     */
    public function success(Request $request): Response
    {
        $sessionId = (string) $request->query('session_id', '');
        abort_unless(preg_match('/^cs_[A-Za-z0-9_]{1,250}$/', $sessionId), 404);
        $row = CardPayments::forSession($this->require(), $sessionId);
        abort_unless($row, 404);

        $state = ($row->payment_status === 'paid' || CardPayments::paidTotal($row->id) > 0) ? 'paid' : 'pending';
        if ($state !== 'paid') {
            try {
                $session = Stripe::retrieveCheckoutSession($sessionId);
                CardPayments::applySession($session, 'return');
                $state = CardPayments::paidTotal($row->id) > 0 ? 'paid' : (($session['status'] ?? null) === 'complete' ? 'confirming' : 'incomplete');
            } catch (StripeException) {
                $state = 'confirming';
            }
        }
        if ($state === 'paid') {
            return redirect('/checkout?paid='.rawurlencode($row->order_number))->header('Cache-Control', 'private, no-store');
        }

        return $this->page('success', $row, $state);
    }

    /** GET /checkout/payment/cancel?order=INV-… — customer left the Stripe page without paying. */
    public function cancel(Request $request): Response
    {
        $number = (string) $request->query('order', '');
        abort_unless(preg_match('/^[A-Za-z0-9\-]{1,40}$/', $number), 404);
        $row = DB::table('orders')->where('order_number', $number)->where('customer_id', $this->require())
            ->first(['id', 'order_number', 'grand_total', 'payment_status', 'order_status', 'payment_method']);
        abort_unless($row && $row->payment_method === 'card', 404);

        return $this->page('cancel', $row, ($row->payment_status === 'paid' || CardPayments::paidTotal($row->id) > 0) ? 'paid' : 'cancelled');
    }

    private function page(string $kind, object $row, string $state): Response
    {
        $paid = CardPayments::paidTotal($row->id);
        $plan = OnlinePaymentPlan::forOrder($row->id);

        return response()->view('pages.payment-result', [
            'kind' => $kind,
            'state' => $state,
            'orderNumber' => $row->order_number,
            'orderUrl' => CustomerOrders::url($row->order_number),
            'total' => (float) $row->grand_total,
            // What this payment is for: the deposit (pre-booking) or, for an order without a plan, the whole total.
            'dueNow' => $plan ? (float) $plan['deposit'] : (float) $row->grand_total,
            'paid' => $paid,
            'balance' => round(max(0, (float) $row->grand_total - $paid), 2),
            'prebooked' => $paid > 0 && $paid < (float) $row->grand_total,
            'canPay' => CardPayments::canPay('card', $row->payment_status, $row->order_status, $paid),
            'canCancel' => $row->order_status === 'pending' && $row->payment_status !== 'paid' && $paid <= 0
                && in_array($row->order_status, CustomerOrders::cancellableStatuses(), true),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * POST /webhooks/stripe — sessionless, no CSRF (Stripe can't send a token); authenticity comes from the
     * Stripe-Signature HMAC. 400 on a bad signature (Stripe shows it as failed), 2xx once handled or deliberately
     * ignored, 500 on our own errors so Stripe retries (it retries for up to 3 days).
     */
    public function webhook(Request $request): JsonResponse
    {
        try {
            $event = Stripe::verifyWebhook($request->getContent(), $request->header('Stripe-Signature'));
        } catch (StripeException $e) {
            Log::warning('Stripe webhook rejected', ['reason' => $e->getMessage(), 'ip' => $request->ip()]);

            return response()->json(['error' => 'invalid signature'], 400);
        }

        $outcome = CardPayments::applyEvent($event);
        Log::info('Stripe webhook', ['event' => $event['id'], 'type' => $event['type'], 'outcome' => $outcome]);

        return response()->json(['received' => true, 'outcome' => $outcome]);
    }
}
