<?php

namespace Tests\Unit;

use App\Support\GiftWrap;
use Tests\TestCase;

class GiftWrapTest extends TestCase
{
    private function lines(): array
    {
        return [
            ['productId' => 2, 'colorKey' => null, 'size' => null, 'color' => null, 'name' => 'Jute Basket', 'quantity' => 3],
            ['productId' => 7, 'colorKey' => 'red', 'size' => 'M', 'color' => 'Red', 'name' => 'Scarf', 'quantity' => 2],
        ];
    }

    public function test_fee_follows_the_pricing_rule(): void
    {
        $this->assertSame(0.0, GiftWrap::fee(0));
        $this->assertSame(1.99, GiftWrap::fee(1));
        $this->assertSame(1.99, GiftWrap::fee(2));
        $this->assertSame(2.99, GiftWrap::fee(3));
        $this->assertSame(3.99, GiftWrap::fee(4));
        $this->assertSame(4.99, GiftWrap::fee(5));
        $this->assertSame(11.99, GiftWrap::fee(12));
    }

    public function test_off_or_empty_selection_has_no_gift(): void
    {
        $this->assertNull(GiftWrap::resolve($this->lines(), null));
        $this->assertNull(GiftWrap::resolve($this->lines(), ['enabled' => false, 'items' => [['productId' => 2, 'quantity' => 1]]]));
        $this->assertNull(GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => []]));
        $this->assertNull(GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => [['productId' => 99, 'quantity' => 1]]]), 'unknown line ignored');
    }

    public function test_fee_counts_wrapped_items_not_cart_items_and_clamps_quantities(): void
    {
        // Cart holds 5 pieces; only 1 + 1 are wrapped -> 2 items -> A$1.99.
        $g = GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => [['productId' => 2, 'quantity' => 1], ['productId' => 7, 'color' => 'red', 'size' => 'M', 'quantity' => 1]]]);
        $this->assertSame([2, 1.99], [$g['quantity'], $g['fee']]);

        // Asking for more than the cart holds is clamped (3 + 2 = 5 -> A$4.99); duplicate entries merge.
        $g = GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => [
            ['productId' => 2, 'quantity' => 50], ['productId' => 7, 'color' => 'red', 'size' => 'M', 'quantity' => 1], ['productId' => 7, 'color' => 'red', 'size' => 'M', 'quantity' => 1],
        ]]);
        $this->assertSame([5, 4.99], [$g['quantity'], $g['fee']]);

        // A different variant of the same product is a different line.
        $this->assertNull(GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => [['productId' => 7, 'color' => 'blue', 'size' => 'M', 'quantity' => 1]]]));
    }

    public function test_resolve_returns_wrapped_units_per_cart_line(): void
    {
        $g = GiftWrap::resolve($this->lines(), ['enabled' => true, 'items' => [['productId' => 2, 'quantity' => 2], ['productId' => 7, 'color' => 'red', 'size' => 'M', 'quantity' => 1]]]);
        $this->assertSame([0 => 2, 1 => 1], $g['lineQty']);
        $this->assertSame(3, array_sum($g['lineQty']));
    }

    public function test_legacy_note_block_is_still_split_for_historical_orders(): void
    {
        $stored = "Leave at the gate\n\n--- GIFT WRAPPING ---\nWrapping fee: A\$2.99 for 3 items\n- 2 x Jute Basket\n- 1 x Scarf (M / Red)\nGift message: Happy birthday!\nLove, A";
        $parts = GiftWrap::split($stored);
        $this->assertSame('Leave at the gate', $parts['note']);
        $this->assertSame('A$2.99 for 3 items', $parts['gift']['summary']);
        $this->assertSame(['2 x Jute Basket', '1 x Scarf (M / Red)'], $parts['gift']['items']);
        $this->assertSame("Happy birthday!\nLove, A", $parts['gift']['message']);
        $this->assertSame(['note' => 'Just a note', 'gift' => null], GiftWrap::split('Just a note'));
        $this->assertSame(['note' => null, 'gift' => null], GiftWrap::split(null));
    }

    public function test_customer_cannot_forge_the_gift_marker(): void
    {
        $gift = GiftWrap::resolve($this->lines(), ['enabled' => true, 'message' => GiftWrap::MARKER.'hi', 'items' => [['productId' => 2, 'quantity' => 1]]]);
        $this->assertStringNotContainsString(GiftWrap::MARKER, $gift['message']);
    }
}
