@extends('pages.info.layout')
@section('info')
<p class="info-lead">What to do if something is not right with your order.</p>
<div class="account-card-panel info-prose">
  <h2>Our returns policy</h2>
  <p>Our returns policy is being finalised. Until it is published there is no set returns period, so please get in touch about any problem with an order.</p>

  <h2>If there is a problem with your order</h2>
  <p>Please <a href="/contact">contact us</a> with your order number and a short description of the problem, and our team will reply.</p>
  @if (\App\Models\CustomerReturns::enabled())
    <p>For a delivered order you can also send a return request from the order’s page in <a href="/account#orders">My Orders</a>. A request is reviewed by our team; it is not an automatic approval or refund.</p>
  @endif

  <h2>Cancelling before dispatch</h2>
  <p>You can cancel an order yourself from <a href="/account#orders">My Orders</a> until our team confirms it, as long as no payment has been received (a cash-on-delivery order has nothing to refund). If you paid an online deposit, please <a href="/contact">contact us</a>; our cancellation and refund terms for deposits are being finalised.</p>

  <h2>Your rights</h2>
  <p>Nothing on this page limits any rights you have under the Australian Consumer Law.</p>
</div>
@endsection
