<?php

namespace Tests\Unit;

use App\Support\OrderStatus;
use PHPUnit\Framework\TestCase;

class OrderStatusTest extends TestCase
{
    private function row(?string $old, string $new, string $at): object
    {
        return (object) ['old_status' => $old, 'new_status' => $new, 'created_at' => $at];
    }

    private function states(array $timeline): array
    {
        return array_column($timeline['steps'], 'state', 'key');
    }

    public function test_every_schema_status_has_a_label_and_badge(): void
    {
        foreach (OrderStatus::ALL as $s) {
            $this->assertNotSame('', OrderStatus::label($s));
            $this->assertNotSame('', OrderStatus::badgeClass($s));
            $this->assertArrayHasKey($s, OrderStatus::TRANSITIONS);
        }
        $this->assertSame('Order Placed', OrderStatus::label('pending'));
        $this->assertSame('Shipped', OrderStatus::label('shipped'));
        $this->assertSame('Cancelled', OrderStatus::label('cancelled'));
    }

    public function test_transitions_are_forward_only(): void
    {
        $this->assertTrue(OrderStatus::canTransition('pending', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransition('delivered', 'returned'));
        $this->assertFalse(OrderStatus::canTransition('shipped', 'cancelled'));
        $this->assertFalse(OrderStatus::canTransition('delivered', 'pending'));
        $this->assertFalse(OrderStatus::canTransition('cancelled', 'confirmed'));
        $this->assertFalse(OrderStatus::canTransition('returned', 'delivered'));
        $this->assertFalse(OrderStatus::canTransition('pending', 'returned'));
        $this->assertFalse(OrderStatus::canTransition('pending', 'out_for_delivery'));
    }

    public function test_new_order_shows_only_placed_as_reached(): void
    {
        $t = OrderStatus::timeline('pending', [$this->row(null, 'pending', '2026-11-01 10:00:00')], '2026-11-01 10:00:00', null);
        $this->assertSame(['pending' => 'current', 'confirmed' => 'upcoming', 'processing' => 'upcoming', 'shipped' => 'upcoming', 'delivered' => 'upcoming'], $this->states($t));
        $this->assertSame('2026-11-01 10:00:00', $t['steps'][0]['at']);
        $this->assertNull($t['steps'][1]['at']);
        $this->assertNull($t['exception']);
    }

    public function test_future_steps_never_get_a_time_and_skipped_steps_get_none_invented(): void
    {
        $t = OrderStatus::timeline('shipped', [
            $this->row(null, 'pending', '2026-11-01 10:00:00'),
            $this->row('pending', 'shipped', '2026-11-03 10:00:00'),
        ], '2026-11-01 10:00:00', null);
        $this->assertSame(['pending' => 'done', 'confirmed' => 'done', 'processing' => 'done', 'shipped' => 'current', 'delivered' => 'upcoming'], $this->states($t));
        $this->assertNull($t['steps'][1]['at']);   // confirmed: reached, but no recorded time
        $this->assertSame('2026-11-03 10:00:00', $t['steps'][3]['at']);
        $this->assertNull($t['steps'][4]['at']);
    }

    public function test_delivered_is_complete(): void
    {
        $t = OrderStatus::timeline('delivered', [], '2026-11-01 10:00:00', null);
        $this->assertSame(['done'], array_values(array_unique($this->states($t))));
    }

    public function test_status_without_history_row_is_still_shown_as_reached(): void
    {
        $t = OrderStatus::timeline('processing', [], '2026-11-01 10:00:00', null);
        $this->assertSame('current', $this->states($t)['processing']);
        $this->assertNull($t['steps'][2]['at']);
    }

    public function test_cancellation_after_confirmation(): void
    {
        $t = OrderStatus::timeline('cancelled', [
            $this->row(null, 'pending', '2026-11-01 10:00:00'),
            $this->row('pending', 'confirmed', '2026-11-02 10:00:00'),
            $this->row('confirmed', 'cancelled', '2026-11-02 12:00:00'),
        ], '2026-11-01 10:00:00', '2026-11-02 12:00:00');
        $this->assertSame(['pending' => 'done', 'confirmed' => 'done', 'processing' => 'skipped', 'shipped' => 'skipped', 'delivered' => 'skipped'], $this->states($t));
        $this->assertSame(['key' => 'cancelled', 'label' => 'Cancelled', 'at' => '2026-11-02 12:00:00'], $t['exception']);
    }

    public function test_cancelled_without_history_only_claims_placed(): void
    {
        $t = OrderStatus::timeline('cancelled', [], '2026-11-01 10:00:00', null);
        $this->assertSame('done', $this->states($t)['pending']);
        $this->assertSame('skipped', $this->states($t)['confirmed']);
        $this->assertNull($t['exception']['at']);
    }

    public function test_returned_after_delivery(): void
    {
        $t = OrderStatus::timeline('returned', [
            $this->row('shipped', 'delivered', '2026-11-04 10:00:00'),
            $this->row('delivered', 'returned', '2026-11-09 10:00:00'),
        ], '2026-11-01 10:00:00', null);
        $this->assertSame('done', $this->states($t)['delivered']);
        $this->assertSame('2026-11-09 10:00:00', $t['exception']['at']);
    }

    public function test_payment_labels_keep_payment_separate_from_delivery(): void
    {
        $this->assertSame('Pay on delivery', OrderStatus::paymentLabel('pending', 'cod', 'shipped'));
        $this->assertSame('No payment due', OrderStatus::paymentLabel('pending', 'cod', 'cancelled'));
        $this->assertSame('Paid', OrderStatus::paymentLabel('paid', 'cod', 'delivered'));
        $this->assertSame('Refunded', OrderStatus::paymentLabel('refunded', 'card', 'returned'));
        $this->assertSame('Payment Pending', OrderStatus::paymentLabel('pending', 'card', 'pending'));
    }
}
