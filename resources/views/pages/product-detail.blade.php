@extends('layouts.store')
@section('title', $pageTitle ?? 'Product Details')
@section('description', $pageDescription ?? 'Handcrafted fashion, home decor, natural jute products and artisan pieces curated for Australia.')
@push('head')
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/product-detail.css') }}">
@endpush
@if (! empty($preloadImage))
@push('preload')
<link rel="preload" as="image" href="{{ \App\Support\Img::url($preloadImage, 800) }}" @if ($set = \App\Support\Img::srcset($preloadImage, [480, 800, 1200])) imagesrcset="{{ $set }}" imagesizes="(max-width: 768px) 100vw, 430px" @endif fetchpriority="high">
@endpush
@endif
@section('content')
<main class="main-content">
    <section class="product-detail-page">
      <div class="container">

        
        <div class="breadcrumb">
          <a href="/">Home</a>
          <span class="breadcrumb-sep">/</span>
          <a href="/products" id="detail-breadcrumb-category-link">Fashion</a>
          <span class="breadcrumb-sep">/</span>
          <span class="breadcrumb-current" id="detail-breadcrumb-title">Product Details</span>
        </div>

        
        <div class="product-detail-layout">

          
          <div class="product-gallery-container">
            <div class="main-image-viewport" id="main-product-image-container">
              <div id="detail-badge-wrap" class="detail-badge-tag"></div>
              <button class="detail-wishlist-trigger" id="detail-wishlist-trigger" aria-label="Add to wishlist">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
              </button>
              <img id="main-product-image" src="" alt="Product view">
              <div class="zoom-lens" id="zoom-lens"></div>
            </div>

            
            <div class="gallery-thumbnails-row" id="detail-thumbnails-list">
              
            </div>
          </div>

          
          <div class="product-purchase-container">
            
            <div class="detail-category-eyebrow" id="detail-eyebrow">FASHION · HERITAGE WEAVES</div>
            <h1 class="detail-title" id="detail-title">Loading Product...</h1>
            <p class="detail-short-desc" id="detail-short-desc">Thoughtfully designed for everyday living and Australian weather.</p>

            
            <div class="detail-rating-wrap">
              <div class="rating-stars-list">
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
              </div>
              <span class="rating-score-val" id="detail-rating-score"></span>
              <span class="rating-count-link" id="detail-rating-count"></span>
            </div>

            
            <div class="detail-price-box">
              <span class="detail-offered-price" id="detail-offered-price">A$0.00</span>
              <span class="detail-original-price" id="detail-original-price" style="display:none">A$0.00</span>
              <span class="detail-discount-pill" id="detail-discount-pill" style="display:none">SAVE 0%</span>
            </div>

            
            <div class="detail-preorder-notice" id="detail-preorder-notice" style="display:none">
              
            </div>

            
            <div class="detail-option-group" id="detail-color-group">
              <div class="detail-option-header">
                <span class="detail-option-title">COLOUR: <span class="selected-option-value" id="selected-color-name">Default</span></span>
              </div>
              <div class="detail-color-swatches-list" id="detail-color-swatches">
                
              </div>
            </div>

            
            <div class="detail-option-group" id="detail-size-group">
              <div class="detail-option-header">
                <span class="detail-option-title">SIZE: <span class="selected-option-value" id="selected-size-name">Medium (M)</span></span>
                <button class="size-guide-trigger-btn" id="btn-open-size-guide">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                  Size Guide
                </button>
              </div>
              <div class="detail-size-pills-list" id="detail-size-pills">
                
              </div>
            </div>

            
            <div class="detail-availability-wrap" id="detail-availability-wrap">
              <span class="availability-status-dot instock"></span>
              <span>In Stock — Ready to dispatch from Sydney</span>
            </div>

            
            <div class="detail-cta-row">
              <div class="detail-quantity-picker">
                <button class="qty-btn" id="qty-minus" aria-label="Decrease quantity">&minus;</button>
                <input type="number" class="qty-input-field" id="detail-qty-input" value="1" min="1" max="99" aria-label="Quantity">
                <button class="qty-btn" id="qty-plus" aria-label="Increase quantity">&plus;</button>
              </div>

              <div class="detail-actions-wrap">
                <button class="btn btn-primary btn-lg" id="detail-add-to-cart-btn">Add to Cart</button>
                <button class="btn btn-secondary-outline  btn-lg" id="detail-buy-now-btn">Buy Now</button>
              </div>
            </div>

            
            <div class="detail-trust-features">
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25h-2.25m-4.5 0H5.25m0 0a2.25 2.25 0 00-2.25 2.25v6h14.25v-6a2.25 2.25 0 00-2.25-2.25H12.75"/></svg>
                <span>West Croydon Dispatch</span>
              </div>
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Free AU Shipping over A$299</span>
              </div>
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                <span>30-Day Returns</span>
              </div>
            </div>

          </div>
        </div>

        
        <section class="product-accordions-container">
          <div class="detail-accordion-group" id="detail-accordion-group">
            
          </div>
        </section>

        
        <section class="delivery-returns-box">
          <div class="delivery-returns-grid">
            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25h-2.25m-4.5 0H5.25m0 0a2.25 2.25 0 00-2.25 2.25v6h14.25v-6a2.25 2.25 0 00-2.25-2.25H12.75"/></svg>
              <h4 class="delivery-info-title">Australian Delivery Timeframe</h4>
              <p class="delivery-info-desc">Dispatched from our Sydney hub within 24 hours. Express shipping takes 1–2 business days. Standard shipping takes 3–5 business days across AU & NZ.</p>
            </div>

            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-6h6m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h4 class="delivery-info-title">Free Shipping Rates</h4>
              <p class="delivery-info-desc">Complimentary Australia Post Standard Shipping on all orders over A$100. Flat rate A$10 shipping for orders under A$100.</p>
            </div>

            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              <h4 class="delivery-info-title">30-Day Returns & Exchanges</h4>
              <p class="delivery-info-desc">Not quite right? We offer 30-day hassle-free returns or size exchanges across Australia. Drop off at any Australia Post location.</p>
            </div>
          </div>
        </section>

        
        <section class="section" style="padding-top:0">
          <div class="section-header" style="text-align:left">
            <span class="section-eyebrow">CURATED FOR YOU</span>
            <h2 class="section-title">You May Also Like</h2>
          </div>
          <div class="listing-grid" id="related-products-grid">
            
          </div>
        </section>

        
        <section class="section" id="recently-viewed-section" style="padding-top:0; display:none">
          <div class="section-header" style="text-align:left">
            <span class="section-eyebrow">YOUR BROWSING HISTORY</span>
            <h2 class="section-title">Recently Viewed</h2>
          </div>
          <div class="listing-grid" id="recently-viewed-grid">
            
          </div>
        </section>

      </div>
    </section>
  </main>

  
  <div class="size-guide-modal-overlay" id="size-guide-modal">
    <div class="size-guide-modal-card">
      <div class="size-guide-header">
        <h3 class="size-guide-title">{{ config('storefront.brand') }} — Size & Measurement Guide</h3>
        <button class="size-guide-close" id="size-guide-close" aria-label="Close size guide">&times;</button>
      </div>
      <div class="size-guide-body">
        <p style="font-size: var(--fs-small); color: var(--color-grey-700)">
          All measurements are taken flat in centimetres (cm). Our apparel is tailored for a relaxed Australian fit. If you prefer a tighter fit, we recommend sizing down.
        </p>

        <div class="size-table-wrapper">
          <table class="size-guide-table">
            <thead>
              <tr>
                <th>Size</th>
                <th>Chest Width</th>
                <th>Body Length</th>
                <th>Shoulder Width</th>
                <th>AU Standard Equivalent</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><strong>S</strong></td>
                <td>52 cm</td>
                <td>71 cm</td>
                <td>46 cm</td>
                <td>AU 36–38 (Small)</td>
              </tr>
              <tr>
                <td><strong>M</strong></td>
                <td>55 cm</td>
                <td>73 cm</td>
                <td>48 cm</td>
                <td>AU 38–40 (Medium)</td>
              </tr>
              <tr>
                <td><strong>L</strong></td>
                <td>58 cm</td>
                <td>75 cm</td>
                <td>50 cm</td>
                <td>AU 40–42 (Large)</td>
              </tr>
              <tr>
                <td><strong>XL</strong></td>
                <td>61 cm</td>
                <td>77 cm</td>
                <td>52 cm</td>
                <td>AU 42–44 (X-Large)</td>
              </tr>
              <tr>
                <td><strong>2XL</strong></td>
                <td>64 cm</td>
                <td>79 cm</td>
                <td>54 cm</td>
                <td>AU 44–46 (2X-Large)</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  
  <div class="modal-overlay" id="quickview-modal">
    <div class="modal-card">
      <button class="modal-close" id="modal-close" aria-label="Close quick view">&times;</button>
      <div class="modal-gallery">
        <img src="" alt="" id="modal-product-img">
      </div>
      <div class="modal-content">
        <div>
          <div class="modal-category-tag" id="modal-category-tag">Fashion</div>
          <h3 class="modal-title"></h3>
          <p class="modal-subtitle"></p>
        </div>
        <div class="modal-price"></div>
        
        <div class="modal-options-section" id="modal-colors-wrap">
          <p class="modal-option-label">Colour / Finish</p>
          <div class="modal-colors"></div>
        </div>
        
        <div class="modal-options-section" id="modal-sizes-wrap">
          <p class="modal-option-label">Size / Dimension</p>
          <div class="modal-sizes"></div>
        </div>

        <button class="btn btn-primary btn-lg" id="modal-add-to-cart" style="width:100%; margin-top: 1rem">Add to Cart</button>
      </div>
    </div>
  </div>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/product-detail.js') }}"></script>
@endpush
