@extends('layouts.store')
@section('title', "Bulk & Corporate Orders \u2014 Bongo Curated")
@section('description', "Request bulk volume pricing, corporate gift sets, wedding favors, and custom product orders with Bongo Curated Australia.")
@push('head')
<style>
    /* Select2 Custom Design System Integration */
    .select2-container--default .select2-selection--single {
      height: 48px;
      padding: 6px 12px;
      border: 1px solid var(--color-grey-300);
      border-radius: var(--radius-md);
      background-color: var(--color-surface-card);
      display: flex;
      align-items: center;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .select2-container--default .select2-selection--single:focus,
    .select2-container--default.select2-container--open .select2-selection--single {
      border-color: var(--color-brand);
      outline: none;
      box-shadow: 0 0 0 3px rgba(204, 122, 63, 0.12);
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
      color: var(--color-dark);
      font-family: var(--font-body);
      font-size: var(--fs-body);
      font-weight: var(--fw-semibold);
      padding-left: 0;
      line-height: normal;
      width: 100%;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
      height: 46px;
      right: 12px;
    }
    .select2-dropdown {
      border: 1px solid var(--color-grey-300);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-md);
      z-index: 9999;
      overflow: hidden;
    }
    .select2-results__option {
      padding: 8px 12px;
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
      background-color: var(--color-surface-alt);
      color: var(--color-brand);
    }
    .select2-search--dropdown .select2-search__field {
      border: 1px solid var(--color-grey-300);
      border-radius: var(--radius-sm);
      padding: 8px 12px;
      font-family: var(--font-body);
      font-size: var(--fs-small);
    }

    /* Product Card Styling */
    .bulk-product-card {
      background: var(--color-surface-card);
      border: 1px solid var(--color-grey-300);
      border-radius: var(--radius-md);
      padding: 1.25rem;
      margin-bottom: 1.25rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .bulk-product-card:hover {
      border-color: var(--color-brand);
    }
    .product-image-box {
      width: 72px;
      height: 72px;
      border-radius: var(--radius-md);
      background: var(--color-surface-alt);
      border: 1px solid var(--color-grey-300);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
    }
    .product-image-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* Variant Row Styling */
    .bulk-variant-row {
      display: grid;
      grid-template-columns: 1fr 1fr 120px 36px;
      gap: 0.75rem;
      align-items: center;
      margin-bottom: 0.75rem;
      background: var(--color-surface-alt);
      padding: 0.75rem;
      border-radius: var(--radius-sm);
      border: 1px solid var(--color-grey-200);
    }
    @media (max-width: 640px) {
      .bulk-variant-row {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
      }
    }

    .btn-remove-item {
      color: var(--color-danger);
      background: none;
      border: none;
      font-size: 1.25rem;
      cursor: pointer;
      line-height: 1;
      padding: 0.25rem 0.5rem;
      border-radius: var(--radius-sm);
    }
    .btn-remove-item:hover {
      background: hsl(0, 75%, 95%);
    }

    /* Separate Unlisted Section */
    .unlisted-section-card {
      margin-top: 2.5rem;
      margin-bottom: 2.5rem;
      padding: 1.5rem;
      background: var(--color-surface-card);
      border: 1px solid var(--color-grey-300);
      border-radius: var(--radius-md);
    }
  </style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
@endpush
@section('content')
<main class="main-content" style="padding: 2.5rem 0 5rem 0;">
    <div class="container">

      
      <nav class="breadcrumb" style="margin-bottom: 2rem;" aria-label="Breadcrumb">
        <ol style="display: flex; gap: 0.5rem; align-items: center; list-style: none; padding: 0; margin: 0; font-size: var(--fs-small);">
          <li><a href="/" style="color: var(--color-grey-600);">Home</a></li>
          <li style="color: var(--color-grey-400);">/</li>
          <li style="color: var(--color-dark); font-weight: var(--fw-semibold);">Bulk & Corporate Orders</li>
        </ol>
      </nav>

      
      <div style="text-align: center; max-width: 720px; margin: 0 auto 3rem auto;">
        <span class="section-kicker" style="margin-bottom: 0.5rem; display: inline-block;">VOLUME & CORPORATE INQUIRIES</span>
        <h1 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.5vw, 2.75rem); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 1rem; line-height: 1.2;">
          Bulk & Corporate Orders
        </h1>
        <p style="font-size: var(--fs-body-lg); color: var(--color-grey-700); line-height: 1.6; margin: 0;">
          Planning a wedding, corporate gift set, boutique retail order, or large event? Select catalog products or request unlisted custom items below. Our team will prepare a volume quote with tailored pricing and estimated delivery.
        </p>
      </div>

      
      <div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start;" class="request-layout-grid">
        
        
        <div class="shop-your-way-card" style="padding: 2.5rem; background: var(--color-surface-card); border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200); box-shadow: var(--shadow-sm);">
          
          <div id="bulk-form-wrapper">
            <form id="bulk-order-form" novalidate>
              
              
              <div style="margin-bottom: 2.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--color-grey-200); padding-bottom: 0.75rem;">
                  <h2 style="font-family: var(--font-heading); font-size: var(--fs-h3); font-weight: var(--fw-bold); color: var(--color-dark); margin: 0;">
                    1. Select Catalog Products
                  </h2>
                  <span id="bulk-total-units-badge" style="font-size: var(--fs-xs); font-weight: var(--fw-bold); color: var(--color-brand); background: var(--color-surface-alt); padding: 0.35rem 0.75rem; border-radius: 99px;">0 Total Units</span>
                </div>

                
                <div id="bulk-products-container">
                  
                </div>

                
                <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
                  <button type="button" class="btn btn-primary" id="btn-add-catalog-product" style="padding: 0.65rem 1.25rem; font-size: 0.95rem; font-weight: var(--fw-semibold);">
                    + Add Product
                  </button>
                </div>
              </div>

              
              <div class="unlisted-section-card">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                  <div>
                    <h3 style="font-family: var(--font-heading); font-size: var(--fs-h3); font-weight: var(--fw-bold); color: var(--color-dark); margin: 0 0 0.25rem 0;">
                      Looking for something not listed?
                    </h3>
                    <p style="font-size: var(--fs-small); color: var(--color-grey-600); margin: 0;">
                      Request bespoke products, custom embroidery, or items not currently listed in our online catalog.
                    </p>
                  </div>
                  <button type="button" class="btn btn-outline" id="btn-add-custom-product" style="padding: 0.6rem 1.15rem; font-size: 0.9rem; font-weight: var(--fw-semibold);">
                    + Request Unlisted Item
                  </button>
                </div>

                <div id="unlisted-items-container">
                  
                </div>
              </div>

              
              <h2 style="font-family: var(--font-heading); font-size: var(--fs-h3); font-weight: var(--fw-bold); color: var(--color-dark); margin: 2.5rem 0 1.25rem 0; border-bottom: 1px solid var(--color-grey-200); padding-bottom: 0.75rem;">
                2. Contact & Australian Delivery Info
              </h2>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;" class="form-row-2col">
                <div>
                  <label for="bulk-name" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Your Full Name / Company Name <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="bulk-name" placeholder="Full Name or Business Name" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-bulk-name" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please enter your name or company.</div>
                </div>

                <div>
                  <label for="bulk-contact" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Email or Phone Number <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="bulk-contact" placeholder="name@example.com.au or 0400 000 000" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-bulk-contact" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please enter your email or phone.</div>
                </div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;" class="form-row-2col">
                <div>
                  <label for="bulk-postcode" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    AU Delivery Postcode & Suburb <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="bulk-postcode" placeholder="e.g. 2000 Sydney NSW" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-bulk-postcode" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please specify delivery postcode & suburb.</div>
                </div>

                <div>
                  <label for="bulk-target-date" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Target Event / Delivery Date <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                  </label>
                  <input type="date" id="bulk-target-date" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);">
                </div>
              </div>

              <div style="margin-bottom: 2rem;">
                <label for="bulk-notes" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                  Special Notes / Gift Packaging / Customization Details <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                </label>
                <textarea id="bulk-notes" rows="3" placeholder="Specify any custom branding, gift wrapping, split shipping, or special timeline requirements..." style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card); resize: vertical;"></textarea>
              </div>

              
              <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; padding: 0.95rem; font-size: 1.05rem; font-weight: var(--fw-bold);">
                Submit Bulk Order Request
              </button>

            </form>
          </div>

          
          <div id="bulk-success-wrapper" style="display: none; text-align: center; padding: 2rem 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: hsl(120, 35%, 92%); color: var(--color-success); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 32px; height: 32px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            
            <h2 style="font-family: var(--font-heading); font-size: var(--fs-h2); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.75rem;">
              Bulk Order Request Submitted!
            </h2>
            
            <p style="color: var(--color-grey-700); font-size: var(--fs-body-lg); max-width: 540px; margin: 0 auto 2rem auto; line-height: 1.6;">
              Thank you, <strong id="submitted-bulk-name">Customer</strong>! We have received your bulk order request (<strong id="submitted-bulk-ref">BULK-1001</strong>). Our corporate sales team will review your specifications and contact you at <strong id="submitted-bulk-contact">contact info</strong> within 24 hours with custom volume pricing.
            </p>

            <div style="background: var(--color-surface-alt); border-radius: var(--radius-md); padding: 1.25rem; max-width: 520px; margin: 0 auto 2rem auto; text-align: left; border: 1px solid var(--color-grey-200); font-size: var(--fs-small);">
              <div style="font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.75rem; border-bottom: 1px solid var(--color-grey-200); padding-bottom: 0.35rem;">Request Summary:</div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="color: var(--color-grey-600)">Total Products Requested:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-dark)" id="summary-bulk-products-count">0 Products</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="color: var(--color-grey-600)">Total Units Requested:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-brand)" id="summary-bulk-total-units">0 Units</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem;">
                <span style="color: var(--color-grey-600)">AU Delivery Postcode:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-dark)" id="summary-bulk-postcode">-</span>
              </div>
              <div id="summary-bulk-items-list" style="margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed var(--color-grey-300);"></div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
              <button type="button" class="btn btn-outline" id="btn-submit-another-bulk" style="min-width: 200px;">Submit Another Request</button>
              <a href="/account" class="btn btn-secondary" style="min-width: 180px;">View My Account</a>
              <a href="/products" class="btn btn-primary" style="min-width: 200px;">Explore Products</a>
            </div>
          </div>

        </div>

        
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          
          <div style="background: var(--color-surface-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200);">
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--color-brand);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:22px; height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div>
                <h4 style="font-family: var(--font-heading); font-size: var(--fs-body); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">Tiered Volume Rates</h4>
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Special volume discounts applied based on overall unit quantity and product categories.</p>
              </div>
            </div>
          </div>

          <div style="background: var(--color-surface-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200);">
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--color-brand);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:22px; height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </div>
              <div>
                <h4 style="font-family: var(--font-heading); font-size: var(--fs-body); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">Dedicated Account Support</h4>
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Personal corporate account manager to coordinate custom branding, packaging, and logistics.</p>
              </div>
            </div>
          </div>

          <div style="background: var(--color-surface-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200);">
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--color-brand);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:22px; height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5"/></svg>
              </div>
              <div>
                <h4 style="font-family: var(--font-heading); font-size: var(--fs-body); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">Australia Express Shipping</h4>
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Door-to-door freight dispatch with trackable delivery nationwide across Australia.</p>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </main>
@endsection
@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="{{ \App\Support\Assets::url('assets/js/pages/bulk-order.js') }}"></script>
@endpush
