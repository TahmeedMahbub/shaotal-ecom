@extends('pages.info.layout')
@section('info')
@php($c = config('storefront.contact'))
<p class="info-lead">Questions about an order, a product or a request? Send us a message.</p>
@if (! empty($c['email']) || ! empty($c['phone']))
  <div class="account-card-panel info-prose">
    <h2>Contact details</h2>
    <ul>
      @if (! empty($c['email']))<li>Email: <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></li>@endif
      @if (! empty($c['phone']))<li>Phone: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $c['phone']) }}">{{ $c['phone'] }}</a></li>@endif
      @if (! empty($c['hours']))<li>Hours: {{ $c['hours'] }}</li>@endif
    </ul>
  </div>
@endif
<div class="account-card-panel">
  <form id="contact-form" class="contact-form" novalidate>
    <div style="position:absolute; left:-10000px; width:1px; height:1px; overflow:hidden;" aria-hidden="true"><label>Company website<input type="text" name="company_url" class="form-hp" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-row-2col">
      <div class="auth-form-group"><label class="auth-label" for="contact-name">Your name</label><input id="contact-name" name="name" class="auth-input" required maxlength="160" autocomplete="name" value="{{ $boot['user']['name'] ?? '' }}"></div>
      <div class="auth-form-group"><label class="auth-label" for="contact-email">Email address</label><input id="contact-email" name="email" type="email" class="auth-input" required maxlength="190" autocomplete="email" value="{{ $boot['user']['email'] ?? '' }}"></div>
    </div>
    <div class="form-row-2col">
      <div class="auth-form-group"><label class="auth-label" for="contact-phone">Phone (optional)</label><input id="contact-phone" name="phone" type="tel" class="auth-input" maxlength="40" autocomplete="tel"></div>
      <div class="auth-form-group"><label class="auth-label" for="contact-order">Order number (optional)</label><input id="contact-order" name="orderNumber" class="auth-input" maxlength="40" placeholder="e.g. INV-123456"></div>
    </div>
    <div class="auth-form-group"><label class="auth-label" for="contact-subject">Subject (optional)</label><input id="contact-subject" name="subject" class="auth-input" maxlength="220"></div>
    <div class="auth-form-group"><label class="auth-label" for="contact-message">Message</label><textarea id="contact-message" name="message" class="auth-input" required minlength="10" maxlength="3000"></textarea></div>
    <button type="submit" class="btn btn-primary" id="contact-submit">Send Message</button>
    <p class="form-status" id="contact-status" role="status" aria-live="polite"></p>
  </form>
</div>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/pages/contact.js') }}"></script>
@endpush
