@extends('pages.info.layout')
@section('info')
@php($free = \App\Support\Shipping::freeThreshold() !== null ? \App\Support\Delivery::money(\App\Support\Shipping::freeThreshold()) : null)
@php($pickup = \App\Support\Shipping::pickup())
@php($flat = \App\Support\Delivery::money(\App\Support\Shipping::fee('australia')))
@php($adelaide = \App\Support\Delivery::money(\App\Support\Shipping::fee('adelaide')))
@php($pickupOn = \App\Support\Shipping::pickupEnabled())
<p class="info-lead">How delivery and pickup work for orders placed on {{ config('storefront.brand') }}.</p>
<div class="account-card-panel info-prose">
  <h2>Where we deliver</h2>
  <p>We currently deliver to addresses within Australia only. Checkout asks for a full Australian address: street, suburb, state or territory, a 4-digit postcode and a contact phone number.</p>

  <h2>Shipping costs</h2>
  <ul>
    <li>Adelaide delivery: {{ $adelaide }}. This applies to suburbs in the Adelaide metropolitan area; not every South Australian address is in this zone.</li>
    <li>Rest of Australia: {{ $flat }}, for every other Australian delivery address (including the rest of South Australia).</li>
    @if ($pickupOn)
      <li>{{ $pickup['label'] }}: free ({{ \App\Support\Delivery::money(0) }}).</li>
    @endif
    @if ($free)
      <li>Delivery orders of {{ $free }} or more: free shipping.</li>
    @endif
  </ul>
  <p>The rate is worked out from the suburb and postcode of your delivery address when you choose it at checkout.@if ($free) The free-shipping threshold applies to the order subtotal before any discount code.@endif The shipping method and amount are shown in your order summary before you place the order.</p>

  @if ($pickupOn)
    <h2>Free pickup from {{ $pickup['suburb'] }}</h2>
    <p>Choose “{{ $pickup['label'] }}” at checkout to collect your order yourself. No delivery address is needed; we ask for the name and phone number of the person collecting it.</p>
    <p>{{ $pickup['details'] ?: 'We will contact you to confirm when and where to collect your order.' }}</p>
  @endif

  <h2>Delivery times</h2>
  <p>We do not currently publish a guaranteed delivery time; it depends on the item and your location. Pre-order items show their expected dispatch date on the product page and are sent once they become available.</p>

  <h2>Following your order</h2>
  <p>Signed-in customers can see each order’s progress in <a href="/account#orders">My Orders</a>: order placed, order confirmed, processing, ready for dispatch, dispatched, in transit, arrived at delivery hub, out for delivery and delivered. Our team updates these steps as your order moves along. Live courier tracking is not available on the site at the moment.</p>

  <h2>Payment</h2>
  @if (in_array('card', \App\Payments\PaymentMethods::enabled(), true))
    <p>You can pay by cash on delivery (the full amount when your order arrives or is collected), or online by card, where a {{ rtrim(rtrim(number_format(\App\Payments\OnlinePaymentPlan::settings()['depositPercent'], 2), '0'), '.') }}% deposit is paid now and the rest later. See <a href="/payments">Payments &amp; Pre-orders</a>.</p>
  @else
    <p>Orders are currently paid by cash on delivery: the full amount when your order arrives or is collected. See <a href="/payments">Payments &amp; Pre-orders</a>.</p>
  @endif

  <h2>Changes before dispatch</h2>
  <p>You can cancel an order yourself from My Orders until our team confirms it, as long as no payment has been received. After that, please <a href="/contact">contact us</a> with your order number.</p>
</div>
@endsection
