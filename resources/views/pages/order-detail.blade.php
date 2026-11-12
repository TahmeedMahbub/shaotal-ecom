@extends('layouts.store')
@section('title', 'Order '.$order['number'])
@section('description', 'Order details and delivery progress.')
@section('robots', 'noindex, nofollow')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/order-detail.css') }}">
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/info-pages.css') }}">
@endpush
@php
  $money = fn ($v) => config('storefront.currency_symbol').number_format((float) $v, 2);
  // Stored in UTC; shown once-converted in the display zone with its abbreviation (AEST/AEDT).
  $when = fn ($at) => \App\Support\DisplayTime::dateTime($at);
  $t = $order['totals'];
  $a = $order['address'];
@endphp
@section('content')
  <section class="account-page-header">
    <div class="container">
      <nav class="breadcrumb" aria-label="Breadcrumb" style="margin-bottom: var(--space-xs);">
        <a href="/">Home</a>
        <span class="breadcrumb-separator">/</span>
        <a href="/account#orders">My Orders</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Order #{{ $order['number'] }}</span>
      </nav>
      <div class="order-detail-head">
        <div>
          <h1>Order #{{ $order['number'] }}</h1>
          <div class="order-detail-meta">Placed {{ $when($order['placedAt']) }}</div>
        </div>
        <span class="order-status-badge {{ $order['statusClass'] }}" id="order-status-badge">{{ $order['statusLabel'] }}</span>
      </div>
    </div>
  </section>

  <main class="container">
    <div class="order-detail-layout">
      <div>
        <div class="account-card-panel">
          <div class="account-panel-header">
            <h2 class="account-panel-title">Order Progress</h2>
            <a href="/account#orders" class="order-detail-back">&larr; Back to My Orders</a>
          </div>
          <ol class="order-track" aria-label="Order progress">
            @foreach ($order['timeline']['steps'] as $i => $step)
              <li class="order-track-step is-{{ $step['state'] }}" @if ($step['state'] === 'current') aria-current="step" @endif>
                <span class="order-track-dot" aria-hidden="true">@if ($step['state'] === 'done')&#10003;@else{{ $i + 1 }}@endif</span>
                <span class="order-track-label">{{ $step['label'] }}<span class="sr-only">: {{ ['done' => 'completed', 'current' => 'current step', 'upcoming' => 'not yet', 'skipped' => 'not reached'][$step['state']] }}</span></span>
                @if ($step['at'])<span class="order-track-time">{{ $when($step['at']) }}</span>@endif
              </li>
            @endforeach
          </ol>
          @if ($ex = $order['timeline']['exception'])
            <div class="order-track-exception {{ $ex['key'] }}" role="status">
              <strong>{{ $ex['label'] }}</strong>@if ($ex['at']) · {{ $when($ex['at']) }}@endif
              @if ($ex['key'] === 'cancelled') — this order will not be delivered.@endif
            </div>
          @endif
          <p class="order-track-note">Delivery progress is updated by our team as your order moves along.</p>
        </div>

        <div class="account-card-panel">
          <div class="account-panel-header">
            <h2 class="account-panel-title">Items ({{ array_sum(array_column($order['items'], 'quantity')) }})</h2>
          </div>
          <div class="order-detail-items">
            @foreach ($order['items'] as $item)
              <div class="order-item-row">
                <img class="order-item-thumb" src="{{ \App\Support\Img::url($item['image'], 160) ?? \App\Support\Img::PLACEHOLDER }}" alt="{{ $item['name'] }}" width="64" height="64" loading="lazy" decoding="async">
                <div class="order-item-info">
                  <div class="order-item-title">@if ($item['url'])<a href="{{ $item['url'] }}">{{ $item['name'] }}</a>@else{{ $item['name'] }}@endif</div>
                  <div class="order-item-variant">
                    @if ($item['color'])Colour: {{ $item['color'] }} · @endif
                    @if ($item['size'])Size: {{ $item['size'] }} · @endif
                    Qty: {{ $item['quantity'] }}
                  </div>
                  <div class="order-item-each">{{ $money($item['price']) }} each @if ($item['listPrice'] > $item['price'])<span class="order-item-was">{{ $money($item['listPrice']) }}</span>@endif</div>
                </div>
                <div class="order-item-price">{{ $money($item['lineTotal']) }}</div>
              </div>
            @endforeach
          </div>
        </div>
      </div>

      <div>
        <div class="account-card-panel">
          <div class="account-panel-header"><h2 class="account-panel-title">Order Summary</h2></div>
          <div class="order-summary-rows">
            <div class="order-summary-row"><span>Subtotal</span><span>{{ $money($t['subtotal']) }}</span></div>
            @if ($t['discount'] > 0)
              <div class="order-summary-row is-discount"><span>Discount @if ($t['coupon'])({{ $t['coupon'] }})@endif</span><span>&minus;{{ $money($t['discount']) }}</span></div>
            @endif
            <div class="order-summary-row"><span>Delivery</span><span>{{ $t['shipping'] > 0 ? $money($t['shipping']) : 'Free' }}</span></div>
            @if ($t['tax'] > 0)
              <div class="order-summary-row"><span>Tax</span><span>{{ $money($t['tax']) }}</span></div>
            @endif
            <div class="order-summary-row is-total"><span>Total</span><span>{{ $money($t['total']) }} AUD</span></div>
          </div>
        </div>

        <div class="account-card-panel">
          <div class="account-panel-header"><h2 class="account-panel-title">Payment</h2></div>
          <div class="order-info-block">
            <span class="order-info-label">Method</span>
            <strong>{{ $order['paymentMethod'] }}</strong>
            <span class="order-info-label" style="margin-top: var(--space-sm);">Payment status</span>
            <strong id="order-payment-status">{{ $order['paymentStatus'] }}</strong>
          </div>
        </div>

        <div class="account-card-panel">
          <div class="account-panel-header"><h2 class="account-panel-title">Delivery Address</h2></div>
          <address class="order-info-block" style="font-style: normal;">
            <strong>{{ $a['name'] }}</strong><br>
            {{ $a['street'] }}<br>
            {{ trim(($a['suburb'] ?? '').' '.($a['state'] ?? '').' '.($a['postcode'] ?? '')) }}<br>
            {{ $a['phone'] }}
          </address>
          @if ($order['note'])
            <div class="order-info-block" style="margin-top: var(--space-sm);">
              <span class="order-info-label">Your note</span>
              <span style="white-space: pre-line;">{{ $order['note'] }}</span>
            </div>
          @endif
        </div>

        @if ($order['returnRequest'])
          <div class="account-card-panel" id="order-return-box">
            <div class="account-panel-header"><h2 class="account-panel-title">Return</h2></div>
            <div class="order-info-block">
              <strong>{{ $order['returnRequest']['label'] }}</strong><br>
              Reason: {{ $order['returnRequest']['reason'] }}<br>
              Requested {{ \App\Support\DisplayTime::dateTime($order['returnRequest']['requestedAt']) }}
            </div>
          </div>
        @elseif ($order['canRequestReturn'])
          <div class="account-card-panel order-cancel-box" id="order-return-box">
            <div class="account-panel-header"><h2 class="account-panel-title">Problem with this order?</h2></div>
            <p>Send a return request. Our team reviews every request and will contact you; it is not an automatic approval or refund. See <a href="/returns">Returns</a>.</p>
            <form id="order-return-form" data-order="{{ $order['number'] }}" class="contact-form">
              <div class="auth-form-group"><label class="auth-label" for="return-reason">Reason</label>
                <select id="return-reason" name="reason" class="auth-input" required>
                  @foreach (config('storefront.returns.reasons') as $r)<option value="{{ $r }}">{{ $r }}</option>@endforeach
                </select>
              </div>
              <div class="auth-form-group"><label class="auth-label" for="return-details">Details (optional)</label><textarea id="return-details" name="details" class="auth-input" maxlength="2000" rows="3"></textarea></div>
              <button type="submit" class="btn btn-outline btn-sm" id="order-return-btn" style="width: 100%;">Request a Return</button>
            </form>
          </div>
        @endif

        @if ($order['canCancel'])
          <div class="account-card-panel order-cancel-box" id="order-cancel-box">
            <div class="account-panel-header"><h2 class="account-panel-title">Need to cancel?</h2></div>
            <p>You can cancel this order until our team confirms it.</p>
            <button type="button" class="btn btn-outline btn-sm" id="order-cancel-btn" data-order="{{ $order['number'] }}" style="width: 100%;">Cancel Order</button>
          </div>
        @endif
      </div>
    </div>
  </main>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/pages/order-detail.js') }}"></script>
@endpush
