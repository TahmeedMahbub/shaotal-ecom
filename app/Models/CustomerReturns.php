<?php

namespace App\Models;

use App\Support\StoreConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer return requests (Part 6). Only what is decided is implemented: a customer may ask once, for their own
 * delivered order, optionally within a configured window after the recorded delivery. Staff decide in the admin app.
 * No refund, payment or stock change happens here. Disabled unless configured AND the proposed tables exist.
 */
class CustomerReturns
{
    public const STATUS_LABELS = ['requested' => 'Return Requested', 'approved' => 'Return Approved', 'rejected' => 'Return Not Approved', 'completed' => 'Return Completed'];

    public static function enabled(): bool
    {
        return StoreConfig::get('returns_enabled')
            && Cache::store('array')->rememberForever('returns.tables', fn () => Schema::hasTable('order_return_requests') && Schema::hasTable('order_return_request_events'));
    }

    /** Existing request for an order (any status), or null. */
    public static function forOrder(int $orderId): ?array
    {
        if (! self::enabled()) {
            return null;
        }
        $r = DB::table('order_return_requests')->where('order_id', $orderId)->first(['status', 'reason', 'created_at', 'status_changed_at']);

        return $r ? ['status' => $r->status, 'label' => self::STATUS_LABELS[$r->status] ?? ucfirst($r->status), 'reason' => $r->reason, 'requestedAt' => $r->created_at] : null;
    }

    /** Why this order cannot get a return request now, or null when it can. */
    public static function ineligibleReason(object $order): ?string
    {
        if (! self::enabled()) {
            return 'Return requests are not available online yet. Please contact us.';
        }
        if ($order->order_status !== 'delivered') {
            return 'Returns can only be requested for delivered orders.';
        }
        if (DB::table('order_return_requests')->where('order_id', $order->id)->exists()) {
            return 'A return has already been requested for this order.';
        }
        $days = StoreConfig::get('returns_window_days');
        if ($days !== null) {
            $deliveredAt = DB::table('order_timeline')->where('order_id', $order->id)->where('new_status', 'delivered')->min('created_at');
            if (! $deliveredAt || now()->greaterThan(Carbon::parse($deliveredAt, 'UTC')->addDays((int) $days))) {
                return 'The period for requesting a return online has ended. Please contact us.';
            }
        }

        return null;
    }

    /**
     * @return array{ok: bool, message: string}|null null when the order is not this customer's
     */
    public static function request(int $customerId, string $orderNumber, string $reason, ?string $details): ?array
    {
        return DB::transaction(function () use ($customerId, $orderNumber, $reason, $details) {
            $order = DB::table('orders')->where('order_number', $orderNumber)->where('customer_id', $customerId)->lockForUpdate()->first(['id', 'order_status']);
            if (! $order) {
                return null;
            }
            if ($why = self::ineligibleReason($order)) {
                return ['ok' => false, 'message' => $why];
            }
            // UNIQUE(order_id) is the final guard against a concurrent duplicate.
            $id = DB::table('order_return_requests')->insertGetId([
                'order_id' => $order->id, 'customer_id' => $customerId, 'status' => 'requested', 'reason' => $reason,
                'details' => $details, 'status_changed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('order_return_request_events')->insert([
                'return_request_id' => $id, 'old_status' => null, 'new_status' => 'requested', 'note' => 'Requested by customer', 'created_at' => now(),
            ]);

            return ['ok' => true, 'message' => 'Your return request has been sent. Our team will review it and contact you.'];
        });
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, ['requested' => ['approved', 'rejected'], 'approved' => ['completed'], 'rejected' => [], 'completed' => []][$from] ?? [], true);
    }
}
