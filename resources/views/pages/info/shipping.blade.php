@extends('pages.info.layout')
@section('info')
@php($free = \App\Support\Delivery::money((float) config('storefront.free_shipping_over')))
@php($flat = \App\Support\Delivery::money((float) config('storefront.shipping_flat')))
<p class="info-lead">How delivery works for orders placed on {{ config('storefront.brand') }}.</p>
<div class="account-card-panel info-prose">
  <h2>Where we deliver</h2>
  <p>We currently deliver to addresses within Australia only. Checkout asks for a full Australian address: street, suburb, state or territory, a 4-digit postcode and a contact phone number.</p>

  <h2>Shipping costs</h2>
  <ul>
    <li>Orders of {{ $free }} or more: free shipping.</li>
    <li>Orders under {{ $free }}: a flat {{ $flat }} shipping fee.</li>
  </ul>
  <p>The threshold applies to the order subtotal before any discount code. The shipping amount is shown in your order summary before you place the order.</p>

  <h2>Delivery times</h2>
  <p>We do not currently publish a guaranteed delivery time; it depends on the item and your location. Pre-order items show their expected dispatch date on the product page and are sent once they become available.</p>

  <h2>Following your order</h2>
  <p>Signed-in customers can see each order’s progress in <a href="/account#orders">My Orders</a>: placed, confirmed, processing, shipped and delivered. Our team updates these steps as your order moves along. Live courier tracking is not available on the site at the moment.</p>

  <h2>Payment</h2>
  <p>Orders are currently paid by cash on delivery. Nothing is charged online when you place an order.</p>

  <h2>Changes before dispatch</h2>
  <p>You can cancel an order yourself from My Orders until our team confirms it. After that, please <a href="/contact">contact us</a> with your order number.</p>
</div>
@endsection
