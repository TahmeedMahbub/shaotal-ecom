@extends('layouts.store')
@section('title', 'Verify your email')
@section('description', 'Confirm your email address to finish creating your Shaotal account.')
@section('robots', 'noindex, nofollow')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/verify-email.css') }}">
@endpush
@section('content')
<main class="container verify-email-page">
  <div class="account-card-panel verify-card" id="verify-card" data-cooldown="{{ $cooldown }}">
    <div class="verify-icon" aria-hidden="true">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/></svg>
    </div>

    @if ($email)
      <h1 class="auth-title verify-title">Verify your email address</h1>
      <p class="auth-subtitle verify-subtitle">We've sent a 4-digit verification code to <strong id="verify-email-address">{{ $email }}</strong>.</p>

      <div class="auth-error-alert" id="verify-error" role="alert"></div>
      <div class="auth-success-alert" id="verify-success" role="status"></div>

      <form id="verify-form" novalidate>
        <fieldset class="otp-fieldset">
          <legend class="verify-sr-only">Enter the 4-digit verification code</legend>
          <div class="otp-inputs" id="otp-inputs">
            @for ($i = 1; $i <= 4; $i++)
              <input class="otp-input" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}" aria-label="Digit {{ $i }} of 4" required>
            @endfor
          </div>
        </fieldset>
        <button type="submit" class="btn btn-primary auth-submit-btn verify-submit" id="verify-submit">
          <span class="verify-spinner" aria-hidden="true"></span><span class="verify-submit-label">Verify Email</span>
        </button>
      </form>
      <p class="verify-hint">The code expires in {{ $minutes }} minutes. It can take a minute to arrive, so check your spam folder too.</p>

      <div class="verify-actions">
        <span>Didn't get the code?</span>
        <button type="button" class="verify-link-btn" id="verify-resend" @if ($cooldown > 0) disabled @endif>Resend code</button>
        <span class="verify-timer" id="verify-timer" aria-live="polite">@if ($cooldown > 0)in {{ $cooldown }}s @endif</span>
      </div>

      <div class="verify-change">
        <button type="button" class="verify-link-btn" id="verify-change-toggle" aria-expanded="false" aria-controls="verify-change-form">Use a different email address</button>
        <form id="verify-change-form" class="verify-change-form" hidden novalidate>
          <label class="auth-label" for="verify-new-email">New email address</label>
          <div class="verify-change-row">
            <input type="email" id="verify-new-email" class="auth-input" autocomplete="email" placeholder="e.g. mate@example.com.au" required>
            <button type="submit" class="btn btn-secondary-outline verify-change-submit" id="verify-change-submit">Send code</button>
          </div>
        </form>
      </div>
    @else
      <h1 class="auth-title verify-title">Your verification session has expired</h1>
      <p class="auth-subtitle verify-subtitle">For your security, please sign in again and we'll email you a new verification code.</p>
      <button type="button" class="btn btn-primary auth-submit-btn" data-action="open-auth" data-auth-tab="login">Sign In</button>
      <p class="verify-hint"><a href="/" class="auth-link">Back to the shop</a></p>
    @endif
  </div>
</main>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/pages/verify-email.js') }}"></script>
@endpush
