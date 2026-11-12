<nav class="navbar" id="navbar">
    <div class="container navbar-inner">
      
      <button class="hamburger" id="hamburger" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>

      
      <a href="/" class="navbar-brand" aria-label="Shaotal">
        <img src="/assets/logos/logo.png" alt="Shaotal" class="navbar-logo">
      </a>

      
      <ul class="navbar-nav" id="nav-items">
        {{-- Same items and markup as NAV_CATEGORIES (data.js) + renderNavbar() (app.js), which re-renders it on load. --}}
        @php
          $navChevron = '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="chevron-down"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>';
          $navItems = [['New Arrivals', '/products?sort=newest', []]];
          foreach ($boot['categories'] ?? [] as $navCat) {
              $cols = [];
              foreach ($boot['subcategories'] ?? [] as $navSub) {
                  if ($navSub['category'] === $navCat['slug']) {
                      $cols[$navSub['section'] ?: $navCat['name']][] = [$navSub['name'], \App\Support\Seo::listingUrl($navCat['slug'], $navSub['slug'])];
                  }
              }
              $navItems[] = [$navCat['name'], \App\Support\Seo::listingUrl($navCat['slug']), $cols];
          }
          $navItems[] = ['Bulk Orders', '/bulk-order', []];
        @endphp
        @foreach ($navItems as [$navName, $navHref, $navCols])
          <li class="nav-item">
            <a href="{{ $navHref }}" class="nav-link">{{ $navName }}@if ($navCols){!! $navChevron !!}@endif</a>
            @if ($navCols)
              <div class="mega-menu">
                @foreach ($navCols as $navHeading => $navLinks)
                  <div class="mega-menu-col">
                    <h4>{{ $navHeading }}</h4>
                    <ul>@foreach ($navLinks as [$navLabel, $navLink])<li><a href="{{ $navLink }}">{{ $navLabel }}</a></li>@endforeach</ul>
                  </div>
                @endforeach
              </div>
            @endif
          </li>
        @endforeach
      </ul>

      
      <div class="navbar-actions" id="navbar-actions">
        
      </div>
    </div>
  </nav>
<div class="mobile-nav-overlay" id="mobile-nav-overlay"></div>
<div class="mobile-nav" id="mobile-nav">
    <div class="mobile-nav-header">
      <a href="/" class="mobile-nav-brand" aria-label="Shaotal">
        <img src="/assets/logos/logo.png" alt="Shaotal" class="navbar-logo">
      </a>
      <button class="mobile-nav-close" id="mobile-nav-close" aria-label="Close menu">&times;</button>
    </div>
    <div id="mobile-nav-items">
      
    </div>
  </div>
<div class="search-overlay" id="search-overlay">
    <div class="search-modal">
      <div class="search-modal-header">
        <div class="search-input-wrap">
          <svg class="search-input-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
          <input type="text" class="search-modal-input" id="search-input" placeholder="Search fashion, home decor, jute, handicrafts..." aria-label="Search catalog" autocomplete="off">
          <button class="search-clear-btn" id="search-clear-btn" aria-label="Clear search" style="display:none">&times;</button>
        </div>
        <button class="search-modal-close" id="search-modal-close" aria-label="Close search">&times;</button>
      </div>

      <div class="search-modal-body" id="search-modal-body">
        
        <div class="search-default-state" id="search-default-state">
          
          <div class="search-section" id="recent-searches-section">
            <div class="search-section-header">
              <span class="search-section-title">Recent Searches</span>
              <button class="search-clear-all-recent" id="clear-recent-searches-btn">Clear All</button>
            </div>
            <div class="search-tags-list" id="recent-searches-list">
              
            </div>
          </div>

          
          <div class="search-section">
            <span class="search-section-title">Suggested Products</span>
            <div class="search-suggested-list" id="search-suggested-list">
              
            </div>
          </div>
        </div>

        
        <div class="search-results-state" id="search-results-state" style="display:none">
          <div class="search-results-count" id="search-results-count"></div>
          <div class="search-results-list" id="search-results-list">
            
          </div>
        </div>

        
        <div class="search-no-results-state" id="search-no-results-state" style="display:none">
          <div class="no-results-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
          </div>
          <h3>No products found</h3>
          <p id="no-results-text">We couldn't find any items matching your search.</p>
          <a href="/products" class="btn btn-primary btn-sm" id="search-browse-all-btn">Browse All Products</a>
        </div>
      </div>
    </div>
  </div>
