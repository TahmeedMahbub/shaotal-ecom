@extends('layouts.store')
@section('title', "Bongo Curated \u2014 Artisan Crafted. Curated for Australia.")
@section('description', "Discover thoughtfully selected fashion, home decor, natural jute, handcrafted pieces and gifts curated for Australian living.")
@section('content')
<main class="main-content">

  
  <section class="hero-section" id="hero-section">
    <div class="container hero-container">
      <div class="hero-desktop-grid">
        
        <div class="hero" id="hero" role="region" aria-label="Featured Collection Highlights" aria-roledescription="carousel">
          
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
          <a href=".//products" class="section-see-more">Show All &rarr;</a>
        </div>
        <p class="section-header-desc">A selection of pieces we're excited to bring to Australia.</p>
      </div>

      <div class="product-grid product-grid-5" id="product-grid">
        
      </div>

      <div class="popular-section-footer fade-up">
        <a href=".//products" class="popular-explore-more-link" id="popular-explore-more-link">
          Explore More Products &rarr;
        </a>
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
          <a href=".//products" class="section-see-more">View All Products &rarr;</a>
        </div>
        <p class="section-header-desc">Explore fashion, home & living, natural jute, handcrafted pieces and thoughtful gifts.</p>
      </div>
      <div class="category-grid" id="category-grid">
        
      </div>
    </div>
  </section>

  
  <div id="category-product-sections">
    
  </div>

  
  <section class="section home-living-section" id="home-living-section">
    <div class="container">
      <div class="editorial-banner fade-up">
        <div class="editorial-content">
          <span class="section-kicker kicker-light">CURATED INTERIORS</span>
          <h2>Home & Living</h2>
          <p>Bring warmth and character to your space with thoughtfully handcrafted pieces.</p>
          <a href="#products-section" class="btn btn-white" data-filter-trigger="home-decor">Shop Home & Living</a>
        </div>
        <div class="editorial-image">
          @if (!empty($boot['homeImages']['living']))<img src="{{ $boot['homeImages']['living'] }}" alt="Australian living room with handcrafted artisan decor" loading="lazy">@endif
        </div>
      </div>

      <div class="home-showcase-grid" id="home-showcase-grid" style="margin-top: 2rem;">
        
      </div>
    </div>
  </section>

  
  <section class="section jute-section" id="jute-section">
    <div class="container">
      <div class="jute-wrapper">
        <div class="jute-image-col fade-up">
          <div class="jute-hero-card">
            @if (!empty($boot['homeImages']['jute']))<img src="{{ $boot['homeImages']['jute'] }}" alt="Handcrafted Golden Jute Totes and Baskets" loading="lazy">@endif
          </div>
        </div>
        <div class="jute-content-col fade-up">
          <span class="section-kicker">NATURAL MATERIALS</span>
          <h2>Jute & Natural</h2>
          <p class="lead-text">
            Discover versatile jute pieces crafted from natural golden fiber.
          </p>
          <a href="#products-section" class="btn btn-primary" data-filter-trigger="jute">Shop Jute</a>
        </div>
      </div>
    </div>
  </section>

  
  <section class="section bangladesh-made" id="bangladesh-made-section">
    <div class="container">
      <div class="bangladesh-made-wrapper" style="grid-template-columns: 1.2fr 1fr; align-items: center;">
        <div class="bangladesh-made-content fade-up">
          <span class="section-kicker">OUR STORY</span>
          <h2>Thoughtfully Crafted. Selected for Australia.</h2>
          <p class="lead-text" style="font-size: 1rem; font-weight: 400; color: var(--color-grey-700); margin-bottom: 1.5rem;">
            We discover distinctive South Asian-inspired pieces and curate them for modern Australian lifestyles — from everyday fashion to handcrafted home accents and thoughtful gifts.
          </p>
          <a href="#products-section" class="btn btn-primary">Our Story</a>
        </div>

        <div class="bangladesh-made-visual fade-up">
          <div class="visual-card-stacked">
            @if (!empty($boot['homeImages']['story']))<img src="{{ $boot['homeImages']['story'] }}" alt="Artisan craft story" loading="lazy" class="main-visual">@endif
          </div>
        </div>
      </div>
    </div>
  </section>

  
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
