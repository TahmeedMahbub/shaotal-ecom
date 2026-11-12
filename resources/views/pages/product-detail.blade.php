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
@php
  $cur = config('storefront.currency_symbol');
  $mainImg = $gallery[0] ?? null;
  $orig = ($product['originalPrice'] && $initialPrice == $product['price']) ? (float) $product['originalPrice'] : null;
  $releaseDate = $product['releaseDate'] ? \Illuminate\Support\Carbon::parse($product['releaseDate'])->format('j F Y') : null;
  $specs = $product['specs'] ?? [];
  $description = $product['fullDesc'] ?: $product['desc'];
  [$selColor, $selSize] = $initialOptions;
  $colorNames = $product['colorNames'] ?? [];
@endphp
@section('content')
<main class="main-content">
    <section class="product-detail-page">
      <div class="container">

        
        <div class="breadcrumb">
          <a href="/">Home</a>
          <span class="breadcrumb-sep">/</span>
          <a href="{{ \App\Support\Seo::listingUrl($product['category']) }}" id="detail-breadcrumb-category-link">{{ $product['categoryName'] }}</a>
          <span class="breadcrumb-sep">/</span>
          <span class="breadcrumb-current" id="detail-breadcrumb-title">{{ $product['name'] }}</span>
        </div>

        
        <div class="product-detail-layout">

          
          <div class="product-gallery-container">
            <div class="main-image-viewport" id="main-product-image-container">
              <div id="detail-badge-wrap" class="detail-badge-tag"></div>
              <button class="detail-wishlist-trigger" id="detail-wishlist-trigger" aria-label="Add to wishlist">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
              </button>
              <img id="main-product-image" src="{{ \App\Support\Img::url($mainImg, 800) ?: \App\Support\Img::PLACEHOLDER }}" @if ($set = \App\Support\Img::srcset($mainImg, [480, 800, 1200])) srcset="{{ $set }}" sizes="(max-width: 768px) 100vw, 430px" @endif width="800" height="800" alt="{{ $product['name'] }}" fetchpriority="high" decoding="async">
              <div class="zoom-lens" id="zoom-lens"></div>
            </div>

            
            <div class="gallery-thumbnails-row" id="detail-thumbnails-list">
              {{-- Same thumbnails renderProductGallery() builds (keeps the gallery height stable when it runs). --}}
              @foreach ($gallery as $ti => $thumb)
                <div class="gallery-thumb-item {{ $ti === 0 ? 'active' : '' }}" data-thumb-index="{{ $ti }}"><img src="{{ \App\Support\Img::url($thumb, 160) ?: \App\Support\Img::PLACEHOLDER }}" alt="Product View {{ $ti + 1 }}" width="84" height="84" loading="lazy" decoding="async"></div>
              @endforeach
            </div>
          </div>

          
          <div class="product-purchase-container">
            
            <div class="detail-category-eyebrow" id="detail-eyebrow">{{ $product['categoryName'] }} · {{ $product['brand'] ?: config('storefront.brand') }}</div>
            <h1 class="detail-title" id="detail-title">{{ $product['name'] }}</h1>
            <p class="detail-short-desc" id="detail-short-desc">{{ \App\Support\Seo::text($product['desc'], 500) }}</p>

            
            <div class="detail-rating-wrap" @if (! $product['reviewCount']) style="display:none" @endif>
              <div class="rating-stars-list">
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
                <svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>
              </div>
              <span class="rating-score-val" id="detail-rating-score">@if ($product['reviewCount']){{ number_format($product['rating'], 1) }} / 5.0 @endif</span>
              <span class="rating-count-link" id="detail-rating-count">@if ($product['reviewCount'])({{ $product['reviewCount'] }} customer review{{ $product['reviewCount'] === 1 ? '' : 's' }})@endif</span>
            </div>

            
            <div class="detail-price-box">
              <span class="detail-offered-price" id="detail-offered-price">{{ $cur }}{{ number_format($initialPrice, 2) }}</span>
              <span class="detail-original-price" id="detail-original-price" @if (! ($orig && $orig > $initialPrice)) style="display:none" @endif>{{ $cur }}{{ number_format($orig ?? 0, 2) }}</span>
              <span class="detail-discount-pill" id="detail-discount-pill" style="display:none">SAVE 0%</span>
            </div>

            
            <div class="detail-preorder-notice" id="detail-preorder-notice" style="display:none">
              
            </div>

            
            {{-- Options server-rendered with the markup renderColorSwatches()/renderSizePills() produce (no layout shift). --}}
            <div class="detail-option-group" id="detail-color-group" @if (! $product['colors']) style="display:none" @endif>
              <div class="detail-option-header">
                <span class="detail-option-title">COLOUR: <span class="selected-option-value" id="selected-color-name">{{ $selColor !== null ? ($colorNames[$selColor] ?? $selColor) : 'Default' }}</span></span>
              </div>
              <div class="detail-color-swatches-list" id="detail-color-swatches">
                @foreach ($product['colors'] as $hex)
                  <button class="detail-color-swatch-item {{ $hex === $selColor ? 'active' : '' }}" style="background-color: {{ $hex }}" data-color-hex="{{ $hex }}" aria-label="Select color {{ $colorNames[$hex] ?? $hex }}"></button>
                @endforeach
              </div>
            </div>

            
            <div class="detail-option-group" id="detail-size-group" @if (! $product['sizes']) style="display:none" @endif>
              <div class="detail-option-header">
                <span class="detail-option-title">SIZE: <span class="selected-option-value" id="selected-size-name">{{ $selSize ?? 'Medium (M)' }}</span></span>
                <button type="button" class="size-guide-trigger-btn" id="btn-open-size-guide" data-guide-key="{{ $sizeGuideKey }}" @if (! $sizeGuideKey) hidden @endif aria-haspopup="dialog" aria-controls="size-guide-modal">
                  <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                  Size Guide
                </button>
              </div>
              <div class="detail-size-pills-list" id="detail-size-pills">
                @foreach ($product['sizes'] as $sz)
                  @php $off = ! $sizeOffered($selColor, $sz); @endphp
                  <button class="detail-size-pill {{ $sz === $selSize ? 'active' : '' }} {{ $off ? 'disabled' : '' }}" data-size="{{ $sz }}" @if ($off) disabled @endif>{{ $sz }}</button>
                @endforeach
              </div>
            </div>

            
            <div class="detail-availability-wrap" id="detail-availability-wrap">
              {{-- Same wording as renderAvailabilityBadge() in product-detail.js; based on the admin availability flag, not stock. --}}
              @if ($product['availability'] === 'preorder')
                <span class="availability-status-dot preorder"></span>
                <span>Pre-order Item{{ $releaseDate ? ' — Expected dispatch '.$releaseDate : '' }}</span>
              @elseif ($product['soldOut'])
                <span class="availability-status-dot lowstock"></span>
                <span>Currently unavailable</span>
              @elseif ($product['availability'] === 'lowstock')
                <span class="availability-status-dot lowstock"></span>
                <span>Low Stock — Only {{ $product['stockQty'] }} left</span>
              @else
                <span class="availability-status-dot instock"></span>
                <span>{{ \App\Support\Delivery::text('dispatch_status') ?? 'In Stock' }}</span>
              @endif
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
              @if ($dp = \App\Support\Delivery::text('dispatch_point'))
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25h-2.25m-4.5 0H5.25m0 0a2.25 2.25 0 00-2.25 2.25v6h14.25v-6a2.25 2.25 0 00-2.25-2.25H12.75"/></svg>
                <span>{{ $dp }}</span>
              </div>
              @endif
              @if ($fs = \App\Support\Delivery::text('free_shipping_short'))
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $fs }}</span>
              </div>
              @endif
              @if ($rs = \App\Support\Delivery::text('returns_short'))
              <div class="trust-feature-item">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                <span>{{ $rs }}</span>
              </div>
              @endif
            </div>

          </div>
        </div>

        
        <section class="product-accordions-container">
          {{-- Same sections, order and markup as renderAccordions() in product-detail.js (no growth when it runs). --}}
          @php
            $chev = '<svg class="accordion-icon-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>';
            $para = fn ($t) => '<p>'.nl2br(e($t), false).'</p>';
            $accordions = [];
            if ($description) {
                // allowlist-sanitised admin rich text (App\Support\SafeHtml); plain text keeps its line breaks
                $accordions[] = ['Description & Craft Story', preg_match('/<[a-z][\s\S]*>/i', $description) ? $description : $para($description)];
            }
            $materials = implode(' · ', array_filter([$specs['material'] ?? null, $specs['fabric'] ?? null, ! empty($specs['pattern']) ? 'Pattern: '.$specs['pattern'] : null]));
            if ($materials !== '') { $accordions[] = ['Materials & Fabric', $para($materials)]; }
            $fit = implode("\n", array_filter([! empty($specs['dimensions']) ? 'Dimensions: '.$specs['dimensions'] : null, ! empty($specs['fit']) ? 'Fit: '.$specs['fit'] : null]));
            if ($fit !== '') { $accordions[] = ['Dimensions & Fit', $para($fit)]; }
            if (! empty($specs['care'])) { $accordions[] = ['Care Instructions', $para($specs['care'])]; }
          @endphp
          <div class="detail-accordion-group" id="detail-accordion-group">
            @foreach ($accordions as $i => [$accTitle, $accHtml])
              <div class="detail-accordion-item {{ $i === 0 ? 'open' : '' }}">
                <button class="accordion-header-btn"><span>{{ $accTitle }}</span>{!! $chev !!}</button>
                <div class="accordion-content-panel" style="{{ $i === 0 ? 'max-height: 500px;' : '' }}">
                  <div class="accordion-inner-body">{!! $accHtml !!}</div>
                </div>
              </div>
            @endforeach
          </div>
        </section>

        
        <section class="delivery-returns-box">
          <div class="delivery-returns-grid">
            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.25h-2.25m-4.5 0H5.25m0 0a2.25 2.25 0 00-2.25 2.25v6h14.25v-6a2.25 2.25 0 00-2.25-2.25H12.75"/></svg>
              <h4 class="delivery-info-title">{{ \App\Support\Delivery::text('timeframe_title') }}</h4>
              <p class="delivery-info-desc">{{ \App\Support\Delivery::text('timeframe') }}</p>
            </div>

            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-6h6m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <h4 class="delivery-info-title">{{ \App\Support\Delivery::text('rates_title') }}</h4>
              <p class="delivery-info-desc">{{ \App\Support\Delivery::text('rates') }}</p>
            </div>

            <div class="delivery-info-col">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              <h4 class="delivery-info-title">{{ \App\Support\Delivery::text('returns_title') }}</h4>
              <p class="delivery-info-desc">{{ \App\Support\Delivery::text('returns_text') }} <a href="/returns" style="color: var(--color-brand);">Returns information</a></p>
            </div>
          </div>
        </section>

        
        <section class="section" style="padding-top:0">
          <div class="section-header" style="text-align:left">
            <span class="section-eyebrow">CURATED FOR YOU</span>
            <h2 class="section-title">You May Also Like</h2>
          </div>
          <div class="listing-grid" id="related-products-grid">
            @foreach ($related as $p)
              @include('partials.product-card', ['product' => $p])
            @endforeach
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
        <div class="size-guide-heading">
          <h3 class="size-guide-title">Size Guide</h3>
          <p class="size-guide-subtitle"></p>
        </div>
        <div class="size-guide-actions">
          <div class="size-unit-toggle" id="size-unit-toggle" role="radiogroup" aria-label="Measurement unit" hidden></div>
          <button class="size-guide-close" id="size-guide-close" aria-label="Close size guide">&times;</button>
        </div>
      </div>
      <div class="size-guide-body">
        <p class="size-guide-status" style="font-size: var(--fs-small); color: var(--color-grey-700)">Loading size guide…</p>
      </div>
    </div>
  </div>

  
  <div class="modal-overlay" id="quickview-modal">
    <div class="modal-card">
      <button class="modal-close" id="modal-close" aria-label="Close quick view">&times;</button>
      <div class="modal-gallery">
        <img alt="" id="modal-product-img">
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
<script src="{{ \App\Support\Assets::url('assets/js/size-guide-units.js') }}"></script>
<script src="{{ \App\Support\Assets::url('assets/js/product-detail.js') }}"></script>
@endpush
