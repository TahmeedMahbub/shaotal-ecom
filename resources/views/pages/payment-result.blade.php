@extends('layouts.store')
@php
  $money = fn ($v) => config('storefront.currency_symbol').number_format((float) $v, 2);
  // state: paid | confirming (Stripe says complete, webhook not in yet) | incomplete | cancelled
  [$title, $badge, $badgeClass, $text] = match ($state) {
      'paid' => $prebooked
          ? ['Order pre-booked', 'Pre-booked', 'processing', 'Thank you! Your deposit of '.$money($paid).' was received and your order is pre-booked. The remaining balance of '.$money($balance).' is payable later.']
          : ['Payment received', 'Paid', 'delivered', 'Thank you! Your card payment was received and your order is now with our team.'],
      'confirming' => ['Confirming your payment', 'Confirming', 'processing', 'Your payment went through on the secure payment page. We are confirming it with the bank. This usually takes a few seconds.'],
      'incomplete' => ['Payment not completed', 'Not paid', 'pending', 'Your payment was not completed, so you have not been charged. Your order is saved: you can try again with the same or another card.'],
      default => ['Payment cancelled', 'Not paid', 'pending', 'You left the secure payment page before paying, so you have not been charged. Your order and your bag are saved.'],
  };
@endphp
@section('title', $title)
@section('description', 'Card payment status for your order.')
@section('robots', 'noindex, nofollow')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/order-detail.css') }}">
@endpush
@section('content')
  <section class="account-page-header">
    <div class="container">
      <nav class="breadcrumb" aria-label="Breadcrumb" style="margin-bottom: var(--space-xs);">
        <a href="/">Home</a>
        <span class="breadcrumb-separator">/</span>
        <a href="/account#orders">My Orders</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Payment</span>
      </nav>
      <div class="order-detail-head">
        <div>
          <h1 id="payment-title">{{ $title }}</h1>
          <div class="order-detail-meta">Order #{{ $orderNumber }} &middot; {{ $money($total) }} AUD total</div>
        </div>
        <span class="order-status-badge {{ $badgeClass }}" id="payment-badge">{{ $badge }}</span>
      </div>
    </div>
  </section>

  <main class="container">
    <div class="order-detail-layout">
      <div>
        <div class="account-card-panel" id="payment-result"
             data-order="{{ $orderNumber }}" data-state="{{ $state }}" data-clear-cart="{{ $state === 'paid' && $kind === 'success' ? '1' : '0' }}">
          <div class="account-panel-header"><h2 class="account-panel-title">Card payment</h2></div>
          <p class="order-info-block" id="payment-text" role="status" aria-live="polite">{{ $text }}</p>
          <div style="display: flex; gap: var(--space-sm); flex-wrap: wrap; margin-top: var(--space-md);">
            @if ($canPay)
              <button type="button" class="btn btn-primary btn-sm" id="payment-retry-btn" data-order="{{ $orderNumber }}">Pay {{ $money($dueNow) }} now</button>
            @endif
            <a href="{{ $orderUrl }}" class="btn btn-outline btn-sm">View order</a>
            @if ($canCancel && $state !== 'confirming')
              <button type="button" class="btn btn-outline btn-sm" id="payment-cancel-order-btn" data-order="{{ $orderNumber }}">Cancel order</button>
            @endif
          </div>
          @if ($prebooked)
            <div class="order-summary-rows" style="margin-top: var(--space-md);">
              <div class="order-summary-row"><span>Order total</span><span>{{ $money($total) }}</span></div>
              <div class="order-summary-row"><span>Deposit paid</span><span>{{ $money($paid) }}</span></div>
              <div class="order-summary-row is-total"><span>Remaining balance</span><span>{{ $money($balance) }}</span></div>
            </div>
          @endif
          @if ($state !== 'paid')
            <p class="order-track-note">Payments are processed securely by Stripe. Card details never reach our servers.
              @if ($canPay) Unpaid card orders are cancelled automatically when the payment window closes. @endif</p>
          @endif
        </div>
      </div>
    </div>
  </main>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/pages/payment-result.js') }}"></script>
@endpush
