@extends('layouts.store')
@section('title', "Shaotal - Crafted for YOU")
@section('description', "Discover thoughtfully selected fashion, home decor, natural jute, handcrafted pieces and gifts curated for Australian living.")
@if (! empty($boot['hero'][0]['imageSrc']))
@push('preload')
@php($h0 = $boot['hero'][0])
{{-- The first hero image is the LCP element: one preload per viewport, matching the <picture> below exactly. --}}
<link rel="preload" as="image" href="{{ $h0['imageSrc'] }}" @if ($h0['srcset'] ?? null) imagesrcset="{{ $h0['srcset'] }}" imagesizes="(max-width: 992px) 100vw, (max-width: 1400px) 75vw, 1000px" @endif media="(min-width: 769px)" fetchpriority="high">
<link rel="preload" as="image" href="{{ $h0['imageSrcMobile'] ?? $h0['imageSrc'] }}" @if ($h0['srcsetMobile'] ?? null) imagesrcset="{{ $h0['srcsetMobile'] }}" imagesizes="100vw" @endif media="(max-width: 768px)" fetchpriority="high">
@endpush
@endif
@section('content')
<main class="main-content">

  
  <section class="hero-section" id="hero-section">
    <div class="container hero-container">
      <div class="hero-desktop-grid">
        
        <div class="hero" id="hero" role="region" aria-label="Featured Collection Highlights" aria-roledescription="carousel">
          {{-- First slide server-rendered (same markup as renderHero() in app.js, which then builds the full carousel):
               the headline is the page's h1 and the image starts loading before any script runs. --}}
          @if ($slide = $boot['hero'][0] ?? null)
            <a href="{{ \App\Support\SafeHtml::link($slide['ctaLink']) }}" class="hero-slide active" data-slide="0" role="group" aria-roledescription="slide" aria-label="Slide 1 of {{ count($boot['hero']) }}: {{ $slide['headline'] }}" aria-hidden="false">
              {{-- Responsive first slide (same markup renderHero() builds): phones get the mobile source, desktops a
                   width-matched candidate; eager + high priority because it is the LCP image. --}}
              <div class="hero-slide-bg">
                <picture>
                  <source media="(max-width: 768px)" srcset="{{ $slide['srcsetMobile'] ?? $slide['imageSrcMobile'] }}" @if ($slide['srcsetMobile'] ?? null) sizes="100vw" @endif>
                  <img class="hero-slide-img" src="{{ $slide['imageSrc'] ?: $slide['image'] }}" @if ($slide['srcset'] ?? null) srcset="{{ $slide['srcset'] }}" sizes="(max-width: 992px) 100vw, (max-width: 1400px) 75vw, 1000px" @endif width="1600" height="533" alt="" fetchpriority="high" decoding="async">
                </picture>
              </div>
              <div class="hero-slide-overlay"></div>
              <div class="hero-content">
                <div class="hero-text">
                  <h1 class="hero-headline">{{ $slide['headline'] }}</h1>
                  <p class="hero-subtext">{{ $slide['subtext'] }}</p>
                  <div class="hero-ctas"><span class="btn btn-white">{{ $slide['cta'] }}</span></div>
                </div>
              </div>
            </a>
          @else
            <h1 class="sr-only">{{ config('storefront.brand') }} — {{ config('storefront.tagline') }}</h1>
          @endif
        </div>

        
        <div class="launch-offer-strip" id="launch-offer-strip" aria-label="Limited Launch Promotion">
          <div class="launch-offer-inner fade-up visible">
            <div class="launch-offer-lead">
              <div class="launch-offer-badge">INAUGURAL OFFER · 25% OFF</div>
              <h3 class="launch-offer-title">Celebrate Our Launch</h3>
              <p class="launch-offer-desc">Enjoy 25% off selected introductory ranges. Applied automatically at checkout.</p>
            </div>

            <div class="launch-offer-countdown-wrap" id="launch-offer-timer" role="timer" aria-live="polite">
              
            </div>

            <div class="launch-offer-action">
              <a href="#products-section" class="btn btn-primary launch-cta-btn">Shop Curated Range &rarr;</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  
  <section class="section products-section" id="products-section">
    <div class="container">
      <div class="section-header fade-up">
        <div class="section-header-top">
          <div class="section-header-title">
            <h2>Popular Right Now</h2>
          </div>
          <a href="/products" class="section-see-more">Show All &rarr;</a>
        </div>
        <p class="section-header-desc">A selection of pieces we're excited to bring to Australia.</p>
      </div>

      <div class="product-grid product-grid-5" id="product-grid" data-popular-ids="{{ implode(',', array_column($popular ?? [], 'id')) }}" @if (count($boot['products'])) data-reserve style="--prows-md: {{ (int) ceil(min(count($boot['products']), 5) / 3) }}" @endif>
        {{-- Server pick of "Popular Right Now"; renderProducts() keeps the same pick for the session. --}}
        @foreach ($popular ?? [] as $p)
          @include('partials.product-card', ['product' => $p])
        @endforeach
      </div>

    </div>
  </section>

  
  <section class="section categories-section" id="categories-section">
    <div class="container">
      <div class="section-header fade-up">
        <div class="section-header-top">
          <div class="section-header-title">
            
            <h2>Shop by Category</h2>
          </div>
          <a href="/products" class="section-see-more">View All Products &rarr;</a>
        </div>
        <p class="section-header-desc">Explore fashion, home & living, natural jute, handcrafted pieces and thoughtful gifts.</p>
      </div>
      <div class="category-grid" id="category-grid" @if (count($boot['categories'])) data-reserve style="--rows-lg: {{ (int) ceil(count($boot['categories']) / 5) }}; --rows-md: {{ (int) ceil(count($boot['categories']) / 2) }}" @endif>
        {{-- Same markup as renderCategories() in app.js. --}}
        @foreach ($boot['categories'] ?? [] as $c)
          @php($catBg = \App\Support\Img::url($c['image'], 640))
          <a href="{{ \App\Support\Seo::listingUrl($c['slug']) }}" class="category-card fade-up visible">
            <div class="category-card-bg" style="@if (is_string($catBg) && preg_match('#^https?://[^\s\'"()\\\\<>]+$#i', $catBg))background-image: url('{{ $catBg }}'); @endif background-color: #7A5C43"></div>
            <div class="category-card-overlay"></div>
            <div class="category-card-content">
              <h3 class="category-card-title">{{ $c['name'] }} <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg></h3>
            </div>
          </a>
        @endforeach
      </div>
    </div>
  </section>

  
  <div id="category-product-sections">
    
  </div>

  <section class="section request-gifting-section" id="request-gifting-section" style="padding: 4rem 0; background: var(--color-surface-card); border-top: 1px solid var(--color-grey-200); border-bottom: 1px solid var(--color-grey-200);">
    <div class="container">
      <div class="dual-cards-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)) !important;">
        
        
        <div class="shop-your-way-card fade-up visible" id="request-section" style="text-align: center; display: flex; flex-direction: column; justify-content: space-between; padding: 2.25rem; height: 100%;">
          <div>
            <span class="section-kicker" style="margin-bottom: 0.5rem;">LOOKING FOR SOMETHING UNIQUE?</span>
            <h2 style="font-family: var(--font-heading); font-size: clamp(1.3rem, 2vw, 1.6rem); margin-bottom: 0.75rem; color: var(--color-dark);">Request Custom Sourcing</h2>
            <p style="color: var(--color-grey-700); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem;">
              Have a custom piece or unlisted item in mind? Tell us what you're looking for and we’ll source it.
            </p>
          </div>
          <div>
            <a href="/request" class="btn btn-primary btn-lg" style="width: 100%;">Make a Request</a>
          </div>
        </div>

        
        <div class="shop-your-way-card fade-up visible" id="bulk-orders-card" style="text-align: center; display: flex; flex-direction: column; justify-content: space-between; padding: 2.25rem; height: 100%;">
          <div>
            <span class="section-kicker" style="margin-bottom: 0.5rem;">VOLUME & EVENTS</span>
            <h2 style="font-family: var(--font-heading); font-size: clamp(1.3rem, 2vw, 1.6rem); margin-bottom: 0.75rem; color: var(--color-dark);">Bulk & Corporate Orders</h2>
            <p style="color: var(--color-grey-700); font-size: 0.95rem; line-height: 1.6; margin-bottom: 1.5rem;">
              Planning a wedding, corporate gift set, or retail order? Request special volume pricing & custom variants.
            </p>
          </div>
          <div>
            <a href="/bulk-order" class="btn btn-primary btn-lg" style="width: 100%;">Request Bulk Order</a>
          </div>
        </div>

        
        

      </div>
    </div>
  </section>

  
  <section class="section newsletter-section" id="newsletter-section">
    <div class="container">
      <div class="newsletter-card">
        <div class="newsletter-content">
          <span class="section-kicker">STAY IN THE LOOP</span>
          <h2>Stay in the Loop</h2>
          <p>Be the first to know about new arrivals, special offers and upcoming collections.</p>
          <form class="newsletter-form" id="newsletter-form">
            <input type="email" class="newsletter-input" placeholder="Enter your email address" aria-label="Email address" required>
            <button type="submit" class="btn btn-primary">Join the List</button>
          </form>
        </div>
      </div>
    </div>
  </section>
  </main>
@endsection
