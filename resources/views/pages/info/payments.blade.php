@extends('pages.info.layout')
@section('info')
@php($plan = \App\Payments\OnlinePaymentPlan::settings())
@php($pct = fn ($n) => rtrim(rtrim(number_format($n, 2), '0'), '.'))
@php($cardOn = in_array('card', \App\Payments\PaymentMethods::enabled(), true))
@php($example = \App\Payments\OnlinePaymentPlan::calculate(100, \App\Support\Shipping::fee('adelaide')))
@php($m = fn ($n) => config('storefront.currency_symbol').number_format($n, 2))
<p class="info-lead">How paying for an order works, including pre-orders and the online payment deposit.</p>
<div class="account-card-panel info-prose">
  <h2>Ways to pay</h2>
  <ul>
    <li><strong>Online payment (card){{ $cardOn ? '' : ', when it is offered at checkout' }}:</strong> pay a {{ $pct($plan['depositPercent']) }}% deposit now to pre-book your order; the remaining {{ $pct($plan['balancePercent']) }}% is payable later.@if ($plan['discountPercent'] > 0) Online payment also takes an extra {{ $pct($plan['discountPercent']) }}% off the items (shipping is not discounted).@endif</li>
    <li><strong>Cash on delivery:</strong> nothing is paid in advance. You pay the full amount when your order is delivered, or when you collect it if you chose free pickup.</li>
  </ul>

  <h2>The {{ $pct($plan['depositPercent']) }}% deposit</h2>
  <p>With online payment, only the {{ $pct($plan['depositPercent']) }}% deposit is charged at checkout, whether your order holds pre-order items, in-stock items or both. The deposit is worked out on the full order total, including shipping. Checkout shows the order total, the deposit payable now and the remaining balance before you pay, and the card payment page charges exactly the deposit.</p>
  <p>Example: items of {{ $m(100) }} with Adelaide delivery ({{ $m($example['shipping']) }})@if ($example['discount'] > 0), less the {{ $pct($plan['discountPercent']) }}% online discount ({{ $m($example['discount']) }})@endif: order total {{ $m($example['total']) }}, deposit now {{ $m($example['deposit']) }}, remaining balance {{ $m($example['balance']) }}.</p>

  <h2>The remaining balance</h2>
  <p>{{ \App\Payments\OnlinePaymentPlan::balanceTerms() }} Your order page in <a href="/account#orders">My Orders</a> always shows what has been paid and what is still owed.</p>

  <h2>Pre-orders</h2>
  <p>Products marked “Pre-order” are not in stock yet. They show their expected dispatch date on the product page and are sent once they become available. You can order them with either payment method above.</p>

  <h2>Payment security</h2>
  <p>Card payments are processed on a secure payment page by our payment provider. We never see or store your card details.</p>

  <h2>Cancellations and refunds</h2>
  <p>You can cancel an order yourself from My Orders until our team confirms it, as long as no payment has been received. Once a deposit has been paid, please <a href="/contact">contact us</a> about any change. Our cancellation and refund terms for deposits are being finalised.</p>

  <h2>Your rights</h2>
  <p>Nothing on this page limits any rights you have under the Australian Consumer Law.</p>
</div>
@endsection
