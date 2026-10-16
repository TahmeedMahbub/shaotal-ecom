@extends('layouts.store')
@section('title', "All Products \u2014 Bongo Curated | Artisan Crafted, Curated for Australia")
@section('description', "Discover handcrafted fashion, home decor, natural jute products, artisan pieces and gifts curated for Australia.")
@section('content')
<main class="main-content">
    <section class="listing-page-section">
      <div class="container">

        
        <div class="breadcrumb">
          <a href="/">Home</a>
          <span class="breadcrumb-sep">/</span>
          <a href="/products">Shop</a>
          <span class="breadcrumb-sep">/</span>
          <span class="breadcrumb-current" id="breadcrumb-current">All Products</span>
        </div>

        
        <header class="listing-header">
          <div class="listing-header-top">
            <div class="listing-title-wrap">
              <h1 class="listing-title" id="listing-title">All Products</h1>
              
            </div>

            <div class="listing-controls-right">
              <div class="listing-count" id="listing-count">Showing products</div>
              
              <div class="listing-sort-wrap">
                <label for="sort-select" class="listing-sort-label">Sort By:</label>
                <select id="sort-select" class="sort-select">
                  <option value="featured">Featured</option>
                  <option value="newest">Newest First</option>
                  <option value="price-low">Price: Low to High</option>
                  <option value="price-high">Price: High to Low</option>
                </select>
              </div>
            </div>
          </div>
        </header>

        
        <div class="mobile-filter-bar">
          <button class="btn-mobile-filter" id="btn-open-mobile-filter">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0m-9.75 0h9.75"/></svg>
            Filter <span class="filter-badge-count" id="filter-badge-count" style="display:none">0</span>
          </button>
          
          <select id="mobile-sort-select" class="sort-select" style="flex:1">
            <option value="featured">Sort: Featured</option>
            <option value="newest">Sort: Newest</option>
            <option value="price-low">Sort: Price Low–High</option>
            <option value="price-high">Sort: Price High–Low</option>
          </select>
        </div>

        
        <div class="listing-layout">

          
          <aside class="listing-sidebar">
            <div class="sidebar-header">
              <span class="sidebar-title">Filters</span>
              <button class="clear-all-link clear-all-filters-btn">Clear All</button>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Category</div>
              <div class="filter-options-list" data-dyn-options="category" data-radio-name="sidebar-category"></div>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Brand / Maker</div>
              <div class="filter-options-list" data-dyn-options="brand"></div>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Price Range</div>
              <div class="filter-options-list">
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="under-30" data-filter-price>
                  <span>Under A$30</span>
                </label>
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="30-40" data-filter-price>
                  <span>A$30 – A$40</span>
                </label>
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="40-50" data-filter-price>
                  <span>A$40 – A$50</span>
                </label>
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="50-above" data-filter-price>
                  <span>A$50 & Above</span>
                </label>
              </div>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Size</div>
              <div class="filter-size-grid" data-dyn-options="size"></div>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Color</div>
              <div class="filter-color-grid" data-dyn-options="color"></div>
            </div>

            
            <div class="filter-group">
              <div class="filter-group-title">Availability</div>
              <div class="filter-options-list">
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="instock" data-filter-availability>
                  <span>In Stock</span>
                </label>
                <label class="filter-checkbox-label">
                  <input type="checkbox" value="preorder" data-filter-availability>
                  <span>Pre-order</span>
                </label>
              </div>
            </div>

            <button class="btn btn-outline clear-all-filters-btn" style="width:100%; margin-top: 0.5rem">Clear All Filters</button>
          </aside>

          
          <div class="listing-content">
            
            <div class="listing-grid" id="listing-products-grid">
              
            </div>

            
            <div class="infinite-scroll-sentinel" id="infinite-scroll-sentinel">
              <div class="loading-spinner"></div>
            </div>

            
            <div class="listing-empty-state" id="listing-empty-state" style="display:none">
              <h3>No products found</h3>
              <p>Try adjusting your filter criteria or clearing selected filters to see more results.</p>
              <button class="btn btn-primary clear-all-filters-btn">Clear All Filters</button>
            </div>
          </div>
        </div>

      </div>
    </section>
  </main>

  
  <div class="filter-drawer-overlay" id="filter-drawer-overlay"></div>
  <div class="filter-drawer" id="filter-drawer">
    <div class="filter-drawer-header">
      <h3>Filters</h3>
      <button class="filter-drawer-close" id="filter-drawer-close" aria-label="Close filters">&times;</button>
    </div>
    <div class="filter-drawer-body">
      <div class="filter-group">
        <div class="filter-group-title">Category</div>
        <div class="filter-options-list" data-dyn-options="category" data-radio-name="mobile-category"></div>
      </div>

      <div class="filter-group">
        <div class="filter-group-title">Brand / Maker</div>
        <div class="filter-options-list" data-dyn-options="brand"></div>
      </div>

      <div class="filter-group">
        <div class="filter-group-title">Price Range</div>
        <div class="filter-options-list">
          <label class="filter-checkbox-label"><input type="checkbox" value="under-30" data-filter-price><span>Under A$30</span></label>
          <label class="filter-checkbox-label"><input type="checkbox" value="30-40" data-filter-price><span>A$30 – A$40</span></label>
          <label class="filter-checkbox-label"><input type="checkbox" value="40-50" data-filter-price><span>A$40 – A$50</span></label>
          <label class="filter-checkbox-label"><input type="checkbox" value="50-above" data-filter-price><span>A$50 & Above</span></label>
        </div>
      </div>

      <div class="filter-group">
        <div class="filter-group-title">Availability</div>
        <div class="filter-options-list">
          <label class="filter-checkbox-label"><input type="checkbox" value="instock" data-filter-availability><span>In Stock</span></label>
          <label class="filter-checkbox-label"><input type="checkbox" value="preorder" data-filter-availability><span>Pre-order</span></label>
        </div>
      </div>
    </div>

    <div class="filter-drawer-footer">
      <button class="btn btn-outline clear-all-filters-btn" style="flex:1">Clear All</button>
      <button class="btn btn-primary" id="btn-apply-mobile-filter" style="flex:1">Apply Filters</button>
    </div>
  </div>
@endsection
@push('scripts')
<script src="{{ \App\Support\Assets::url('assets/js/products.js') }}"></script>
@endpush
