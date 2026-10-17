@extends('layouts.store')
@section('title', "Checkout")
@section('description', "Fast and secure Australian checkout. Complete your artisan-crafted order with delivery nationwide.")
@section('minimal', '1')
@push('head')
<style>
    /* ============================================================
       CHECKOUT PAGE SPECIFIC STYLES & SVG ICON HELPERS
       ============================================================ */
    .checkout-section {
      padding: var(--space-xl) 0 var(--space-3xl);
      background-color: var(--color-surface);
      min-height: calc(100vh - 140px);
    }

    .checkout-header {
      margin-bottom: var(--space-lg);
    }

    .checkout-breadcrumb {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      font-size: var(--fs-small);
      color: var(--color-grey-500);
      margin-bottom: var(--space-xs);
    }

    .checkout-breadcrumb a {
      color: var(--color-grey-700);
      text-decoration: none;
      transition: color var(--transition-fast);
    }

    .checkout-breadcrumb a:hover {
      color: var(--color-brand);
    }

    .checkout-title {
      font-family: var(--font-heading);
      font-size: var(--fs-h2);
      font-weight: var(--fw-bold);
      color: var(--color-black);
      letter-spacing: var(--ls-tight);
      margin-bottom: 0.25rem;
    }

    .checkout-subtitle {
      font-size: var(--fs-body);
      color: var(--color-grey-700);
    }

    /* 2-Column Desktop Grid */
    .checkout-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: var(--space-xl);
    }

    @media (min-width: 992px) {
      .checkout-grid {
        grid-template-columns: 1fr 400px;
      }
    }

    /* Step Card Base */
    .checkout-card {
      background-color: var(--color-surface-card);
      border-radius: var(--radius-lg);
      border: 1px solid var(--color-grey-200);
      box-shadow: var(--shadow-sm);
      padding: var(--space-lg);
      margin-bottom: var(--space-lg);
      transition: border-color var(--transition-fast);
    }

    .checkout-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: var(--space-md);
      margin-bottom: var(--space-md);
      border-bottom: 1px solid var(--color-grey-100);
    }

    .checkout-step-title {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-family: var(--font-heading);
      font-size: var(--fs-h4);
      font-weight: var(--fw-bold);
      color: var(--color-black);
    }

    .step-number {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background-color: var(--color-brand);
      color: var(--color-white);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--fs-small);
      font-weight: var(--fw-bold);
    }

    /* Saved Addresses Grid */
    .address-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: var(--space-md);
      margin-bottom: var(--space-md);
    }

    .address-card {
      border: 2px solid var(--color-grey-200);
      border-radius: var(--radius-md);
      padding: var(--space-md);
      cursor: pointer;
      position: relative;
      transition: all var(--transition-fast);
      background-color: var(--color-surface-card);
    }

    .address-card:hover {
      border-color: var(--color-grey-400);
    }

    .address-card.selected {
      border-color: var(--color-brand);
      background-color: var(--color-brand-light);
      box-shadow: var(--shadow-xs);
    }

    .address-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 0.5rem;
    }

    .address-type-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.35rem;
      font-size: var(--fs-xs);
      font-weight: var(--fw-semibold);
      text-transform: uppercase;
      padding: 0.2rem 0.55rem;
      border-radius: var(--radius-sm);
      background-color: var(--color-grey-200);
      color: var(--color-dark);
    }

    .address-card.selected .address-type-badge {
      background-color: var(--color-brand);
      color: var(--color-white);
    }

    .address-selection-indicator {
      display: flex;
      align-items: center;
      gap: 0.25rem;
      font-size: var(--fs-xs);
      font-weight: var(--fw-semibold);
      color: var(--color-brand);
    }

    .address-name {
      font-weight: var(--fw-bold);
      color: var(--color-black);
      font-size: var(--fs-body);
      margin-bottom: 0.25rem;
    }

    .address-text {
      font-size: var(--fs-small);
      color: var(--color-grey-700);
      line-height: var(--lh-normal);
      margin-bottom: 0.5rem;
    }

    .address-phone {
      font-size: var(--fs-xs);
      color: var(--color-grey-500);
      display: flex;
      align-items: center;
      gap: 0.3rem;
    }

    .address-actions {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      margin-top: 0.75rem;
      padding-top: 0.5rem;
      border-top: 1px dashed var(--color-grey-200);
    }

    .address-action-btn {
      background: none;
      border: none;
      font-size: var(--fs-xs);
      font-weight: var(--fw-medium);
      color: var(--color-grey-700);
      cursor: pointer;
      padding: 0.2rem 0.4rem;
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      border-radius: var(--radius-sm);
      transition: all var(--transition-fast);
    }

    .address-action-btn:hover {
      color: var(--color-brand);
      background-color: rgba(204, 122, 63, 0.08);
    }

    .address-action-btn.delete-btn:hover {
      color: var(--color-danger);
      background-color: rgba(220, 53, 69, 0.08);
    }

    .btn-add-address-trigger {
      width: 100%;
      border: 2px dashed var(--color-grey-300);
      border-radius: var(--radius-md);
      padding: 0.85rem;
      background: transparent;
      color: var(--color-brand);
      font-weight: var(--fw-semibold);
      font-size: var(--fs-small);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.5rem;
      cursor: pointer;
      transition: all var(--transition-fast);
    }

    .btn-add-address-trigger:hover {
      background-color: var(--color-brand-light);
      border-color: var(--color-brand);
    }

    /* Address Form Panel */
    .address-form-panel {
      margin-top: var(--space-md);
      padding: var(--space-md);
      background-color: var(--color-surface);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-grey-200);
    }

    .form-grid-2 {
      display: grid;
      grid-template-columns: 1fr;
      gap: var(--space-md);
    }

    @media (min-width: 576px) {
      .form-grid-2 {
        grid-template-columns: 1fr 1fr;
      }
      .form-grid-full {
        grid-column: 1 / -1;
      }
    }

    .form-group {
      display: flex;
      flex-direction: column;
      gap: 0.35rem;
    }

    .form-label {
      font-size: var(--fs-xs);
      font-weight: var(--fw-semibold);
      color: var(--color-dark);
      text-transform: uppercase;
      letter-spacing: var(--ls-wide);
    }

    .form-input, .form-select {
      width: 100%;
      padding: 0.65rem 0.85rem;
      border: 1.5px solid var(--color-grey-300);
      border-radius: var(--radius-sm);
      font-family: var(--font-body);
      font-size: var(--fs-body);
      color: var(--color-black);
      background-color: var(--color-white);
      transition: border-color var(--transition-fast);
    }

    .form-input:focus, .form-select:focus {
      outline: none;
      border-color: var(--color-brand);
      box-shadow: 0 0 0 3px rgba(204, 122, 63, 0.15);
    }

    .type-radio-group {
      display: flex;
      gap: 1rem;
      margin-top: 0.25rem;
    }

    .type-radio-label {
      display: flex;
      align-items: center;
      gap: 0.35rem;
      font-size: var(--fs-small);
      cursor: pointer;
    }

    /* Payment Methods */
    .payment-options-list {
      display: flex;
      flex-direction: column;
      gap: var(--space-md);
    }

    .payment-option-card {
      border: 2px solid var(--color-grey-200);
      border-radius: var(--radius-md);
      padding: var(--space-md);
      cursor: pointer;
      transition: all var(--transition-fast);
    }

    .payment-option-card.selected {
      border-color: var(--color-brand);
      background-color: var(--color-brand-light);
    }

    .payment-option-header {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .payment-option-title {
      font-weight: var(--fw-semibold);
      color: var(--color-black);
      font-size: var(--fs-body);
      flex-grow: 1;
    }

    .payment-subpanel {
      margin-top: var(--space-md);
      padding-top: var(--space-md);
      border-top: 1px solid var(--color-grey-200);
    }

    /* Order Summary Sidebar Sticky */
    .sticky-sidebar {
      position: sticky;
      top: 90px;
    }

    .summary-items-list {
      display: flex;
      flex-direction: column;
      gap: var(--space-md);
      max-height: 280px;
      overflow-y: auto;
      padding-right: 0.25rem;
      margin-bottom: var(--space-md);
    }

    .summary-item-row {
      display: flex;
      align-items: center;
      gap: var(--space-sm2);
    }

    .summary-item-img {
      width: 56px;
      height: 56px;
      border-radius: var(--radius-md);
      object-fit: cover;
      border: 1px solid var(--color-grey-200);
      flex-shrink: 0;
    }

    .summary-item-info {
      flex-grow: 1;
      min-width: 0;
    }

    .summary-item-title {
      font-size: var(--fs-small);
      font-weight: var(--fw-semibold);
      color: var(--color-black);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .summary-item-meta {
      font-size: var(--fs-xs);
      color: var(--color-grey-500);
    }

    .summary-item-price {
      font-size: var(--fs-small);
      font-weight: var(--fw-bold);
      color: var(--color-black);
      text-align: right;
    }

    /* Expandable Coupon Box */
    .coupon-accordion-trigger {
      background: transparent;
      border: none;
      padding: 0;
      color: var(--color-brand);
      font-size: var(--fs-small);
      font-weight: var(--fw-semibold);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 0.4rem;
      margin-bottom: var(--space-md);
    }

    .coupon-input-wrap {
      display: flex;
      gap: 0.5rem;
    }

    .coupon-applied-box {
      background-color: hsl(152, 40%, 95%);
      border: 1px solid hsl(152, 45%, 75%);
      border-radius: var(--radius-sm);
      padding: 0.5rem 0.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: var(--space-md);
    }

    .coupon-applied-text {
      font-size: var(--fs-xs);
      font-weight: var(--fw-bold);
      color: var(--color-success);
      display: flex;
      align-items: center;
      gap: 0.35rem;
    }

    .coupon-remove-btn {
      background: none;
      border: none;
      color: var(--color-danger);
      font-size: var(--fs-xs);
      font-weight: var(--fw-bold);
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 0.2rem;
    }

    /* Breakdown Rows */
    .breakdown-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: var(--fs-small);
      color: var(--color-grey-700);
      margin-bottom: 0.5rem;
    }

    .breakdown-row.total-row {
      font-size: var(--fs-body-lg);
      font-weight: var(--fw-bold);
      color: var(--color-black);
      margin-top: 0.75rem;
      padding-top: 0.75rem;
      border-top: 1.5px solid var(--color-grey-200);
    }

    .breakdown-row.total-row .total-price {
      color: var(--color-brand);
      font-family: var(--font-heading);
      font-size: 1.35rem;
    }

    .place-order-btn {
      width: 100%;
      margin-top: var(--space-md);
      padding: 0.95rem;
      font-size: var(--fs-body);
      font-weight: var(--fw-bold);
    }

    .security-notice {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.35rem;
      font-size: var(--fs-xs);
      color: var(--color-grey-500);
      margin-top: 0.75rem;
      text-align: center;
    }

    /* Order Success Modal */
    .success-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: rgba(0, 0, 0, 0.65);
      backdrop-filter: blur(4px);
      z-index: var(--z-modal);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-md);
      opacity: 0;
      visibility: hidden;
      transition: all var(--transition-base);
    }

    .success-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .success-modal-card {
      background: var(--color-surface-card);
      border-radius: var(--radius-lg);
      max-width: 520px;
      width: 100%;
      padding: var(--space-xl);
      text-align: center;
      box-shadow: var(--shadow-lg);
      transform: translateY(20px);
      transition: transform var(--transition-base);
    }

    .success-overlay.active .success-modal-card {
      transform: translateY(0);
    }

    .success-icon-badge {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background-color: hsl(152, 45%, 90%);
      color: var(--color-success);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: var(--space-md);
    }

    /* Outline Icon Helper utility */
    .icon-svg {
      display: inline-block;
      width: 1.15em;
      height: 1.15em;
      vertical-align: -0.15em;
      flex-shrink: 0;
    }
  </style>
@endpush
@section('content')
<main class="checkout-section">
    <div class="container">
      
      
      <div class="checkout-header">
        <div class="checkout-breadcrumb">
          <a href="/">Home</a>
          <span>/</span>
          <a href="javascript:void(0)" id="breadcrumb-cart-link">Cart</a>
          <span>/</span>
          <span style="color: var(--color-black); font-weight: var(--fw-semibold);">Checkout</span>
        </div>
        <h1 class="checkout-title">Express Checkout</h1>
        <p class="checkout-subtitle">Fast, secure Australian dispatch with full customer guarantee.</p>
      </div>

      
      <div class="checkout-grid">
        
        
        <div class="checkout-main">
          
          
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="checkout-step-title">
                <span class="step-number">1</span>
                <span>Delivery Address</span>
              </div>
              <span class="badge" style="background: var(--color-brand-light); color: var(--color-brand); font-size: var(--fs-xs); display: inline-flex; align-items: center; gap: 0.25rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0C.68 5.58.25 6.06.25 6.628v8.872c0 .621.504 1.125 1.125 1.125h.375m16.5-7.5h-3.75" /></svg>
                Australia Post Express
              </span>
            </div>

            
            <div class="address-cards-grid" id="address-cards-grid">
              
            </div>

            
            <button type="button" class="btn-add-address-trigger" id="add-address-btn">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
              <span>Add New Address</span>
            </button>

            
            <div class="address-form-panel" id="address-form-panel" style="display: none;">
              <h4 id="form-panel-title" style="font-family: var(--font-heading); font-size: var(--fs-h4); font-weight: var(--fw-bold); margin-bottom: var(--space-md); color: var(--color-black);">
                Add New Delivery Address
              </h4>

              <form id="new-address-form">
                <input type="hidden" id="edit-address-id" value="">
                
                <div class="form-grid-2">
                  <div class="form-group">
                    <label class="form-label" for="addr-fullname">Full Name *</label>
                    <input type="text" id="addr-fullname" class="form-input" placeholder="Your Name Here" required>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="addr-phone">Phone Number *</label>
                    <input type="tel" id="addr-phone" class="form-input" placeholder="e.g. +61 412 345 678" required>
                  </div>

                  <div class="form-group form-grid-full">
                    <label class="form-label" for="addr-street">Street Address *</label>
                    <input type="text" id="addr-street" class="form-input" placeholder="House/Apartment no, street name" required>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="addr-suburb">Suburb / City *</label>
                    <input type="text" id="addr-suburb" class="form-input" placeholder="e.g. Adelaide" required>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="addr-state">State *</label>
                    <select id="addr-state" class="form-select" required>
                      <option value="VIC">Victoria (VIC)</option>
                      <option value="NSW">New South Wales (NSW)</option>
                      <option value="QLD">Queensland (QLD)</option>
                      <option value="WA">Western Australia (WA)</option>
                      <option value="SA">South Australia (SA)</option>
                      <option value="TAS">Tasmania (TAS)</option>
                      <option value="ACT">ACT</option>
                      <option value="NT">Northern Territory (NT)</option>
                    </select>
                  </div>

                  <div class="form-group">
                    <label class="form-label" for="addr-postcode">Postcode *</label>
                    <input type="text" id="addr-postcode" class="form-input" placeholder="e.g. 3000" maxlength="4" pattern="[0-9]{4}" required>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Address Type</label>
                    <div class="type-radio-group">
                      <label class="type-radio-label">
                        <input type="radio" name="addr-type" value="Home" checked> Home
                      </label>
                      <label class="type-radio-label">
                        <input type="radio" name="addr-type" value="Office"> Office
                      </label>
                      <label class="type-radio-label">
                        <input type="radio" name="addr-type" value="Other"> Other
                      </label>
                    </div>
                  </div>
                </div>

                <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-md); justify-content: flex-end;">
                  <button type="button" class="btn btn-outline" id="cancel-address-btn">Cancel</button>
                  <button type="submit" class="btn btn-primary" id="save-address-btn">Save & Use Address</button>
                </div>
              </form>
            </div>

          </div>

          
          <div class="checkout-card">
            <div class="checkout-card-header">
              <div class="checkout-step-title">
                <span class="step-number">2</span>
                <span>Payment Method</span>
              </div>
              <span class="badge" style="background: var(--color-grey-200); color: var(--color-dark); font-size: var(--fs-xs); display: inline-flex; align-items: center; gap: 0.25rem;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>
                256-bit Encrypted
              </span>
            </div>

            <div class="payment-options-list" id="payment-options-list">
              
              
              @if (in_array('card', $methods))<div class="payment-option-card" data-method="card">
                <div class="payment-option-header">
                  <input type="radio" name="payment-method" id="pay-card" value="card" checked>
                  <label for="pay-card" class="payment-option-title" style="display: flex; align-items: center; gap: 0.4rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5pt18.75A2.25 2.25 0 0021.75 6.75H2.25A2.25 2.25 0 000 9v9a2.25 2.25 0 002.25 2.25h19.5A2.25 2.25 0 0024 18V9A2.25 2.25 0 0021.75 6.75H2.25z" /></svg>
                    Credit or Debit Card
                  </label>
                  <div style="display: flex; gap: 0.25rem;">
                    <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); background: #f0f0f0; padding: 2px 6px; border-radius: 4px;">VISA</span>
                    <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); background: #f0f0f0; padding: 2px 6px; border-radius: 4px;">MC</span>
                    <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); background: #f0f0f0; padding: 2px 6px; border-radius: 4px;">AMEX</span>
                  </div>
                </div>

                <div class="payment-subpanel" id="card-details-panel">
                  <div class="form-grid-2">
                    <div class="form-group form-grid-full">
                      <label class="form-label">Card Number</label>
                      <input type="text" class="form-input font-monospace" placeholder="4532 •••• •••• 8921" readonly>
                    </div>
                    <div class="form-group">
                      <label class="form-label">Expiry Date</label>
                      <input type="text" class="form-input font-monospace" placeholder="MM/YY" readonly>
                    </div>
                    <div class="form-group">
                      <label class="form-label">CVV / CVC</label>
                      <input type="password" class="form-input font-monospace" placeholder="123" value="888" maxlength="4" readonly>
                    </div>
                  </div>
                </div>
              </div>@endif

              
              @if (in_array('paypal', $methods))<div class="payment-option-card" data-method="paypal">
                <div class="payment-option-header">
                  <input type="radio" name="payment-method" id="pay-paypal" value="paypal">
                  <label for="pay-paypal" class="payment-option-title" style="display: flex; align-items: center; gap: 0.4rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12 14v4" /></svg>
                    PayPal
                  </label>
                  <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); color: #003087;">PayPal</span>
                </div>
                <div class="payment-subpanel" id="paypal-details-panel" style="display: none;">
                  <p style="font-size: var(--fs-small); color: var(--color-grey-700); margin-bottom: 0;">
                    After clicking "Place Order", you will be redirected to PayPal to authorize your payment securely.
                  </p>
                </div>
              </div>@endif

              
              @if (in_array('cod', $methods))<div class="payment-option-card" data-method="cod">
                <div class="payment-option-header">
                  <input type="radio" name="payment-method" id="pay-cod" value="cod">
                  <label for="pay-cod" class="payment-option-title" style="display: flex; align-items: center; gap: 0.4rem;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5h16.5A2.25 2.25 0 0122.5 6.75v10.5a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 17.25V6.75A2.25 2.25 0 013.75 4.5z" /></svg>
                    Pay on Delivery (COD)
                  </label>
                  <span style="font-size: var(--fs-xs); font-weight: var(--fw-bold); color: var(--color-brand);">Cash / Card</span>
                </div>
                <div class="payment-subpanel" id="cod-details-panel" style="display: none;">
                  <p style="font-size: var(--fs-small); color: var(--color-grey-700); margin-bottom: 0;">
                    Pay via cash or card upon parcel delivery to your specified Australian address.
                  </p>
                </div>
              </div>@endif

            </div>
          </div>

        </div>

        
        <div class="checkout-sidebar">
          <div class="sticky-sidebar">
            
            <div class="checkout-card">
              <div class="checkout-card-header" style="margin-bottom: var(--space-md); padding-bottom: var(--space-xs);">
                <span style="font-family: var(--font-heading); font-size: var(--fs-h4); font-weight: var(--fw-bold); color: var(--color-black);">Order Summary</span>
                <span id="summary-items-count" class="badge" style="background: var(--color-grey-200); color: var(--color-dark); font-size: var(--fs-xs);">0 Items</span>
              </div>

              
              <div class="summary-items-list" id="summary-items-list">
                
              </div>

              
              <div style="border-top: 1px solid var(--color-grey-100); padding-top: var(--space-md); margin-top: var(--space-sm);">
                <button type="button" class="coupon-accordion-trigger" id="coupon-toggle-btn">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" /></svg>
                  <span>Have a coupon?</span>
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg" id="coupon-chevron" style="transition: transform var(--transition-fast);"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                </button>

                
                <div id="coupon-form-container" style="display: none; margin-bottom: var(--space-md);">
                  <div class="coupon-input-wrap">
                    <input type="text" id="coupon-code-input" class="form-input" placeholder="Enter coupon code (e.g. 10AUD)" style="text-transform: uppercase;">
                    <button type="button" class="btn btn-dark" id="apply-coupon-btn" style="padding: 0.65rem 1rem;">Apply</button>
                  </div>
                  <div id="coupon-error-msg" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.4rem; display: none;">
                    Invalid or expired coupon.
                  </div>
                </div>

                
                <div id="coupon-applied-box" class="coupon-applied-box" style="display: none;">
                  <span class="coupon-applied-text" id="coupon-applied-text">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    Coupon "10AUD" Applied
                  </span>
                  <button type="button" class="coupon-remove-btn" id="remove-coupon-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    Remove
                  </button>
                </div>
              </div>

              
              <div style="border-top: 1px solid var(--color-grey-200); padding-top: var(--space-md);">
                <div class="breakdown-row">
                  <span>Subtotal</span>
                  <span id="summary-subtotal" class="fw-semibold text-dark">$0.00</span>
                </div>
                
                <div class="breakdown-row">
                  <span>Shipping</span>
                  <span id="summary-shipping" class="fw-semibold text-dark">$10.00</span>
                </div>

                <div class="breakdown-row" id="discount-row" style="display: none; color: var(--color-success);">
                  <span>Discount</span>
                  <span id="summary-discount" class="fw-bold">-$0.00</span>
                </div>

                <div class="breakdown-row total-row">
                  <span>Total</span>
                  <span class="total-price" id="summary-total">$0.00 AUD</span>
                </div>
              </div>

              
              <button type="button" class="btn btn-primary place-order-btn" id="place-order-btn">
                Place Order • $0.00 AUD
              </button>

              <div class="security-notice">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>
                <span>Fast Australian Dispatch & Free Returns</span>
              </div>
            </div>

          </div>
        </div>

      </div>
    </div>
  </main>

  
  <div class="success-overlay" id="success-modal-overlay">
    <div class="success-modal-card">
      <div class="success-icon-badge">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:40px;height:40px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
      </div>
      <h2 style="font-family: var(--font-heading); font-size: var(--fs-h2); font-weight: var(--fw-bold); color: var(--color-black); margin-bottom: 0.5rem;">
        Order Placed Successfully!
      </h2>
      <p style="color: var(--color-grey-700); font-size: var(--fs-body); margin-bottom: var(--space-lg);">
        Thank you for shopping with {{ config('storefront.brand') }}. Your order reference is <strong id="success-order-ref" style="color: var(--color-brand);">#BNG-2026-8492</strong>.
      </p>

      <div style="background-color: var(--color-surface); border-radius: var(--radius-md); padding: var(--space-md); text-align: left; font-size: var(--fs-small); margin-bottom: var(--space-lg);">
        <div style="margin-bottom: 0.5rem;">
          <span style="color: var(--color-grey-500);">Delivering to:</span>
          <div id="success-address-summary" style="font-weight: var(--fw-bold); color: var(--color-black);">--</div>
        </div>
        <div style="margin-bottom: 0.5rem;">
          <span style="color: var(--color-grey-500);">Estimated Delivery:</span>
          <div style="font-weight: var(--fw-semibold); color: var(--color-black);">3 - 5 Business Days via Australia Post</div>
        </div>
        <div>
          <span style="color: var(--color-grey-500);">Amount Paid:</span>
          <div id="success-paid-amount" style="font-weight: var(--fw-bold); color: var(--color-brand); font-size: var(--fs-body);">--</div>
        </div>
      </div>

      <div style="display: flex; gap: var(--space-sm); justify-content: center;">
        <a href="/products" class="btn btn-primary" style="flex: 1;">Continue Shopping</a>
        <a href="/account" class="btn btn-outline" style="flex: 1;">View My Orders</a>
      </div>
    </div>
  </div>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/pages/checkout.js') }}"></script>
@endpush
