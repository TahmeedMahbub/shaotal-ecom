@extends('layouts.store')
@section('title', "Find Something Aesthetic \u2014 Bongo Curated")
@section('description', "Have a unique handcrafted product in mind? Request custom fashion, home decor, jute items, or artisan pieces and we will source and deliver them directly to your door in Australia.")
@section('content')
<main style="padding: 2.5rem 0 5rem 0; background: var(--color-surface); min-height: 80vh;">
    <div class="container">
      
      
      <nav aria-label="Breadcrumb" style="margin-bottom: 1.5rem;">
        <ol style="display: flex; gap: 0.5rem; align-items: center; list-style: none; padding: 0; margin: 0; font-size: var(--fs-small); color: var(--color-grey-500);">
          <li><a href="/" style="color: var(--color-grey-600); text-decoration: underline;">Home</a></li>
          <li>/</li>
          <li style="color: var(--color-dark); font-weight: var(--fw-semibold);">Request Sourcing</li>
        </ol>
      </nav>

      
      <div style="text-align: center; max-width: 720px; margin: 0 auto 3rem auto;">
        <span class="section-kicker" style="margin-bottom: 0.5rem; display: inline-block;">SPECIAL SOURCING SERVICE</span>
        <h1 style="font-family: var(--font-heading); font-size: clamp(2rem, 3.5vw, 2.75rem); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 1rem; line-height: 1.2;">
          Find Something Aesthetic
        </h1>
        <p style="font-size: var(--fs-body-lg); color: var(--color-grey-700); line-height: 1.6; margin: 0;">
          Have something unique in mind? Whether it's custom flax linen apparel, heirloom Nakshi Kantha textiles, handcrafted brassware, or natural jute goods — tell us what you're looking for. Our dedicated sourcing team will inspect, verify quality, and deliver it directly to your doorstep anywhere in Australia.
        </p>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 340px; gap: 2rem; align-items: start;" class="request-layout-grid">
        
        
        <div class="shop-your-way-card" style="padding: 2.5rem; background: var(--color-surface-card); border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200); box-shadow: var(--shadow-sm);">
          
          <div id="request-form-wrapper">
            <h2 style="font-family: var(--font-heading); font-size: var(--fs-h3); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 1.5rem; border-bottom: 1px solid var(--color-grey-200); padding-bottom: 0.75rem;">
              Product Request Details
            </h2>

            <form id="sourcing-request-form" novalidate>
              
              
              <div style="margin-bottom: 1.5rem;">
                <label for="request-item-name" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                  What are you looking for? <span style="color: var(--color-danger)">*</span>
                </label>
                <textarea id="request-item-name" rows="3" placeholder="e.g. Traditional Jamdani saree, Nakshi Kantha cushion set, custom linen shirt, hammered brass vase..." style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card); resize: vertical;" required></textarea>
                <div class="field-error-msg" id="error-item-name" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please describe the item you are looking for.</div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.5rem;" class="form-row-2col">
                
                <div>
                  <label for="request-product-link" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Product Link <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                  </label>
                  <input type="url" id="request-product-link" placeholder="https://instagram.com/p/... or website link" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);">
                </div>

                
                <div>
                  <label for="request-variant" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Preferred Color / Size <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                  </label>
                  <input type="text" id="request-variant" placeholder="e.g. Size L, Terracotta / Olive" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);">
                </div>
              </div>

              
              <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                  Upload Image / Screenshot <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                </label>
                
                <div class="file-upload-zone" id="file-upload-zone">
                  <div class="file-upload-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
                  </div>
                  <span class="file-upload-text">Drag & drop image here or click to browse</span>
                  <span class="file-upload-subtext">Supports JPG, PNG, WEBP (Max 10MB per file)</span>
                  <input type="file" id="request-file-input" accept="image/*" multiple style="display: none;">
                </div>

                
                <div class="file-preview-list" id="file-preview-list"></div>
              </div>

              <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.25rem; margin-bottom: 1.5rem;" class="form-row-qty-details">
                
                <div>
                  <label for="request-quantity" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Quantity <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="number" id="request-quantity" min="1" value="1" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-quantity" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Quantity must be at least 1.</div>
                </div>

                
                <div>
                  <label for="request-details" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Additional Details <span style="color: var(--color-grey-500); font-weight: var(--fw-normal)">(Optional)</span>
                  </label>
                  <input type="text" id="request-details" placeholder="Any specific materials, deadline, or notes..." style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);">
                </div>
              </div>

              
              <h2 style="font-family: var(--font-heading); font-size: var(--fs-h3); font-weight: var(--fw-bold); color: var(--color-dark); margin: 2rem 0 1.25rem 0; border-bottom: 1px solid var(--color-grey-200); padding-bottom: 0.75rem;">
                Contact & Delivery Info
              </h2>

              <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;" class="form-row-3col">
                
                <div>
                  <label for="request-name" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Your Name <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="request-name" placeholder="Full Name" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-name" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please enter your name.</div>
                </div>

                
                <div>
                  <label for="request-contact" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    Email or Phone Number <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="request-contact" placeholder="name@example.com.au or 0400 000 000" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-contact" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please enter your email or phone number.</div>
                </div>

                
                <div>
                  <label for="request-postcode" style="display: block; font-size: var(--fs-small); font-weight: var(--fw-semibold); color: var(--color-dark); margin-bottom: 0.5rem;">
                    AU Delivery Postcode <span style="color: var(--color-danger)">*</span>
                  </label>
                  <input type="text" id="request-postcode" placeholder="e.g. 2000, 3000, 4000" maxlength="4" style="width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--color-grey-300); border-radius: var(--radius-md); font-family: var(--font-body); font-size: var(--fs-body); color: var(--color-dark); background: var(--color-surface-card);" required>
                  <div class="field-error-msg" id="error-postcode" style="color: var(--color-danger); font-size: var(--fs-xs); margin-top: 0.25rem; display: none;">Please enter a valid 4-digit Australian postcode.</div>
                </div>
              </div>

              
              <button type="submit" class="btn btn-primary btn-lg" style="width: 100%; padding: 0.95rem; font-size: 1.05rem; font-weight: var(--fw-bold);">
                Submit Request
              </button>

            </form>
          </div>

          
          <div id="request-success-wrapper" style="display: none; text-align: center; padding: 2rem 1rem;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: hsl(120, 35%, 92%); color: var(--color-success); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem auto;">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 32px; height: 32px;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            
            <h2 style="font-family: var(--font-heading); font-size: var(--fs-h2); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.75rem;">
              Request Submitted Successfully!
            </h2>
            
            <p style="color: var(--color-grey-700); font-size: var(--fs-body-lg); max-width: 540px; margin: 0 auto 2rem auto; line-height: 1.6;">
              Thank you, <strong id="submitted-user-name">Customer</strong>! We have received your sourcing request for <strong id="submitted-item-name">your requested item</strong>. Our sourcing team will review your specifications and contact you at <strong id="submitted-contact">your contact info</strong> within 24–48 hours with pricing and availability.
            </p>

            <div style="background: var(--color-surface-alt); border-radius: var(--radius-md); padding: 1.25rem; max-width: 480px; margin: 0 auto 2rem auto; text-align: left; border: 1px solid var(--color-grey-200); font-size: var(--fs-small);">
              <div style="font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.5rem;">Request Summary:</div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span style="color: var(--color-grey-600)">Requested Item:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-dark)" id="summary-item-name">-</span>
              </div>
              <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                <span style="color: var(--color-grey-600)">Quantity:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-dark)" id="summary-quantity">-</span>
              </div>
              <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--color-grey-600)">AU Delivery Postcode:</span>
                <span style="font-weight: var(--fw-semibold); color: var(--color-dark)" id="summary-postcode">-</span>
              </div>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
              <button type="button" class="btn btn-outline" id="btn-submit-another" style="min-width: 200px;">Submit Another Request</button>
              <a href="/products" class="btn btn-primary" style="min-width: 200px;">Explore Our Collection</a>
            </div>
          </div>

        </div>

        
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
          
          <div style="background: var(--color-surface-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200);">
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--color-brand);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:22px; height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751A11.959 11.959 0 0112 2.714z"/></svg>
              </div>
              <div>
                <h4 style="font-family: var(--font-heading); font-size: var(--fs-body); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">Authentic Sourcing</h4>
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Direct partnership with master weavers, ceramicists, brassware artisans, and certified apparel makers.</p>
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
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Full door-to-door tracking with Australian customs clearance handled seamlessly for you.</p>
              </div>
            </div>
          </div>

          <div style="background: var(--color-surface-card); padding: 1.5rem; border-radius: var(--radius-lg); border: 1px solid var(--color-grey-200);">
            <div style="display: flex; align-items: flex-start; gap: 1rem;">
              <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--color-surface-alt); display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--color-brand);">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:22px; height:22px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              </div>
              <div>
                <h4 style="font-family: var(--font-heading); font-size: var(--fs-body); font-weight: var(--fw-bold); color: var(--color-dark); margin-bottom: 0.25rem;">Transparent Pricing</h4>
                <p style="font-size: var(--fs-xs); color: var(--color-grey-600); line-height: 1.5; margin: 0;">Clear upfront itemized quotes including sourcing, quality check, and shipping — zero hidden charges.</p>
              </div>
            </div>
          </div>

        </div>

      </div>

    </div>
  </main>

  
  

  
  <div class="bottom-nav" id="bottom-nav">
    <div class="bottom-nav-inner" id="bottom-nav-inner">
      
    </div>
  </div>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/pages/request.js') }}"></script>
@endpush
