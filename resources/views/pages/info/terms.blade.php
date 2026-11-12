@extends('pages.info.layout')
@section('info')
<p class="info-lead">How buying from {{ config('storefront.brand') }} currently works.</p>
<div class="account-card-panel info-prose">
  <h2>About these terms</h2>
  <p>These terms describe how the store works today. They are being prepared for legal review and will be replaced by approved terms of sale.</p>

  <h2>Accounts</h2>
  <p>You need an account to place an order. Keep your password private; you can reset it from the sign-in window.</p>

  <h2>Prices and payment</h2>
  <ul>
    <li>Prices are shown in Australian dollars (A$).</li>
    <li>The price, shipping and any discount are calculated by our system when you place the order and are shown in your order summary.</li>
    <li>Payment is currently cash on delivery.</li>
  </ul>

  <h2>Orders</h2>
  <p>When you place an order you receive an order number and the order appears in <a href="/account#orders">My Orders</a> as “Order Placed”. Our team then confirms and processes it. You can cancel an order yourself until it is confirmed.</p>

  <h2>Delivery and returns</h2>
  <p>See <a href="/shipping">Shipping</a> and <a href="/returns">Returns</a>.</p>

  <h2>Your rights</h2>
  <p>Nothing in these terms limits any rights you have under the Australian Consumer Law.</p>

  <h2>Contact</h2>
  <p>Questions about these terms: <a href="/contact">contact us</a>.</p>
</div>
@endsection
