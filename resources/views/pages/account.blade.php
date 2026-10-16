@extends('layouts.store')
@section('title', "My Account \u2014 Bongo Curated Australia")
@section('description', "")
@push('head')
@endpush
@section('content')
<div class="announcement-bar" id="announcement-bar">
    <div class="container announcement-bar-inner">
      <span class="announcement-text">✈️ Express Australian Delivery · Seamless Door-to-Door Shipping</span>
    </div>
  </div>

  
  

  
  
  

  
  <section class="account-page-header">
    <div class="container">
      <div class="breadcrumb" style="margin-bottom: var(--space-xs);">
        <a href="/">Home</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">My Account</span>
      </div>

      <div class="account-header-content" id="account-header-card">
        
      </div>
    </div>
  </section>

  
  <main class="container">
    <div id="account-unauth-view" style="display:none; max-width:440px; margin:var(--space-xl) auto;">
      <div class="account-card-panel" style="text-align:center;">
        <h3 class="auth-title" style="margin-bottom:8px">Access Your Account</h3>
        <p class="auth-subtitle" style="margin-bottom:var(--space-md)">Sign in or create an account to view your Australian orders, saved items, and custom sourcing requests.</p>
        <div style="display:flex; flex-direction:column; gap:var(--space-xs)">
          <button class="btn btn-primary" onclick="openAuthModal('login')">Sign In</button>
          <button class="btn btn-secondary" onclick="openAuthModal('register')">Create Account</button>
        </div>
      </div>
    </div>

    <div class="account-layout" id="account-auth-view" style="display:none;">
      
      <aside class="account-nav-card">
        <nav class="account-tab-list" role="tablist">
          <button class="account-tab-item active" data-account-tab="profile">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            Personal Profile
          </button>
          <button class="account-tab-item" data-account-tab="orders">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.75 7.5h16.5m-16.5 0l1.125-3.375A2.25 2.25 0 0110.5 2.625h3a2.25 2.25 0 012.125 1.5L16.5 7.5"/></svg>
            My Orders
          </button>
          <button class="account-tab-item" data-account-tab="wishlist">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>
            Saved Wishlist
          </button>
          <button class="account-tab-item" data-account-tab="cart">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
            Shopping Bag
          </button>
          <button class="account-tab-item" data-account-tab="requests">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m3.75 9v6m3-3H9m1.5-12H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            Sourcing Requests
          </button>
          <button class="account-tab-item" id="account-sidebar-logout" style="color:var(--color-danger)">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
            Log Out
          </button>
        </nav>
      </aside>

      
      <section class="account-main-content">
        
        <div class="account-tab-content-panel active" id="tab-panel-profile">
          <div class="account-card-panel">
            <div class="account-panel-header">
              <h3 class="account-panel-title">Personal Profile Details</h3>
              <span class="auth-subtitle">Update your profile & Australian delivery address</span>
            </div>
            <form id="profile-edit-form">
              <div class="form-row-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md);">
                <div class="auth-form-group">
                  <label class="auth-label" for="profile-name">Full Name</label>
                  <input type="text" id="profile-name" class="auth-input" required>
                </div>
                <div class="auth-form-group">
                  <label class="auth-label" for="profile-email">Email Address</label>
                  <input type="email" id="profile-email" class="auth-input" disabled style="background:var(--color-surface-alt)">
                </div>
              </div>

              <div class="form-row-2col" style="display:grid; grid-template-columns:1fr 1fr; gap:var(--space-md); margin-top:var(--space-sm);">
                <div class="auth-form-group">
                  <label class="auth-label" for="profile-phone">Australian Phone Number</label>
                  <input type="tel" id="profile-phone" class="auth-input" placeholder="e.g. +61 412 345 678">
                </div>
                <div class="auth-form-group">
                  <label class="auth-label" for="profile-postcode">Australian Delivery Postcode</label>
                  <input type="text" id="profile-postcode" class="auth-input" placeholder="e.g. 3000">
                </div>
              </div>

              <div class="auth-form-group" style="margin-top:var(--space-sm);">
                <label class="auth-label" for="profile-address">Shipping Address</label>
                <textarea id="profile-address" class="auth-input" rows="3" placeholder="Street address, suburb, state, postcode"></textarea>
              </div>

              <div style="margin-top:var(--space-md); text-align:right;">
                <button type="submit" class="btn btn-primary" id="profile-save-btn">Save Changes</button>
              </div>
            </form>
          </div>
        </div>

        
        <div class="account-tab-content-panel" id="tab-panel-orders">
          <div class="account-card-panel">
            <div class="account-panel-header">
              <h3 class="account-panel-title">My Orders</h3>
              <span class="auth-subtitle">Track current shipments and view order history</span>
            </div>
            <div id="account-orders-list">
              
            </div>
          </div>
        </div>

        
        <div class="account-tab-content-panel" id="tab-panel-wishlist">
          <div class="account-card-panel">
            <div class="account-panel-header">
              <h3 class="account-panel-title">Saved Wishlist Items</h3>
              <button class="btn btn-secondary btn-sm" onclick="toggleWishlistDrawer()">Open Wishlist Drawer</button>
            </div>
            <div id="account-wishlist-grid">
              
            </div>
          </div>
        </div>

        
        <div class="account-tab-content-panel" id="tab-panel-cart">
          <div class="account-card-panel">
            <div class="account-panel-header">
              <h3 class="account-panel-title">Shopping Bag Items</h3>
              <button class="btn btn-secondary btn-sm" onclick="toggleCart()">Open Cart Drawer</button>
            </div>
            <div id="account-cart-grid">
              
            </div>
          </div>
        </div>

        
        <div class="account-tab-content-panel" id="tab-panel-requests">
          <div class="account-card-panel">
            <div class="account-panel-header">
              <h3 class="account-panel-title">Sourcing & Bulk Order Requests</h3>
              <div style="display:flex; gap:0.5rem;">
                <a href="/request" class="btn btn-outline btn-sm">+ Custom Request</a>
                <a href="/bulk-order" class="btn btn-primary btn-sm">+ Bulk Order</a>
              </div>
            </div>
            <div id="account-requests-list">
              
            </div>
          </div>
        </div>
      </section>
    </div>
  </main>
@endsection
@push('scripts')
<script src="{{ asset('assets/js/pages/account.js') }}"></script>
@endpush
