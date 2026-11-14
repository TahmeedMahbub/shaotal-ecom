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
        $this->assertSame('Dispatched', OrderStatus::label('shipped'));
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
        // The four finer stages are real order_status values now; transitions match the admin (forward only, skips allowed).
        $this->assertTrue(OrderStatus::canTransition('pending', 'out_for_delivery'));
        $this->assertTrue(OrderStatus::canTransition('processing', 'ready_for_dispatch'));
        $this->assertTrue(OrderStatus::canTransition('ready_for_dispatch', 'cancelled'));
        $this->assertFalse(OrderStatus::canTransition('in_transit', 'cancelled'));
        $this->assertTrue(OrderStatus::canTransition('arrived_at_hub', 'returned'));
        $this->assertFalse(OrderStatus::canTransition('out_for_delivery', 'in_transit'));
        $this->assertCount(11, OrderStatus::ALL);
        $this->assertSame(OrderStatus::ALL, array_keys(OrderStatus::TRANSITIONS));
    }

    public function test_every_one_of_the_eleven_statuses_is_stored_directly_and_shown_with_its_label(): void
    {
        $labels = ['pending' => 'Order Placed', 'confirmed' => 'Order Confirmed', 'processing' => 'Processing', 'ready_for_dispatch' => 'Ready for Dispatch',
            'shipped' => 'Dispatched', 'in_transit' => 'In Transit', 'arrived_at_hub' => 'Arrived at Delivery Hub', 'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'];
        $keys = array_keys(OrderStatus::STEPS);
        foreach ($labels as $status => $label) {
            $this->assertSame($label, OrderStatus::label(OrderStatus::stage($status, [])), $status);
            if (! isset(OrderStatus::STEPS[$status])) {
                continue;
            }
            // No timeline sub-stage rows at all: order_status alone decides the position.
            $t = OrderStatus::timeline($status, [], '2026-11-01 10:00:00', null);
            $i = array_search($status, $keys, true);
            $this->assertSame($status === 'delivered' ? 'done' : 'current', $this->states($t)[$status], $status);
            $this->assertSame(array_fill(0, $i, 'done'), array_slice(array_values($this->states($t)), 0, $i), $status);
        }
    }

    public function test_new_order_shows_only_placed_as_reached(): void
    {
        $t = OrderStatus::timeline('pending', [$this->row(null, 'pending', '2026-11-01 10:00:00')], '2026-11-01 10:00:00', null);
        $this->assertSame(['pending' => 'current'] + array_fill_keys(array_slice(array_keys(OrderStatus::STEPS), 1), 'upcoming'), $this->states($t));
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
        $this->assertSame(['pending' => 'done', 'confirmed' => 'done', 'processing' => 'done', 'ready_for_dispatch' => 'done', 'shipped' => 'current', 'in_transit' => 'upcoming', 'arrived_at_hub' => 'upcoming', 'out_for_delivery' => 'upcoming', 'delivered' => 'upcoming'], $this->states($t));
        $this->assertNull($t['steps'][1]['at']);   // confirmed: reached, but no recorded time
        $this->assertSame('2026-11-03 10:00:00', $t['steps'][4]['at']);
        $this->assertNull($t['steps'][5]['at']);
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
        $this->assertSame(['pending' => 'done', 'confirmed' => 'done'] + array_fill_keys(array_slice(array_keys(OrderStatus::STEPS), 2), 'skipped'), $this->states($t));
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

    public function test_all_nine_statuses_in_order_with_labels_and_descriptions(): void
    {
        $this->assertSame(
            ['Order Placed', 'Order Confirmed', 'Processing', 'Ready for Dispatch', 'Dispatched', 'In Transit', 'Arrived at Delivery Hub', 'Out for Delivery', 'Delivered'],
            array_values(OrderStatus::STEPS)
        );
        $this->assertSame(array_keys(OrderStatus::STEPS), array_keys(OrderStatus::DESCRIPTIONS));
    }

    public function test_each_stage_is_current_with_earlier_done_and_later_upcoming(): void
    {
        $rows = [];
        foreach (OrderStatus::SUB_STAGES as $stage => $parent) {
            $rows[$stage] = $this->row($parent, $stage, '2026-11-0'.(count($rows) + 2).' 10:00:00');
        }
        $keys = array_keys(OrderStatus::STEPS);
        foreach ($keys as $i => $key) {
            $parent = OrderStatus::SUB_STAGES[$key] ?? $key;
            $history = isset($rows[$key]) ? [$rows[$key]] : [];
            $t = OrderStatus::timeline($parent, $history, '2026-11-01 10:00:00', null);
            $states = array_values($this->states($t));
            $expected = array_map(fn ($j) => $j < $i ? 'done' : ($j === $i ? ($key === 'delivered' ? 'done' : 'current') : 'upcoming'), array_keys($keys));
            $this->assertSame($expected, $states, $key);
            $this->assertSame($key, OrderStatus::stage($parent, array_column($history, 'new_status')), $key);
        }
    }

    public function test_sub_stage_of_another_status_is_ignored_and_unknown_values_never_shown(): void
    {
        $this->assertSame('processing', OrderStatus::stage('processing', ['in_transit']));
        $this->assertSame('delivered', OrderStatus::stage('delivered', ['out_for_delivery']));
        $this->assertSame('Order Placed', OrderStatus::label('pending'));
    }

    public function test_payment_labels_keep_payment_separate_from_delivery(): void
    {
        $this->assertSame('Pay on delivery', OrderStatus::paymentLabel('pending', 'cod', 'shipped'));
        $this->assertSame('No payment due', OrderStatus::paymentLabel('pending', 'cod', 'cancelled'));
        $this->assertSame('Paid', OrderStatus::paymentLabel('paid', 'cod', 'delivered'));
        // A deposit is never "Paid": money received but less than the total reads as pre-booked.
        $this->assertSame('Partially Paid', OrderStatus::paymentLabel('pending', 'card', 'pending', 35.94, 119.80));
        $this->assertSame('Payment Pending', OrderStatus::paymentLabel('pending', 'card', 'pending', 0.0, 119.80));
        $this->assertSame('Paid', OrderStatus::paymentLabel('paid', 'card', 'pending', 119.80, 119.80));
        $this->assertSame('Refunded', OrderStatus::paymentLabel('refunded', 'card', 'returned'));
        $this->assertSame('Payment Pending', OrderStatus::paymentLabel('pending', 'card', 'pending'));
    }
}
