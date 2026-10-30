/* ============================================================
   APP.JS — Shaotal Main Application
   Artisan Lifestyle & Fashion Curated for Australia
   ============================================================ */

(function () {
  'use strict';

  /* ── Server session, cart & wishlist ── */
  const BOOT = window.__BOOT__ || {};
  let CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  window.PLACEHOLDER_IMG = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500" viewBox="0 0 400 500"><rect width="400" height="500" fill="#efe9e0"/></svg>');

  async function api(method, url, body) {
    try {
      const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
      let data = null;
      try { data = await res.json(); } catch (e) { /* non-JSON response */ }
      return { ok: res.ok, status: res.status, data: data || {} };
    } catch (e) {
      return { ok: false, status: 0, data: { message: 'Network error. Please try again.' } };
    }
  }
  window.bongoApi = api;

  function apiMessage(res) {
    const d = res.data || {};
    if (d.errors) {
      const first = Object.values(d.errors)[0];
      return Array.isArray(first) ? first[0] : String(first);
    }
    return d.message || 'Something went wrong. Please try again.';
  }
  window.bongoApiMessage = apiMessage;

  function readList(key) {
    try {
      const parsed = JSON.parse(localStorage.getItem(key));
      if (Array.isArray(parsed)) return parsed;
    } catch (e) { /* ignore */ }
    return [];
  }
  function getStoredCart() { return readList('bongo_cart'); }
  function getStoredWishlist() { return readList('bongo_wishlist'); }

  const syncTimers = {};
  function syncToServer(key, url, body) {
    clearTimeout(syncTimers[key]);
    syncTimers[key] = setTimeout(() => api('PUT', url, body), 400);
  }

  function variantPrice(product, color, size) {
    if (!product.hasVariants) return product.price;
    const v = (product.variants || []).find(x => (x.color || null) === (color || null) && (x.size || null) === (size || null));
    return v ? v.price : product.price;
  }
  window.bongoVariantPrice = variantPrice;

  // Refresh cart lines from the live catalog (price, name, image) and drop products that are gone or sold out.
  function reconcileCart(cart) {
    const out = [];
    (cart || []).forEach(item => {
      const p = PRODUCTS.find(x => x.id === (item.productId || item.id));
      if (!p || p.soldOut) return;
      const price = variantPrice(p, item.color, item.size);
      out.push(Object.assign({}, item, {
        productId: p.id, name: p.name, price,
        originalPrice: (p.originalPrice && price === p.price) ? p.originalPrice : price,
        image: p.image, badge: p.badge, availability: p.availability, isPreorder: p.availability === 'preorder',
      }));
    });
    return out;
  }

  function saveCart() {
    try { localStorage.setItem('bongo_cart', JSON.stringify(state.cart)); } catch (e) { /* storage unavailable */ }
    if (state.currentUser) {
      syncToServer('cart', '/account/cart', { items: state.cart.map(i => ({ productId: i.productId, color: i.color || null, size: i.size || null, quantity: i.quantity || 1 })) });
    }
    if (typeof renderWishlistDrawer === 'function') renderWishlistDrawer();
  }

  function wishlistIds() {
    return state.wishlist.map(i => (typeof i === 'object' ? (i.productId || i.id) : i));
  }

  function saveWishlist() {
    try { localStorage.setItem('bongo_wishlist', JSON.stringify(state.wishlist)); } catch (e) { /* storage unavailable */ }
    if (state.currentUser) syncToServer('wishlist', '/account/wishlist', { ids: wishlistIds() });
  }

  function getStoredCurrentUser() { return state.currentUser; }
  function saveCurrentUser(user) { state.currentUser = user; }
  function updateUserInUsersList() { /* profile is persisted server-side */ }

  /* ── State ── */
  const initialUser = BOOT.user || null;
  const state = {
    currentUser: initialUser,
    cart: reconcileCart(initialUser ? BOOT.cart : getStoredCart()),
    wishlist: initialUser ? (BOOT.wishlist || []) : getStoredWishlist(),
    currentSlide: 0,
    heroInterval: null,
    selectedModalProduct: null,
    selectedSize: null,
    currentCategoryFilter: 'all',
  };
  if (initialUser) {
    try {
      localStorage.setItem('bongo_cart', JSON.stringify(state.cart));
      localStorage.setItem('bongo_wishlist', JSON.stringify(state.wishlist));
    } catch (e) { /* storage unavailable */ }
  }

  /* ══════════════════════════════════════════════════════════
     ICON HELPER — inline SVG icons
     ══════════════════════════════════════════════════════════ */
  const ICONS = {
    search: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>`,
    user: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>`,
    heart: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>`,
    heartFill: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>`,
    bag: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>`,
    chevronDown: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="chevron-down"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>`,
    arrowRight: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>`,
    home: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>`,
    grid: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>`,
    check: `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>`,
    facebook: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`,
    instagram: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>`,
    tiktok: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>`,
  };

  const BADGE_MAP = {
    new: '<span class="badge badge-new">New</span>',
    launch: '<span class="badge badge-sale">Launch Offer</span>',
    preorder: '<span class="badge badge-preorder">Pre-order</span>',
    lowstock: '<span class="badge badge-limited">Low Stock</span>',
  };


  /* ══════════════════════════════════════════════════════════
     1. HERO SLIDER (3:1 Editorial Ken Burns Carousel)
     ══════════════════════════════════════════════════════════ */
  let heroState = {
    currentIndex: 0,
    timer: null,
    isPaused: false
  };

  function renderHero() {
    const heroEl = document.getElementById('hero');
    if (!heroEl) return;

    if (!Array.isArray(HERO_SLIDES) || HERO_SLIDES.length === 0) return;

    let slidesHTML = HERO_SLIDES.map((slide, i) => `
      <a href="${slide.ctaLink}" class="hero-slide ${i === 0 ? 'active' : ''}" data-slide="${i}" role="group" aria-roledescription="slide" aria-label="Slide ${i + 1} of ${HERO_SLIDES.length}: ${slide.headline}" aria-hidden="${i === 0 ? 'false' : 'true'}">
        <div class="hero-slide-bg" ${i === 0 ? `style="background-image: url('${slide.imageSrc || slide.image || ''}')"` : `data-bg="${slide.imageSrc || slide.image || ''}"`}></div>
        <div class="hero-slide-overlay"></div>
        <div class="hero-content">
          <div class="hero-text">
            ${slide.label ? `<span class="hero-label">${slide.label}</span>` : ''}
            <h1 class="hero-headline">${slide.headline}</h1>
            <p class="hero-subtext">${slide.subtext}</p>
            <div class="hero-ctas">
              <span class="btn btn-white">${slide.cta}</span>
              ${slide.secondaryCta ? `<span class="btn btn-outline-white hero-secondary-btn" data-secondary-link="${slide.secondaryCtaLink}">${slide.secondaryCta}</span>` : ''}
            </div>
          </div>
        </div>
      </a>
    `).join('');

    let indicatorsHTML = `
      <div class="hero-nav-indicators" role="tablist" aria-label="Carousel slide controls">
        ${HERO_SLIDES.map((_, i) => `
          <button class="hero-nav-pill ${i === 0 ? 'active' : ''}" data-slide-index="${i}" role="tab" aria-selected="${i === 0 ? 'true' : 'false'}" aria-label="Go to slide ${i + 1}">
          </button>
        `).join('')}
      </div>
    `;

    let arrowsHTML = `
      <button class="hero-arrow hero-arrow-prev" aria-label="Previous slide" data-direction="prev">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
      </button>
      <button class="hero-arrow hero-arrow-next" aria-label="Next slide" data-direction="next">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
      </button>
    `;

    heroEl.innerHTML = slidesHTML + indicatorsHTML + arrowsHTML;

    // Load the remaining slide backgrounds once the page (and the first, LCP image) has finished loading.
    const loadHeroBackgrounds = () => heroEl.querySelectorAll('.hero-slide-bg[data-bg]').forEach(el => {
      el.style.backgroundImage = `url('${el.dataset.bg}')`;
      el.removeAttribute('data-bg');
    });
    if (document.readyState === 'complete') setTimeout(loadHeroBackgrounds, 300);
    else window.addEventListener('load', () => setTimeout(loadHeroBackgrounds, 300), { once: true });

    bindHeroEvents(heroEl);
    startHeroAutoRotation();
  }

  function goToSlide(index) {
    const heroEl = document.getElementById('hero');
    if (!heroEl) return;

    const slides = heroEl.querySelectorAll('.hero-slide');
    const pills = heroEl.querySelectorAll('.hero-nav-pill');
    if (!slides.length) return;

    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;

    heroState.currentIndex = index;

    slides.forEach((slide, i) => {
      const isActive = i === index;
      if (isActive) {
        const bg = slide.querySelector('.hero-slide-bg');
        if (bg && !slide.classList.contains('active')) {
          bg.style.animation = 'none';
          void bg.offsetWidth; // Force reflow to cleanly restart Ken Burns animation
          bg.style.animation = '';
        }
        slide.classList.add('active');
        slide.setAttribute('aria-hidden', 'false');
      } else {
        slide.classList.remove('active');
        slide.setAttribute('aria-hidden', 'true');
      }
    });

    pills.forEach((pill, i) => {
      const isActive = i === index;
      pill.classList.toggle('active', isActive);
      pill.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });
  }

  function nextSlide() {
    goToSlide(heroState.currentIndex + 1);
  }

  function prevSlide() {
    goToSlide(heroState.currentIndex - 1);
  }

  function startHeroAutoRotation() {
    stopHeroAutoRotation();
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      return;
    }
    heroState.timer = setInterval(() => {
      if (!heroState.isPaused) {
        nextSlide();
      }
    }, 5500); // 5.5s rotation
  }

  function stopHeroAutoRotation() {
    if (heroState.timer) {
      clearInterval(heroState.timer);
      heroState.timer = null;
    }
  }

  function bindHeroEvents(heroEl) {
    // Secondary CTA clicks: prevent parent slide click, navigate to secondary link
    heroEl.querySelectorAll('.hero-secondary-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const secLink = btn.dataset.secondaryLink;
        if (secLink) window.location.href = secLink;
      });
    });

    // Indicator pills
    heroEl.querySelectorAll('.hero-nav-pill').forEach(pill => {
      pill.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const idx = parseInt(pill.dataset.slideIndex, 10);
        goToSlide(idx);
        startHeroAutoRotation();
      });
    });

    // Arrow navigation
    const prevBtn = heroEl.querySelector('.hero-arrow-prev');
    const nextBtn = heroEl.querySelector('.hero-arrow-next');
    if (prevBtn) {
      prevBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        prevSlide();
        startHeroAutoRotation();
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        nextSlide();
        startHeroAutoRotation();
      });
    }

    // Pause auto-rotation on mouse enter / keyboard focus
    heroEl.addEventListener('mouseenter', () => {
      heroState.isPaused = true;
    });
    heroEl.addEventListener('mouseleave', () => {
      heroState.isPaused = false;
    });
    heroEl.addEventListener('focusin', () => {
      heroState.isPaused = true;
    });
    heroEl.addEventListener('focusout', () => {
      heroState.isPaused = false;
    });

    // Mobile swipe handling (distinguish swipe from tap)
    let startX = 0;
    let isSwiping = false;

    heroEl.addEventListener('touchstart', (e) => {
      startX = e.changedTouches[0].screenX;
      isSwiping = false;
      heroState.isPaused = true;
    }, { passive: true });

    heroEl.addEventListener('touchmove', (e) => {
      const diffX = Math.abs(e.changedTouches[0].screenX - startX);
      if (diffX > 10) {
        isSwiping = true;
      }
    }, { passive: true });

    heroEl.addEventListener('touchend', (e) => {
      const endX = e.changedTouches[0].screenX;
      heroState.isPaused = false;
      const diff = endX - startX;
      if (Math.abs(diff) > 35) {
        if (diff < 0) nextSlide();
        else prevSlide();
        startHeroAutoRotation();
      }
    }, { passive: true });

    // Prevent navigation if a swipe gesture was performed
    heroEl.addEventListener('click', (e) => {
      if (isSwiping) {
        e.preventDefault();
        isSwiping = false;
      }
    });
  }

  /* ══════════════════════════════════════════════════════════
     2. RENDER CATEGORIES
     ══════════════════════════════════════════════════════════ */
  function renderCategories() {
    const grid = document.getElementById('category-grid');
    if (!grid) return;

    grid.innerHTML = CATEGORIES.map(cat => `
      <a href="#products-section" class="category-card fade-up visible" data-filter-trigger="${cat.slug}">
        <div class="category-card-bg" style="background-image: url('${imgUrl(cat.image, 640)}'); background-color: ${cat.gradient.split(',')[0].replace('linear-gradient(135deg', '').trim()}"></div>
        <div class="category-card-overlay"></div>
        <div class="category-card-content">
          <h3 class="category-card-title">${cat.name}</h3>
          <p class="category-card-desc">${cat.desc}</p>
          <span class="category-card-link">Explore ${cat.name} ${ICONS.arrowRight}</span>
        </div>
      </a>
    `).join('');

    grid.querySelectorAll('[data-filter-trigger]').forEach(card => {
      card.addEventListener('click', () => {
        filterProducts(card.dataset.filterTrigger);
      });
    });
  }

  /* ══════════════════════════════════════════════════════════
     3. RENDER PRODUCT CARDS & FILTER TABS
     ══════════════════════════════════════════════════════════ */
  function fixImgPath(src) {
    return src || window.PLACEHOLDER_IMG;
  }

  function createProductCardHTML(product, opts) {
    const eager = !!(opts && opts.eager);
    const numPrice = parseFloat(product.price) || 0;
    const numOrigPrice = product.originalPrice ? parseFloat(product.originalPrice) : null;

    const originalPriceHTML = (numOrigPrice && numOrigPrice > numPrice)
      ? `<span class="original">${SITE.currency}${numOrigPrice.toFixed(2)}</span>`
      : `<span class="original">&nbsp;</span>`;

    const imgPath = fixImgPath(product.image);

    return `
      <div class="product-card fade-up visible" data-product-id="${product.id}">
        <div class="product-card-image">
          <img src="${imgUrl(imgPath, 480)}" ${imgSrcset(imgPath, [320, 480, 640, 800]) ? `srcset="${imgSrcset(imgPath, [320, 480, 640, 800])}" sizes="(max-width: 640px) 50vw, (max-width: 1100px) 40vw, 260px"` : ''} width="400" height="450" alt="${product.name || 'Product'}" ${eager ? 'loading="eager" fetchpriority="high"' : 'loading="lazy"'} decoding="async" onerror="this.onerror=null;this.removeAttribute('srcset');this.src=window.PLACEHOLDER_IMG;">
          <div class="product-card-badge">${product.soldOut ? '<span class="badge badge-limited">Sold Out</span>' : (BADGE_MAP[product.badge] || '')}</div>
          <button class="product-card-wishlist ${state.wishlist.includes(product.id) ? 'active' : ''}" data-wishlist-id="${product.id}" aria-label="Add to wishlist">
            ${state.wishlist.includes(product.id) ? ICONS.heartFill : ICONS.heart}
          </button>
        </div>
        <div class="product-card-info">
          <div class="product-card-category">${CATEGORY_NAME_MAP[product.category] || product.category || 'General'}</div>
          <div class="product-card-name">${product.name || 'Product'}</div>
          <div class="product-card-desc">${product.desc || ''}</div>
          <div class="product-card-footer">
            <div class="product-card-price">
              ${originalPriceHTML}
              <span class="current">${SITE.currency}${numPrice.toFixed(2)}</span>
            </div>
            <button class="product-card-btn" data-view-details-id="${product.id}" aria-label="View details for ${product.name || 'Product'}">
              <span class="btn-text-desktop">View Details</span>
              <span class="btn-text-mobile">View</span>
            </button>
          </div>
        </div>
      </div>
    `;
  }

  function bindProductCardEvents(container) {
    container.querySelectorAll('.product-card-wishlist').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleWishlist(parseInt(btn.dataset.wishlistId));
      });
    });

    container.querySelectorAll('[data-view-details-id]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id = parseInt(btn.dataset.viewDetailsId);
        window.location.href = `${productUrl(id)}`;
      });
    });

    container.querySelectorAll('.product-card').forEach(card => {
      card.addEventListener('click', () => {
        const id = parseInt(card.dataset.productId);
        window.location.href = `${productUrl(id)}`;
      });
    });
  }

  window.createProductCardHTML = createProductCardHTML;
  window.bindProductCardEvents = bindProductCardEvents;

  function getStablePopularProducts(products, count = 5) {
    if (!Array.isArray(products) || products.length === 0) return [];
    const storageKey = 'bongo_popular_product_ids_v5';
    try {
      const cached = sessionStorage.getItem(storageKey);
      if (cached) {
        const ids = JSON.parse(cached);
        if (Array.isArray(ids) && ids.length === Math.min(count, products.length)) {
          const selected = ids.map(id => products.find(p => p.id === id)).filter(Boolean);
          if (selected.length === Math.min(count, products.length)) {
            return selected;
          }
        }
      }
    } catch (e) { }

    // Deterministic/Random shuffle that stays stable for the session
    const copy = [...products];
    for (let i = copy.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    const selected = copy.slice(0, count);
    try {
      sessionStorage.setItem(storageKey, JSON.stringify(selected.map(p => p.id)));
    } catch (e) { }
    return selected;
  }

  function renderProducts() {
    const grid = document.getElementById('product-grid');
    if (!grid) return;

    const sourceProducts = window.BongoProducts ? window.BongoProducts.getAll() : (typeof PRODUCTS !== 'undefined' ? PRODUCTS : []);
    const available = sourceProducts.filter(p => !p.isDisabled);

    if (available.length === 0) {
      grid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: var(--space-2xl); color: var(--color-grey-500);">No products found.</div>`;
      return;
    }

    const displayed = getStablePopularProducts(available, 5);
    grid.innerHTML = displayed.map(p => createProductCardHTML(p)).join('');
    bindProductCardEvents(grid);

    // Update bottom-left Explore XX More Products link
    const exploreLink = document.getElementById('popular-explore-more-link');
    if (exploreLink) {
      const additionalCount = Math.max(0, available.length - displayed.length);
      exploreLink.innerHTML = `Explore ${additionalCount} More Products &rarr;`;
      exploreLink.href = '/products';
    }
  }

  // Category slider interaction state store
  const categorySliderState = {};

  function updateCategorySliderControls(catSlug, totalPages, catName) {
    const wrapper = document.querySelector(`.category-slider-wrapper[data-category="${catSlug}"]`);
    if (!wrapper) return;
    const prevBtn = wrapper.querySelector('.category-nav-arrow-prev');
    const nextBtn = wrapper.querySelector('.category-nav-arrow-next');
    const state = categorySliderState[catSlug] || { step: 0 };

    if (prevBtn) {
      if (state.step <= 0) {
        prevBtn.setAttribute('disabled', 'true');
        prevBtn.setAttribute('aria-disabled', 'true');
        prevBtn.classList.add('is-disabled');
      } else {
        prevBtn.removeAttribute('disabled');
        prevBtn.removeAttribute('aria-disabled');
        prevBtn.classList.remove('is-disabled');
        const prevTitle = `View previous ${catName} products`;
        prevBtn.setAttribute('title', prevTitle);
        prevBtn.setAttribute('aria-label', prevTitle);
      }
    }

    if (nextBtn) {
      const isFinal = totalPages <= 1 || state.step >= 2 || state.step >= totalPages - 1;
      if (isFinal) {
        nextBtn.setAttribute('title', 'View all products');
        nextBtn.setAttribute('aria-label', `View all ${catName} products`);
        nextBtn.classList.add('is-final-step');
      } else {
        const nextTitle = `View next ${catName} products`;
        nextBtn.setAttribute('title', nextTitle);
        nextBtn.setAttribute('aria-label', nextTitle);
        nextBtn.classList.remove('is-final-step');
      }
    }
  }

  function initCategorySliderInteractions(container) {
    container.querySelectorAll('.category-nav-arrow').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const catSlug = btn.dataset.category;
        const catName = btn.dataset.categoryName || '';
        const categoryUrl = btn.dataset.url || `/products?category=${catSlug}`;
        const totalPages = parseInt(btn.dataset.totalPages, 10) || 1;
        const direction = btn.dataset.direction || (btn.classList.contains('category-nav-arrow-prev') ? 'prev' : 'next');

        if (!categorySliderState[catSlug]) {
          categorySliderState[catSlug] = { step: 0, isAnimating: false };
        }
        const state = categorySliderState[catSlug];

        // Prevent repeated rapid clicks from breaking animation
        if (state.isAnimating) return;

        const track = document.getElementById(`category-track-${catSlug}`);

        if (direction === 'prev') {
          // Left arrow: reverse movement back to previous product page
          if (state.step <= 0) return;
          if (!track) return;

          state.isAnimating = true;
          state.step -= 1;
          track.style.transform = `translateX(-${state.step * 100}%)`;

          updateCategorySliderControls(catSlug, totalPages, catName);

          setTimeout(() => {
            state.isAnimating = false;
          }, 520);
        } else {
          // Right arrow: advance forward to next products or navigate to category page if at end
          if (totalPages <= 1 || state.step >= 2 || state.step >= totalPages - 1) {
            window.location.href = categoryUrl;
            return;
          }

          if (!track) {
            window.location.href = categoryUrl;
            return;
          }

          state.isAnimating = true;
          state.step += 1;
          track.style.transform = `translateX(-${state.step * 100}%)`;

          updateCategorySliderControls(catSlug, totalPages, catName);

          setTimeout(() => {
            state.isAnimating = false;
          }, 520);
        }
      });
    });
  }

  function renderCategoryProductSections() {
    const container = document.getElementById('category-product-sections');
    if (!container) return;

    const categories = (window.BongoCategories && typeof window.BongoCategories.getAll === 'function')
      ? window.BongoCategories.getAll().filter(c => !c.isDisabled)
      : (typeof CATEGORIES !== 'undefined' ? CATEGORIES : []);

    const sourceProducts = window.BongoProducts
      ? window.BongoProducts.getAll().filter(p => !p.isDisabled)
      : (typeof PRODUCTS !== 'undefined' ? PRODUCTS : []);

    // Render all available categories that have products dynamically
    const eligibleCategories = categories.filter(cat => {
      return sourceProducts.some(p => p.category === cat.slug);
    });

    if (eligibleCategories.length === 0) {
      container.innerHTML = '';
      return;
    }

    container.innerHTML = eligibleCategories.map((cat, catIdx) => {
      const allCatProducts = sourceProducts.filter(p => p.category === cat.slug);
      const categoryUrl = `/products?category=${cat.slug}`;

      // Divide products into pages of up to 5 products each (max 3 pages for homepage)
      const pages = [];
      const pageSize = 5;
      const maxPages = 3; // Allow up to 2 advances (pages 0, 1, 2)
      for (let i = 0; i < allCatProducts.length && pages.length < maxPages; i += pageSize) {
        pages.push(allCatProducts.slice(i, i + pageSize));
      }
      if (pages.length === 0 && allCatProducts.length > 0) {
        pages.push(allCatProducts.slice(0, 5));
      }

      const totalPages = pages.length;
      categorySliderState[cat.slug] = { step: 0, isAnimating: false };

      const mobileEndCardHTML = `
        <a href="${categoryUrl}" class="category-mobile-end-card" aria-label="View all ${cat.name} products">
          <div class="category-mobile-end-arrow-circle">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12"></line>
              <polyline points="12 5 19 12 12 19"></polyline>
            </svg>
          </div>
          <span class="category-mobile-end-text">View All</span>
        </a>
      `;

      const pagesHTML = pages.map((pageProducts, pageIdx) => `
        <div class="category-product-page product-grid product-grid-5" data-page="${pageIdx}">
          ${pageProducts.map(p => createProductCardHTML(p)).join('')}
          ${pageIdx === 0 ? mobileEndCardHTML : ''}
        </div>
      `).join('');

      // Left & Right arrow controls positioned at the sides of each category's product row
      const nextArrowTitle = totalPages > 1 ? `View next ${cat.name} products` : `View all ${cat.name} products`;
      const prevArrowTitle = `View previous ${cat.name} products`;

      const prevArrowHTML = `
        <button type="button" 
                class="category-nav-arrow category-nav-arrow-prev is-disabled" 
                data-category="${cat.slug}"
                data-category-name="${cat.name}"
                data-direction="prev"
                data-url="${categoryUrl}"
                data-total-pages="${totalPages}"
                title="${prevArrowTitle}"
                aria-label="${prevArrowTitle}"
                disabled
                aria-disabled="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
          </svg>
        </button>
      `;

      const nextArrowHTML = `
        <button type="button" 
                class="category-nav-arrow category-nav-arrow-next ${totalPages <= 1 ? 'is-final-step' : ''}" 
                data-category="${cat.slug}"
                data-category-name="${cat.name}"
                data-direction="next"
                data-url="${categoryUrl}"
                data-total-pages="${totalPages}"
                title="${nextArrowTitle}"
                aria-label="${nextArrowTitle}">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="5" y1="12" x2="19" y2="12"></line>
            <polyline points="12 5 19 12 12 19"></polyline>
          </svg>
        </button>
      `;

      const isAltBg = catIdx % 2 === 1;

      return `
        <section class="section category-products-section ${isAltBg ? 'category-section-alt' : ''} fade-up visible" id="category-section-${cat.slug}">
          <div class="container">
            <div class="section-header">
              <div class="section-header-top">
                <div class="section-header-title">
                  <h2>${cat.name}</h2>
                </div>
                <a href="${categoryUrl}" class="section-see-more" aria-label="Show all ${cat.name} products">Show All &rarr;</a>
              </div>
            </div>

            <div class="category-slider-wrapper" data-category="${cat.slug}">
              ${prevArrowHTML}
              ${nextArrowHTML}
              <div class="category-slider-viewport">
                <div class="category-slider-track" id="category-track-${cat.slug}">
                  ${pagesHTML}
                </div>
              </div>
            </div>
          </div>
        </section>
      `;
    }).join('');

    container.querySelectorAll('.product-grid').forEach(grid => {
      bindProductCardEvents(grid);
    });

    initCategorySliderInteractions(container);
  }

  function filterProducts(filterCategory) {
    state.currentCategoryFilter = filterCategory;

    document.querySelectorAll('#product-filter-tabs .filter-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.filter === filterCategory);
    });

    const categoryUrl = (filterCategory && filterCategory !== 'all')
      ? `/products?category=${filterCategory}`
      : '/products';

    const showMoreLink = document.querySelector('#products-section .section-see-more');
    if (showMoreLink) {
      showMoreLink.href = categoryUrl;
    }

    renderProducts();
  }

  function initFilterTabs() {
    const tabsContainer = document.getElementById('product-filter-tabs');
    if (!tabsContainer) return;

    tabsContainer.querySelectorAll('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        filterProducts(btn.dataset.filter);
      });
    });

    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-filter-trigger]');
      if (trigger) {
        filterProducts(trigger.dataset.filterTrigger);
      }
      const filterLink = e.target.closest('[data-filter]');
      if (filterLink) {
        filterProducts(filterLink.dataset.filter);
      }
    });
  }

  /* ══════════════════════════════════════════════════════════
     4. RENDER HOME & LIVING SHOWCASE
     ══════════════════════════════════════════════════════════ */
  function renderHomeLivingShowcase() {
    const grid = document.getElementById('home-showcase-grid');
    if (!grid) return;

    const sourceProducts = window.BongoProducts ? window.BongoProducts.getAll().filter(p => !p.isDisabled) : (typeof PRODUCTS !== 'undefined' ? PRODUCTS : []);
    const homeProducts = sourceProducts.filter(p => p.category === 'home-decor').slice(0, 4);
    grid.innerHTML = homeProducts.map(p => createProductCardHTML(p)).join('');
    bindProductCardEvents(grid);
  }

  function renderPreorderPreview() {
    const previewGrid = document.getElementById('preorder-products-preview');
    if (!previewGrid) return;

    const sourceProducts = window.BongoProducts ? window.BongoProducts.getAll().filter(p => !p.isDisabled) : (typeof PRODUCTS !== 'undefined' ? PRODUCTS : []);
    const preorderProducts = sourceProducts.filter(p => p.badge === 'preorder' || p.availability === 'preorder').slice(0, 4);
    if (preorderProducts.length === 0) {
      const section = previewGrid.closest('section');
      if (section) section.style.display = 'none';
      return;
    }
    previewGrid.innerHTML = preorderProducts.map(p => createProductCardHTML(p)).join('');
    bindProductCardEvents(previewGrid);
  }

  /* ══════════════════════════════════════════════════════════
     NAVBAR
     ══════════════════════════════════════════════════════════ */
  function renderNavbar() {
    const navItems = document.getElementById('nav-items');
    const mobileNavItems = document.getElementById('mobile-nav-items');
    if (!navItems) return;

    navItems.innerHTML = NAV_CATEGORIES.map(cat => {
      let megaHTML = '';
      if (cat.megaMenu) {
        megaHTML = `
          <div class="mega-menu">
            ${cat.megaMenu.map(col => `
              <div class="mega-menu-col">
                <h4>${col.heading}</h4>
                <ul>${col.links.map(l => `<li><a href="/products?category=${l.slug}${l.sub ? '&subcategory=' + l.sub : ''}">${l.label}</a></li>`).join('')}</ul>
              </div>
            `).join('')}
          </div>
        `;
      }
      const targetHref = cat.customUrl || (cat.filterCategory ? `/products?category=${cat.filterCategory}` : '/products');
      return `
        <li class="nav-item">
          <a href="${targetHref}" class="nav-link">${cat.name}${cat.megaMenu ? ICONS.chevronDown : ''}</a>
          ${megaHTML}
        </li>
      `;
    }).join('');

    if (mobileNavItems) {
      mobileNavItems.innerHTML = NAV_CATEGORIES.map(cat => {
        let subHTML = '';
        if (cat.megaMenu) {
          const allLinks = cat.megaMenu.flatMap(col => col.links);
          subHTML = `
            <div class="mobile-submenu">
              ${allLinks.map(l => `<a href="/products?category=${l.slug}${l.sub ? '&subcategory=' + l.sub : ''}">${l.label}</a>`).join('')}
            </div>
          `;
        }
        const targetHref = cat.customUrl || (cat.filterCategory ? `/products?category=${cat.filterCategory}` : '/products');
        return `
          <div class="mobile-nav-item ${cat.megaMenu ? 'has-submenu' : ''}">
            <a href="${targetHref}" class="mobile-nav-link">
              ${cat.name}
              ${cat.megaMenu ? ICONS.chevronDown : ''}
            </a>
            ${subHTML}
          </div>
        `;
      }).join('') + `
        <div class="mobile-nav-item" style="padding: 0.5rem 0 0.25rem 0">
          <a href="/bulk-order" class="btn btn-outline" style="width:100%" onclick="document.getElementById('mobile-nav-close').click()">Bulk & Corporate Orders</a>
        </div>
        <div class="mobile-nav-item" style="padding: 0.25rem 0 1rem 0">
          <a href="/request" class="btn btn-primary" style="width:100%" onclick="document.getElementById('mobile-nav-close').click()">Request Custom Sourcing</a>
        </div>
      `;

      mobileNavItems.querySelectorAll('.mobile-nav-item.has-submenu .mobile-nav-link').forEach(link => {
        link.addEventListener('click', (e) => {
          if (link.parentElement.classList.contains('has-submenu')) {
            e.preventDefault();
            const item = link.closest('.mobile-nav-item');
            const sub = item.querySelector('.mobile-submenu');
            item.classList.toggle('expanded');
            sub.classList.toggle('open');
          }
        });
      });
    }
  }

  function applySession(data) {
    state.currentUser = data.user || null;
    state.cart = reconcileCart(data.cart || []);
    state.wishlist = data.wishlist || [];
    try {
      localStorage.setItem('bongo_cart', JSON.stringify(state.cart));
      localStorage.setItem('bongo_wishlist', JSON.stringify(state.wishlist));
    } catch (e) { /* storage unavailable */ }
    renderNavbarIcons();
    updateCartCount();
    updateWishlistCount();
    renderCartDrawer();
    renderWishlistDrawer();
    updateAllWishlistIcons();
  }

  // After returning from Google: show the result and merge the guest cart/wishlist saved before leaving.
  async function handleAuthRedirect() {
    const flash = BOOT.flash || {};
    if (flash.error) showToast(flash.error);
    if (flash.notice) showToast(flash.notice);
    let pending = null;
    try { pending = JSON.parse(localStorage.getItem('bongo_pending_merge')); } catch (e) { /* ignore */ }
    if (!pending) return;
    try { localStorage.removeItem('bongo_pending_merge'); } catch (e) { /* ignore */ }
    if (!state.currentUser) return;
    const res = await api('POST', '/account/merge', pending);
    if (res.ok) applySession(res.data);
  }

  function guestPayload() {
    return { cart: state.cart.map(i => ({ productId: i.productId, color: i.color || null, size: i.size || null, quantity: i.quantity || 1 })), wishlist: wishlistIds() };
  }

  async function loginUser(email, password) {
    const res = await api('POST', '/auth/login', Object.assign({ email: (email || '').trim(), password }, guestPayload()));
    if (!res.ok) return { success: false, message: apiMessage(res) };
    applySession(res.data);
    return { success: true, user: res.data.user };
  }

  async function registerUser(name, email, password) {
    const res = await api('POST', '/auth/register', Object.assign({ name: (name || '').trim(), email: (email || '').trim(), password }, guestPayload()));
    if (!res.ok) return { success: false, message: apiMessage(res) };
    applySession(res.data);
    return { success: true, user: res.data.user };
  }

  async function logoutUser() {
    const out = await api('POST', '/auth/logout');
    if (out.data && out.data.csrf) CSRF = out.data.csrf;
    state.currentUser = null;
    state.cart = [];
    state.wishlist = [];
    try {
      localStorage.removeItem('bongo_cart');
      localStorage.removeItem('bongo_wishlist');
    } catch (e) { /* storage unavailable */ }
    renderNavbarIcons();
    showToast('Logged out successfully');
    if (window.location.pathname.startsWith('/account')) {
      window.location.href = '/';
    } else {
      updateCartCount();
      updateWishlistCount();
      updateAllWishlistIcons();
      renderWishlistDrawer();
      renderCartDrawer();
    }
  }

  async function requestPasswordReset(email) {
    const res = await api('POST', '/auth/forgot', { email: (email || '').trim() });
    return res.ok ? { success: true, message: res.data.message } : { success: false, message: apiMessage(res) };
  }

  async function resetPassword(email, code, newPassword) {
    const res = await api('POST', '/auth/reset', { email: (email || '').trim(), code: (code || '').trim(), password: newPassword });
    return res.ok ? { success: true, message: res.data.message } : { success: false, message: apiMessage(res) };
  }

  function renderNavbarIcons() {
    const el = document.getElementById('navbar-actions');
    if (!el) return;

    const curr = state.currentUser;
    const userIconContent = curr ? `<span class="user-avatar-badge">${(curr.name || 'U').charAt(0).toUpperCase()}</span>` : ICONS.user;

    el.innerHTML = `
      <button class="navbar-action-btn" id="search-toggle" aria-label="Search">${ICONS.search}</button>
      <button class="navbar-action-btn" id="wishlist-toggle" aria-label="Wishlist">
        ${ICONS.heart}
        <span class="wishlist-count" id="wishlist-count" style="display:${state.wishlist.length ? 'flex' : 'none'}">${state.wishlist.length}</span>
      </button>
      <button class="navbar-action-btn" id="cart-toggle" aria-label="Cart">
        ${ICONS.bag}
        <span class="cart-count" id="cart-count" style="display:${state.cart.length ? 'flex' : 'none'}">${state.cart.length}</span>
      </button>
      <div class="user-menu-wrapper">
        <button class="navbar-action-btn" id="user-account-toggle" aria-label="Account" title="${curr ? `Logged in as ${curr.name}` : 'Account / Sign In'}">
          ${userIconContent}
        </button>
        <div class="user-dropdown-menu" id="user-dropdown-menu">
          ${curr ? `
            <div class="user-dropdown-header">
              <div class="user-dropdown-name">${curr.name}</div>
              <div class="user-dropdown-email">${curr.email}</div>
            </div>
            <a href="/account" class="user-dropdown-item">👤 My Profile & Account</a>
            <a href="/account#orders" class="user-dropdown-item">📦 My Orders</a>
            <a href="/account#requests" class="user-dropdown-item">📋 Sourcing Requests</a>
            <div class="user-dropdown-divider"></div>
            <button class="user-dropdown-item" id="nav-dropdown-logout" style="color: var(--color-danger)">🚪 Log out</button>
          ` : `
            <div class="user-dropdown-header">
              <div class="user-dropdown-name">G'day, Guest</div>
              <div class="user-dropdown-email">Sign in for orders & wishlist</div>
            </div>
            <button class="user-dropdown-item" id="nav-dropdown-login">🔑 Log In</button>
            <button class="user-dropdown-item" id="nav-dropdown-register">✨ Create Account</button>
          `}
        </div>
      </div>
    `;

    document.getElementById('search-toggle').addEventListener('click', toggleSearch);
    document.getElementById('cart-toggle').addEventListener('click', toggleCart);
    document.getElementById('wishlist-toggle').addEventListener('click', toggleWishlistDrawer);

    const userToggle = document.getElementById('user-account-toggle');
    const dropdown = document.getElementById('user-dropdown-menu');

    if (userToggle && dropdown) {
      userToggle.addEventListener('click', (e) => {
        e.stopPropagation();
        if (!state.currentUser) {
          openAuthModal('login');
        } else {
          dropdown.classList.toggle('open');
        }
      });

      document.addEventListener('click', () => {
        dropdown.classList.remove('open');
      });
    }

    const loginBtn = document.getElementById('nav-dropdown-login');
    if (loginBtn) {
      loginBtn.addEventListener('click', () => {
        dropdown.classList.remove('open');
        openAuthModal('login');
      });
    }

    const registerBtn = document.getElementById('nav-dropdown-register');
    if (registerBtn) {
      registerBtn.addEventListener('click', () => {
        dropdown.classList.remove('open');
        openAuthModal('register');
      });
    }

    const logoutBtn = document.getElementById('nav-dropdown-logout');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', () => {
        dropdown.classList.remove('open');
        logoutUser();
      });
    }
  }

  function renderBottomNav() {
    const el = document.getElementById('bottom-nav-inner');
    if (!el) return;

    const curr = state.currentUser;
    const accountLabel = curr ? (curr.name.split(' ')[0]) : 'Account';

    el.innerHTML = `
      <a href="/" class="bottom-nav-item active">${ICONS.home}<span>Home</span></a>
      <a href="/products" class="bottom-nav-item">${ICONS.grid}<span>Shop</span></a>
      <a href="#" class="bottom-nav-item" id="bottom-wishlist-toggle">${ICONS.heart}<span>Wishlist</span></a>
      <a href="#" class="bottom-nav-item" id="bottom-cart-toggle">${ICONS.bag}<span>Bag</span></a>
      <a href="/account" class="bottom-nav-item" id="bottom-account-toggle">${ICONS.user}<span>${accountLabel}</span></a>
    `;

    document.getElementById('bottom-cart-toggle').addEventListener('click', (e) => {
      e.preventDefault();
      toggleCart();
    });

    const bottomWishlistBtn = document.getElementById('bottom-wishlist-toggle');
    if (bottomWishlistBtn) {
      bottomWishlistBtn.addEventListener('click', (e) => {
        e.preventDefault();
        toggleWishlistDrawer();
      });
    }

    const bottomAccountBtn = document.getElementById('bottom-account-toggle');
    if (bottomAccountBtn) {
      bottomAccountBtn.addEventListener('click', (e) => {
        if (!state.currentUser) {
          e.preventDefault();
          openAuthModal('login');
        }
      });
    }
  }

  /* ── Auth Modal Controller ── */
  function ensureAuthModalDOM() {
    let overlay = document.getElementById('auth-modal-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'auth-modal-overlay';
      overlay.className = 'auth-modal-overlay';
      overlay.innerHTML = `
        <div class="auth-modal-card">
          <button class="auth-modal-close" id="auth-modal-close" aria-label="Close">&times;</button>
          <div class="auth-card-body" id="auth-card-body">
            <!-- Dynamic Content rendered by renderAuthModalContent -->
          </div>
        </div>
      `;
      document.body.appendChild(overlay);

      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeAuthModal();
      });

      const closeBtn = overlay.querySelector('#auth-modal-close');
      if (closeBtn) closeBtn.addEventListener('click', closeAuthModal);
    }
    return overlay;
  }

  let currentAuthTab = 'login'; // 'login', 'register', 'forgot', 'reset'
  let resetEmail = '';

  function openAuthModal(tab = 'login') {
    currentAuthTab = tab;
    const overlay = ensureAuthModalDOM();
    renderAuthModalContent();
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeAuthModal() {
    const overlay = document.getElementById('auth-modal-overlay');
    if (overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
  }

  function renderAuthModalContent() {
    const body = document.getElementById('auth-card-body');
    if (!body) return;

    const googleButton = BOOT.googleLogin ? `
        <div class="auth-divider">OR</div>
        <button type="button" class="social-auth-btn" id="google-auth-btn">
          <svg class="social-auth-icon" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
          Continue with Google
        </button>` : '';

    const alerts = `
        <div class="auth-error-alert" id="auth-error-alert"></div>
        <div class="auth-success-alert" id="auth-success-alert"></div>`;

    if (currentAuthTab === 'login') {
      body.innerHTML = `
        <div class="auth-header">
          <h3 class="auth-title">Welcome Back</h3>
          <p class="auth-subtitle">Log in to manage your Australian orders and saved items</p>
        </div>
        <div class="auth-tabs">
          <button class="auth-tab-btn active" data-tab="login">Sign In</button>
          <button class="auth-tab-btn" data-tab="register">Create Account</button>
        </div>
        ${alerts}
        <form id="auth-login-form" novalidate>
          <div class="auth-form-group">
            <label class="auth-label" for="login-email">Email Address</label>
            <input type="email" id="login-email" class="auth-input" placeholder="e.g. mate@example.com.au" required>
          </div>
          <div class="auth-form-group">
            <div class="auth-label-row">
              <label class="auth-label" for="login-password">Password</label>
              <a href="#" class="auth-link" id="auth-forgot-link">Forgot Password?</a>
            </div>
            <input type="password" id="login-password" class="auth-input" placeholder="••••••••" required>
          </div>
          <button type="submit" class="btn btn-primary auth-submit-btn" id="login-submit-btn">Sign In</button>
        </form>
        ${googleButton}
      `;
    } else if (currentAuthTab === 'register') {
      body.innerHTML = `
        <div class="auth-header">
          <h3 class="auth-title">Create an Account</h3>
          <p class="auth-subtitle">Join Shaotal for fast checkout and custom sourcing</p>
        </div>
        <div class="auth-tabs">
          <button class="auth-tab-btn" data-tab="login">Sign In</button>
          <button class="auth-tab-btn active" data-tab="register">Create Account</button>
        </div>
        ${alerts}
        <form id="auth-register-form" novalidate>
          <div class="auth-form-group">
            <label class="auth-label" for="reg-name">Full Name</label>
            <input type="text" id="reg-name" class="auth-input" placeholder="Your Name Here" required>
          </div>
          <div class="auth-form-group">
            <label class="auth-label" for="reg-email">Email Address</label>
            <input type="email" id="reg-email" class="auth-input" placeholder="e.g. mate@example.com.au" required>
          </div>
          <div class="auth-form-group">
            <label class="auth-label" for="reg-password">Password</label>
            <input type="password" id="reg-password" class="auth-input" placeholder="At least 6 characters" required>
          </div>
          <div class="auth-form-group">
            <label class="auth-label" for="reg-confirm">Confirm Password</label>
            <input type="password" id="reg-confirm" class="auth-input" placeholder="Re-enter password" required>
          </div>
          <button type="submit" class="btn btn-primary auth-submit-btn">Create Account</button>
        </form>
        ${googleButton}
      `;
    } else if (currentAuthTab === 'forgot') {
      body.innerHTML = `
        <div class="auth-header">
          <h3 class="auth-title">Reset Password</h3>
          <p class="auth-subtitle">Enter your registered email address and we will send you a 6-digit reset code.</p>
        </div>
        ${alerts}
        <form id="auth-forgot-form" novalidate>
          <div class="auth-form-group">
            <label class="auth-label" for="forgot-email">Email Address</label>
            <input type="email" id="forgot-email" class="auth-input" placeholder="e.g. mate@example.com.au" required>
          </div>
          <button type="submit" class="btn btn-primary auth-submit-btn">Send Reset Code</button>
        </form>
        <div style="text-align:center; margin-top:var(--space-md)">
          <a href="#" class="auth-link" id="auth-back-login">← Back to Sign In</a>
        </div>
      `;
    } else if (currentAuthTab === 'reset') {
      body.innerHTML = `
        <div class="auth-header">
          <h3 class="auth-title">Choose a New Password</h3>
          <p class="auth-subtitle">Enter the 6-digit code emailed to ${resetEmail.replace(/[<>&"]/g, '')} and your new password.</p>
        </div>
        ${alerts}
        <form id="auth-reset-form" novalidate>
          <div class="auth-form-group">
            <label class="auth-label" for="reset-code">Reset Code</label>
            <input type="text" id="reset-code" class="auth-input" inputmode="numeric" maxlength="6" placeholder="123456" required>
          </div>
          <div class="auth-form-group">
            <label class="auth-label" for="reset-password">New Password</label>
            <input type="password" id="reset-password" class="auth-input" placeholder="At least 6 characters" required>
          </div>
          <button type="submit" class="btn btn-primary auth-submit-btn">Update Password</button>
        </form>
        <div style="text-align:center; margin-top:var(--space-md)">
          <a href="#" class="auth-link" id="auth-back-login">← Back to Sign In</a>
        </div>
      `;
    }

    const showError = (msg) => {
      const errEl = body.querySelector('#auth-error-alert');
      if (errEl) { errEl.textContent = msg; errEl.style.display = 'block'; }
    };
    const clearError = () => {
      const errEl = body.querySelector('#auth-error-alert');
      if (errEl) errEl.style.display = 'none';
    };
    const afterAuth = (message) => {
      showToast(message);
      closeAuthModal();
      if (window.location.pathname === '/account' || window.location.pathname === '/checkout') window.location.reload();
    };
    const busy = (form, isBusy) => {
      const btn = form.querySelector('button[type="submit"]');
      if (btn) btn.disabled = isBusy;
    };

    body.querySelectorAll('.auth-tab-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        currentAuthTab = btn.dataset.tab;
        renderAuthModalContent();
      });
    });

    const googleBtn = body.querySelector('#google-auth-btn');
    if (googleBtn) {
      googleBtn.addEventListener('click', () => {
        // Keep the guest cart/wishlist so it can be merged into the account after the Google round trip.
        try { localStorage.setItem('bongo_pending_merge', JSON.stringify(guestPayload())); } catch (e) { /* storage unavailable */ }
        window.location.href = '/auth/google?return=' + encodeURIComponent(window.location.pathname + window.location.search);
      });
    }

    const forgotLink = body.querySelector('#auth-forgot-link');
    if (forgotLink) {
      forgotLink.addEventListener('click', (e) => {
        e.preventDefault();
        currentAuthTab = 'forgot';
        renderAuthModalContent();
      });
    }

    const backLoginLink = body.querySelector('#auth-back-login');
    if (backLoginLink) {
      backLoginLink.addEventListener('click', (e) => {
        e.preventDefault();
        currentAuthTab = 'login';
        renderAuthModalContent();
      });
    }

    const loginForm = body.querySelector('#auth-login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        busy(loginForm, true);
        const res = await loginUser(body.querySelector('#login-email').value, body.querySelector('#login-password').value);
        busy(loginForm, false);
        if (!res.success) return showError(res.message);
        afterAuth(`Welcome back, ${res.user.name}!`);
      });
    }

    const registerForm = body.querySelector('#auth-register-form');
    if (registerForm) {
      registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        const password = body.querySelector('#reg-password').value;
        if (password !== body.querySelector('#reg-confirm').value) return showError('Passwords do not match.');
        busy(registerForm, true);
        const res = await registerUser(body.querySelector('#reg-name').value, body.querySelector('#reg-email').value, password);
        busy(registerForm, false);
        if (!res.success) return showError(res.message);
        afterAuth(`Account created! Welcome, ${res.user.name}`);
      });
    }

    const forgotForm = body.querySelector('#auth-forgot-form');
    if (forgotForm) {
      forgotForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        const email = body.querySelector('#forgot-email').value;
        busy(forgotForm, true);
        const res = await requestPasswordReset(email);
        busy(forgotForm, false);
        if (!res.success) return showError(res.message);
        resetEmail = email.trim();
        showToast(res.message);
        currentAuthTab = 'reset';
        renderAuthModalContent();
      });
    }

    const resetForm = body.querySelector('#auth-reset-form');
    if (resetForm) {
      resetForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearError();
        busy(resetForm, true);
        const res = await resetPassword(resetEmail, body.querySelector('#reset-code').value, body.querySelector('#reset-password').value);
        busy(resetForm, false);
        if (!res.success) return showError(res.message);
        showToast(res.message);
        currentAuthTab = 'login';
        renderAuthModalContent();
      });
    }
  }

  // Export functions globally
  window.openAuthModal = openAuthModal;
  window.closeAuthModal = closeAuthModal;
  window.loginUser = loginUser;
  window.registerUser = registerUser;
  window.logoutUser = logoutUser;
  window.getStoredCurrentUser = getStoredCurrentUser;
  window.saveCurrentUser = saveCurrentUser;
  window.updateUserInUsersList = updateUserInUsersList;

  /* ══════════════════════════════════════════════════════════
     FOOTER
     ══════════════════════════════════════════════════════════ */
  function renderFooter() {
    const footerGrid = document.getElementById('footer-grid');
    if (!footerGrid) return;

    const social = BOOT.social || {};
    footerGrid.innerHTML = `
      <div class="footer-brand">
        <a href="/" class="footer-logo-link" aria-label="${SITE.brandName}">
          <img src="/assets/logos/logo.png" alt="${SITE.brandName}" class="footer-logo">
        </a>
        <p>An Australian ecommerce brand offering thoughtfully curated lifestyle pieces — from fashion and handcrafted decor to natural jute items and gifts.</p>
        <div class="footer-social">
          ${social.facebook ? `<a href="${social.facebook}" target="_blank" rel="noopener" aria-label="Facebook">${ICONS.facebook}</a>` : ''}
          ${social.instagram ? `<a href="${social.instagram}" target="_blank" rel="noopener" aria-label="Instagram">${ICONS.instagram}</a>` : ''}
          ${social.tiktok ? `<a href="${social.tiktok}" target="_blank" rel="noopener" aria-label="TikTok">${ICONS.tiktok}</a>` : ''}
        </div>
      </div>
      <div class="footer-col">
        <h4>Shop</h4>
        <ul>${FOOTER_LINKS.shop.map(l => `<li><a href="${l.href}">${l.label}</a></li>`).join('')}</ul>
      </div>
      <div class="footer-col">
        <h4>Customer Care</h4>
        <ul>${FOOTER_LINKS.help.map(l => `<li><a href="${l.href}">${l.label}</a></li>`).join('')}</ul>
      </div>
      <div class="footer-col">
        <h4>About</h4>
        <ul>${FOOTER_LINKS.about.map(l => `<li><a href="${l.href}">${l.label}</a></li>`).join('')}</ul>
      </div>
    `;
  }

  /* ══════════════════════════════════════════════════════════
     COLOR MAP & HELPER
     ══════════════════════════════════════════════════════════ */
  const COLOR_NAME_MAP = window.COLOR_NAMES || {};

  function getColorName(val) {
    if (!val) return '';
    if (typeof val !== 'string') return val;
    const upper = val.toUpperCase();
    if (COLOR_NAME_MAP[upper]) return COLOR_NAME_MAP[upper];
    if (COLOR_NAME_MAP[val]) return COLOR_NAME_MAP[val];
    if (val.startsWith('#')) {
      return 'Color ' + val.substring(1);
    }
    return val;
  }

  function findCartItemForWishlist(item, product) {
    if (!product) return { cartIndex: -1, cartItem: null, cartQty: 0 };
    let selSize = (typeof item === 'object' && item) ? item.size : null;
    let selColor = (typeof item === 'object' && item) ? item.color : null;

    let idx = -1;
    if (selSize || selColor) {
      idx = state.cart.findIndex(c => {
        const cId = c.productId || c.id;
        if (cId !== product.id) return false;
        if (selSize && c.size && c.size !== selSize) return false;
        if (selColor && c.color && c.color !== selColor) return false;
        return true;
      });
    }

    if (idx === -1) {
      idx = state.cart.findIndex(c => (c.productId || c.id) === product.id);
    }

    if (idx > -1) {
      return {
        cartIndex: idx,
        cartItem: state.cart[idx],
        cartQty: state.cart[idx].quantity || 1
      };
    }
    return { cartIndex: -1, cartItem: null, cartQty: 0 };
  }

  /* ══════════════════════════════════════════════════════════
     WISHLIST & CART
     ══════════════════════════════════════════════════════════ */
  function isProductInWishlist(productId) {
    const targetId = typeof productId === 'object' ? (productId.id || productId.productId) : productId;
    return state.wishlist.some(item => (typeof item === 'object' ? (item.id || item.productId) : item) === targetId);
  }

  function updateAllWishlistIcons() {
    document.querySelectorAll('.product-card-wishlist').forEach(btn => {
      const pId = parseInt(btn.dataset.wishlistId, 10);
      const inWishlist = isProductInWishlist(pId);
      if (inWishlist) {
        btn.classList.add('active');
        btn.innerHTML = ICONS.heartFill;
      } else {
        btn.classList.remove('active');
        btn.innerHTML = ICONS.heart;
      }
    });

    const detailBtn = document.getElementById('detail-wishlist-trigger');
    if (detailBtn) {
      const detailId = (window.detailState && window.detailState.product) ? window.detailState.product.id : null;
      if (detailId) {
        const inWishlist = isProductInWishlist(detailId);
        if (inWishlist) {
          detailBtn.classList.add('active');
          detailBtn.innerHTML = ICONS.heartFill;
        } else {
          detailBtn.classList.remove('active');
          detailBtn.innerHTML = ICONS.heart;
        }
      }
    }
  }

  function updateWishlistCount() {
    const totalCount = state.wishlist.length;

    const countEl = document.getElementById('wishlist-count');
    if (countEl) {
      countEl.textContent = totalCount;
      countEl.style.display = totalCount > 0 ? 'flex' : 'none';
    }

    const drawerCountEl = document.getElementById('wishlist-drawer-count');
    if (drawerCountEl) {
      drawerCountEl.textContent = `${totalCount} Item${totalCount === 1 ? '' : 's'}`;
    }
  }

  function toggleWishlist(productId) {
    const targetId = typeof productId === 'object' ? (productId.id || productId.productId) : productId;
    const idx = state.wishlist.findIndex(item => (typeof item === 'object' ? (item.id || item.productId) : item) === targetId);
    if (idx > -1) {
      state.wishlist.splice(idx, 1);
      showToast('Removed from wishlist');
    } else {
      state.wishlist.push(targetId);
      showToast('Saved to wishlist ♡');
    }
    saveWishlist();
    updateWishlistCount();
    renderWishlistDrawer();
    updateAllWishlistIcons();
    if (typeof renderProducts === 'function') {
      renderProducts(state.currentCategoryFilter);
    }
  }

  function removeFromWishlist(index) {
    if (index < 0 || index >= state.wishlist.length) return;
    state.wishlist.splice(index, 1);
    saveWishlist();
    updateWishlistCount();
    renderWishlistDrawer();
    updateAllWishlistIcons();
    if (typeof renderProducts === 'function') {
      renderProducts(state.currentCategoryFilter);
    }
    showToast('Removed from wishlist');
  }

  function renderWishlistDrawer() {
    const body = document.getElementById('wishlist-drawer-body');
    const footer = document.getElementById('wishlist-drawer-footer');
    if (!body) return;

    updateWishlistCount();

    if (state.wishlist.length === 0) {
      body.innerHTML = `
        <div class="wishlist-drawer-empty">
          <div class="wishlist-empty-icon">${ICONS.heart}</div>
          <h4 class="wishlist-empty-title">Your wishlist is empty</h4>
          <p class="wishlist-empty-desc">Discover artisan items curated for Australian living and save your favorites for later.</p>
          <button class="btn btn-primary wishlist-empty-cta" id="wishlist-empty-continue">Continue Shopping</button>
        </div>
      `;
      if (footer) {
        footer.innerHTML = '';
        footer.style.display = 'none';
      }

      const continueBtn = document.getElementById('wishlist-empty-continue');
      if (continueBtn) {
        continueBtn.addEventListener('click', closeWishlist);
      }
      return;
    }

    if (footer) {
      footer.style.display = 'block';
    }

    body.innerHTML = state.wishlist.map((item, i) => {
      let product = null;
      let selectedSize = null;
      let selectedColor = null;
      let qty = 1;

      if (typeof item === 'object' && item !== null) {
        const pId = item.productId || item.id;
        product = PRODUCTS.find(p => p.id === pId);
        selectedSize = item.size || null;
        selectedColor = item.color || null;
        qty = item.quantity || 1;
      } else {
        product = PRODUCTS.find(p => p.id === item);
      }

      if (!product) return '';

      if (!selectedSize && product.sizes && product.sizes.length > 0) {
        selectedSize = product.sizes[0];
      }
      if (!selectedColor && product.colors && product.colors.length > 0) {
        selectedColor = product.colors[0];
      }

      const colorName = selectedColor ? getColorName(selectedColor) : null;
      let variantText = '';
      if (selectedSize && colorName) {
        variantText = `${selectedSize} / ${colorName}`;
      } else if (selectedSize) {
        variantText = `${selectedSize}`;
      } else if (colorName) {
        variantText = `${colorName}`;
      } else {
        variantText = 'Standard';
      }

      const displayPrice = product.price * qty;
      const { cartIndex, cartQty } = findCartItemForWishlist(item, product);

      const actionHtml = cartQty > 0 ? `
        <div class="cart-quantity-selector wishlist-qty-box">
          <button class="cart-qty-btn minus wishlist-cart-minus-btn" data-cart-index="${cartIndex}" aria-label="Decrease quantity">&minus;</button>
          <span class="cart-qty-value">${cartQty}</span>
          <button class="cart-qty-btn plus wishlist-cart-plus-btn" data-cart-index="${cartIndex}" aria-label="Increase quantity">+</button>
        </div>
        <button class="wishlist-item-remove-btn" data-index="${i}">Remove</button>
      ` : `
        <button class="btn btn-primary wishlist-add-to-cart-btn" data-index="${i}">Add to Cart</button>
        <button class="wishlist-item-remove-btn" data-index="${i}">Remove</button>
      `;

      return `
        <div class="wishlist-item" data-index="${i}">
          <div class="wishlist-item-image">
            <img src="${imgUrl(product.image, 160)}" alt="${product.name}" width="80" height="80" loading="lazy" decoding="async">
          </div>
          <div class="wishlist-item-details">
            <div class="wishlist-item-top">
              <div class="wishlist-item-header-row">
                <h4 class="wishlist-item-name">${product.name}</h4>
              </div>
              <div class="wishlist-item-variant">Variant: ${variantText}</div>
            </div>
            <div class="wishlist-item-bottom">
              <div class="wishlist-item-price-wrapper">
                <span class="wishlist-item-price">${SITE.currency}${displayPrice.toFixed(2)}</span>
              </div>
              <div class="wishlist-item-actions">
                ${actionHtml}
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');

    if (footer) {
      footer.innerHTML = `
        <button class="wishlist-continue-shopping" id="wishlist-drawer-continue">Continue Shopping</button>
      `;
    }

    body.querySelectorAll('.wishlist-add-to-cart-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        const item = state.wishlist[idx];
        if (!item) return;

        let pId = typeof item === 'object' ? (item.productId || item.id) : item;
        const product = PRODUCTS.find(p => p.id === pId);
        if (!product) return;

        // Open Variant Selection Modal (Wishlist remains open, item stays in Wishlist)
        openVariantModal(product);
      });
    });

    body.querySelectorAll('.wishlist-cart-minus-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const cIdx = parseInt(btn.dataset.cartIndex, 10);
        if (cIdx >= 0 && state.cart[cIdx]) {
          const currentQty = state.cart[cIdx].quantity || 1;
          updateCartQuantity(cIdx, currentQty - 1);
        }
      });
    });

    body.querySelectorAll('.wishlist-cart-plus-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const cIdx = parseInt(btn.dataset.cartIndex, 10);
        if (cIdx >= 0 && state.cart[cIdx]) {
          const currentQty = state.cart[cIdx].quantity || 1;
          updateCartQuantity(cIdx, currentQty + 1);
        }
      });
    });

    body.querySelectorAll('.wishlist-item-remove-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        removeFromWishlist(idx);
      });
    });

    const continueBtn = document.getElementById('wishlist-drawer-continue');
    if (continueBtn) {
      continueBtn.addEventListener('click', closeWishlist);
    }
  }

  function toggleWishlistDrawer() {
    const drawer = document.getElementById('wishlist-drawer');
    if (!drawer) return;
    if (drawer.classList.contains('open')) {
      closeWishlist();
    } else {
      openWishlist();
    }
  }

  function openWishlist() {
    const drawer = document.getElementById('wishlist-drawer');
    const overlay = document.getElementById('wishlist-drawer-overlay');
    if (!drawer || !overlay) return;
    closeCart();
    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    renderWishlistDrawer();
  }

  function closeWishlist() {
    const drawer = document.getElementById('wishlist-drawer');
    const overlay = document.getElementById('wishlist-drawer-overlay');
    if (!drawer || !overlay) return;
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  /* ══════════════════════════════════════════════════════════
     VARIANT SELECTION MODAL (Wishlist Add to Cart)
     ══════════════════════════════════════════════════════════ */
  let variantModalState = {
    product: null,
    selectedColor: null,
    selectedSize: null,
    quantity: 1
  };

  function openVariantModal(product) {
    if (!product) return;

    // Pre-populate with existing wishlist item choices if available
    const existingWItem = state.wishlist.find(item => (typeof item === 'object' ? (item.id || item.productId) : item) === product.id);
    const defaultColor = (typeof existingWItem === 'object' && existingWItem && existingWItem.color) ? existingWItem.color : (product.colors && product.colors.length > 0 ? product.colors[0] : null);
    const defaultSize = (typeof existingWItem === 'object' && existingWItem && existingWItem.size) ? existingWItem.size : (product.sizes && product.sizes.length > 0 ? product.sizes[0] : null);
    const defaultQty = (typeof existingWItem === 'object' && existingWItem && existingWItem.quantity) ? existingWItem.quantity : 1;

    variantModalState = {
      product: product,
      selectedColor: defaultColor,
      selectedSize: defaultSize,
      quantity: defaultQty
    };

    const overlay = document.getElementById('variant-modal-overlay');
    if (!overlay) return;

    const img = document.getElementById('variant-modal-img');
    const cat = document.getElementById('variant-modal-category');
    const title = document.getElementById('variant-modal-title');
    const price = document.getElementById('variant-modal-price');
    const colorsWrap = document.getElementById('variant-modal-colors-wrap');
    const colorsContainer = document.getElementById('variant-modal-colors');
    const selectedColorLabel = document.getElementById('variant-modal-selected-color');
    const sizesWrap = document.getElementById('variant-modal-sizes-wrap');
    const sizesContainer = document.getElementById('variant-modal-sizes');
    const selectedSizeLabel = document.getElementById('variant-modal-selected-size');
    const qtyVal = document.getElementById('variant-qty-value');
    const errorEl = document.getElementById('variant-modal-error');

    if (errorEl) errorEl.style.display = 'none';

    if (img) { img.src = imgUrl(product.image, 480); img.alt = product.name; }
    if (cat) cat.textContent = CATEGORY_NAME_MAP[product.category] || product.category;
    if (title) title.textContent = product.name;
    if (price) {
      const orig = product.originalPrice ? `<span class="original">${SITE.currency}${product.originalPrice.toFixed(2)}</span>` : '';
      price.innerHTML = `${orig}<span class="current">${SITE.currency}${product.price.toFixed(2)}</span>`;
    }
    if (qtyVal) qtyVal.textContent = variantModalState.quantity;

    // Render Colors
    if (product.colors && product.colors.length > 0) {
      colorsWrap.style.display = 'block';
      const initialColor = variantModalState.selectedColor || product.colors[0];
      if (selectedColorLabel) selectedColorLabel.textContent = getColorName(initialColor);

      colorsContainer.innerHTML = product.colors.map(c => {
        const isSelected = c === variantModalState.selectedColor;
        const isHex = c.startsWith('#');
        const bgStyle = isHex ? `background-color: ${c};` : '';
        const colorTitle = getColorName(c);
        return `<div class="modal-color-swatch ${isSelected ? 'selected' : ''}" data-color="${c}" title="${colorTitle}" style="${bgStyle}"></div>`;
      }).join('');

      colorsContainer.querySelectorAll('.modal-color-swatch').forEach(swatch => {
        swatch.addEventListener('click', () => {
          colorsContainer.querySelectorAll('.modal-color-swatch').forEach(s => s.classList.remove('selected'));
          swatch.classList.add('selected');
          variantModalState.selectedColor = swatch.dataset.color;
          if (selectedColorLabel) selectedColorLabel.textContent = getColorName(swatch.dataset.color);
          if (errorEl) errorEl.style.display = 'none';
        });
      });
    } else {
      colorsWrap.style.display = 'none';
      variantModalState.selectedColor = null;
    }

    // Render Sizes
    if (product.sizes && product.sizes.length > 0) {
      sizesWrap.style.display = 'block';
      if (selectedSizeLabel) selectedSizeLabel.textContent = variantModalState.selectedSize || product.sizes[0];

      sizesContainer.innerHTML = product.sizes.map(s => {
        const isSelected = s === variantModalState.selectedSize;
        return `<button type="button" class="modal-size-btn ${isSelected ? 'selected' : ''}" data-size="${s}">${s}</button>`;
      }).join('');

      sizesContainer.querySelectorAll('.modal-size-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          sizesContainer.querySelectorAll('.modal-size-btn').forEach(b => b.classList.remove('selected'));
          btn.classList.add('selected');
          variantModalState.selectedSize = btn.dataset.size;
          if (selectedSizeLabel) selectedSizeLabel.textContent = btn.dataset.size;
          if (errorEl) errorEl.style.display = 'none';
        });
      });
    } else {
      sizesWrap.style.display = 'none';
      variantModalState.selectedSize = null;
    }

    overlay.classList.add('open');
  }

  function closeVariantModal() {
    const overlay = document.getElementById('variant-modal-overlay');
    if (overlay) overlay.classList.remove('open');
  }

  function initVariantModalEvents() {
    const overlay = document.getElementById('variant-modal-overlay');
    const closeBtn = document.getElementById('variant-modal-close');
    const confirmBtn = document.getElementById('variant-modal-confirm-btn');
    const qtyPlus = document.getElementById('variant-qty-plus');
    const qtyMinus = document.getElementById('variant-qty-minus');
    const qtyVal = document.getElementById('variant-qty-value');

    if (closeBtn) closeBtn.addEventListener('click', closeVariantModal);
    if (overlay) {
      overlay.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeVariantModal();
      });
    }

    if (qtyPlus) {
      qtyPlus.addEventListener('click', () => {
        variantModalState.quantity++;
        if (qtyVal) qtyVal.textContent = variantModalState.quantity;
      });
    }

    if (qtyMinus) {
      qtyMinus.addEventListener('click', () => {
        if (variantModalState.quantity > 1) {
          variantModalState.quantity--;
          if (qtyVal) qtyVal.textContent = variantModalState.quantity;
        }
      });
    }

    if (confirmBtn) {
      confirmBtn.addEventListener('click', () => {
        const product = variantModalState.product;
        if (!product) return;

        const errorEl = document.getElementById('variant-modal-error');
        if (product.colors && product.colors.length > 0 && !variantModalState.selectedColor) {
          if (errorEl) { errorEl.textContent = 'Please select a color'; errorEl.style.display = 'block'; }
          return;
        }
        if (product.sizes && product.sizes.length > 0 && !variantModalState.selectedSize) {
          if (errorEl) { errorEl.textContent = 'Please select a size'; errorEl.style.display = 'block'; }
          return;
        }

        // Update item in state.wishlist with selected variant, size & quantity
        const wIdx = state.wishlist.findIndex(item => (typeof item === 'object' ? (item.id || item.productId) : item) === product.id);
        if (wIdx > -1) {
          state.wishlist[wIdx] = {
            productId: product.id,
            id: product.id,
            size: variantModalState.selectedSize,
            color: variantModalState.selectedColor,
            quantity: variantModalState.quantity
          };
          saveWishlist();
          renderWishlistDrawer();
        }

        // Add to Cart without closing Wishlist or auto-opening Cart Drawer
        addToCart(product.id, variantModalState.selectedColor, variantModalState.selectedSize, variantModalState.quantity, false);

        // Close Variant Modal
        closeVariantModal();

        // Show toast with "Go to Cart" link/action
        showToast('Added to cart', 'Go to Cart', () => {
          closeWishlist();
          openCart();
        });
      });
    }
  }

  function addToCart(productId, colorOrSize, size, quantity = 1, autoOpenCart = true) {
    const targetProduct = (typeof productId === 'object' && productId !== null) ? productId : PRODUCTS.find(p => p.id === productId);
    if (!targetProduct) return;

    // (id, color, size, qty) from the detail page / variant modal; (id, size) from quick view.
    let selectedColor = null;
    let selectedSize = null;
    let qty = 1;
    if (arguments.length >= 3) {
      selectedColor = colorOrSize || null;
      selectedSize = size || null;
      qty = Number(quantity) || 1;
    } else {
      selectedSize = colorOrSize || null;
    }
    if (!selectedColor && targetProduct.colors && targetProduct.colors.length) selectedColor = targetProduct.colors[0];
    if (!selectedSize && targetProduct.sizes && targetProduct.sizes.length) selectedSize = targetProduct.sizes[0];
    if (!targetProduct.colors || !targetProduct.colors.length) selectedColor = null;
    if (!targetProduct.sizes || !targetProduct.sizes.length) selectedSize = null;

    // Reject combinations that are out of stock before they reach the cart.
    if (targetProduct.hasVariants) {
      const v = (targetProduct.variants || []).find(x => (x.color === null || x.color === selectedColor) && (x.size === null || x.size === selectedSize));
      if (!v) { showToast('That option is unavailable'); return; }
    }

    if (targetProduct.soldOut) {
      showToast('Sorry, this item is currently unavailable');
      return;
    }

    const isPreorder = targetProduct.badge === 'preorder' || targetProduct.availability === 'preorder';

    const existingIndex = state.cart.findIndex(item =>
      (item.productId || item.id) === targetProduct.id &&
      item.size === selectedSize &&
      (item.color || null) === (selectedColor || null)
    );

    if (existingIndex > -1) {
      state.cart[existingIndex].quantity = (state.cart[existingIndex].quantity || 1) + qty;
    } else {
      state.cart.push({
        productId: targetProduct.id,
        name: targetProduct.name,
        price: variantPrice(targetProduct, selectedColor, selectedSize),
        originalPrice: targetProduct.originalPrice || targetProduct.price,
        size: selectedSize,
        color: selectedColor,
        quantity: qty,
        image: targetProduct.image,
        badge: targetProduct.badge,
        availability: targetProduct.availability,
        isPreorder: isPreorder,
      });
    }

    saveCart();
    updateCartCount();
    if (typeof closeQuickView === 'function') closeQuickView();
    if (autoOpenCart) {
      showToast(`${targetProduct.name} added to cart`);
      openCart();
    }
  }

  function updateCartQuantity(index, newQty) {
    if (index < 0 || index >= state.cart.length) return;
    if (newQty <= 0) {
      removeFromCart(index);
    } else {
      state.cart[index].quantity = newQty;
      saveCart();
      updateCartCount();
      renderCartDrawer();
    }
  }

  function removeFromCart(index) {
    if (index < 0 || index >= state.cart.length) return;
    state.cart.splice(index, 1);
    saveCart();
    updateCartCount();
    renderCartDrawer();
  }

  function updateCartCount() {
    const totalCount = state.cart.reduce((sum, item) => sum + (item.quantity || 1), 0);

    const countEl = document.getElementById('cart-count');
    if (countEl) {
      countEl.textContent = totalCount;
      countEl.style.display = totalCount > 0 ? 'flex' : 'none';
    }

    const drawerCountEl = document.getElementById('cart-drawer-count');
    if (drawerCountEl) {
      drawerCountEl.textContent = `${totalCount} Item${totalCount === 1 ? '' : 's'}`;
    }
  }

  function renderCartDrawer() {
    const body = document.getElementById('cart-drawer-body');
    const footer = document.getElementById('cart-drawer-footer');
    if (!body) return;

    const totalCount = state.cart.reduce((sum, item) => sum + (item.quantity || 1), 0);
    const drawerCountEl = document.getElementById('cart-drawer-count');
    if (drawerCountEl) {
      drawerCountEl.textContent = `${totalCount} Item${totalCount === 1 ? '' : 's'}`;
    }

    if (state.cart.length === 0) {
      body.innerHTML = `
        <div class="cart-drawer-empty">
          <div class="cart-empty-icon">${ICONS.bag}</div>
          <h4 class="cart-empty-title">Your cart is empty</h4>
          <p class="cart-empty-desc">Discover handcrafted items curated for Australian living.</p>
          <button class="btn btn-primary cart-empty-cta" id="cart-empty-continue">Continue Shopping</button>
        </div>
      `;
      if (footer) {
        footer.innerHTML = '';
        footer.style.display = 'none';
      }

      const continueBtn = document.getElementById('cart-empty-continue');
      if (continueBtn) {
        continueBtn.addEventListener('click', closeCart);
      }
      return;
    }

    if (footer) {
      footer.style.display = 'block';
    }

    let originalSubtotal = 0;
    let currentSubtotal = 0;

    body.innerHTML = state.cart.map((item, i) => {
      const qty = item.quantity || 1;
      const originalPrice = item.originalPrice || item.price;
      const price = item.price;

      originalSubtotal += originalPrice * qty;
      currentSubtotal += price * qty;

      const isPreorder = item.isPreorder || item.badge === 'preorder' || item.availability === 'preorder';

      const colorName = item.color ? getColorName(item.color) : null;
      let variantText = '';
      if (item.size && colorName) {
        variantText = `${item.size} / ${colorName}`;
      } else if (item.size) {
        variantText = `${item.size}`;
      } else if (colorName) {
        variantText = `${colorName}`;
      } else {
        variantText = 'Standard';
      }


      // ${isPreorder ? '<span class="cart-item-badge-preorder">Pre-order</span>' : ''}
      //   ${originalPrice > price ? `<span class="cart-item-original-price">${SITE.currency}${originalPrice.toFixed(2)}</span>` : ''}

      return `
        <div class="cart-item" data-index="${i}">
          <div class="cart-item-image">
            <img src="${imgUrl(item.image, 160)}" alt="${item.name}" width="80" height="80" loading="lazy" decoding="async">
          </div>
          <div class="cart-item-details">
            <div class="cart-item-top">
              <div class="cart-item-header-row">
                <h4 class="cart-item-name">${item.name}</h4>
              </div>
              <div class="cart-item-variant">Variant: ${variantText}</div>
            </div>
            <div class="cart-item-bottom">
              <div class="cart-item-price-wrapper">
                <span class="cart-item-price">${SITE.currency}${price.toFixed(2)}</span>
              </div>
              <div class="cart-item-actions">
                <div class="cart-quantity-selector">
                  <button class="cart-qty-btn minus" data-action="decrease" data-index="${i}" aria-label="Decrease quantity">&minus;</button>
                  <span class="cart-qty-value">${qty}</span>
                  <button class="cart-qty-btn plus" data-action="increase" data-index="${i}" aria-label="Increase quantity">+</button>
                </div>
                <button class="cart-item-remove-btn" data-action="remove" data-index="${i}">Remove</button>
              </div>
            </div>
          </div>
        </div>
      `;
    }).join('');

    const totalDiscount = originalSubtotal > currentSubtotal ? originalSubtotal - currentSubtotal : 0;
    const finalSubtotal = originalSubtotal > currentSubtotal ? originalSubtotal : currentSubtotal;
    const total = currentSubtotal;

    if (footer) {
      footer.innerHTML = `
        <div class="cart-summary-breakdown">
          <div class="cart-summary-row">
            <span>Subtotal</span>
            <span>${SITE.currency}${finalSubtotal.toFixed(2)}</span>
          </div>
          ${totalDiscount > 0 ? `
            <div class="cart-summary-row discount-row">
              <span>Discount</span>
              <span class="discount-amount">-${SITE.currency}${totalDiscount.toFixed(2)}</span>
            </div>
          ` : ''}
          <div class="cart-summary-row shipping-row">
            <span>Shipping</span>
            <span class="shipping-text">Calculated at checkout</span>
          </div>
          <div class="cart-summary-divider"></div>
          <div class="cart-summary-total">
            <span>Total</span>
            <span class="total-amount">${SITE.currency}${total.toFixed(2)}</span>
          </div>
        </div>
        <button class="btn btn-primary btn-lg cart-checkout-btn" id="checkout-btn">Proceed to Checkout</button>
        <button class="cart-continue-shopping" id="cart-drawer-continue">Continue Shopping</button>
      `;
    }

    body.querySelectorAll('.cart-qty-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        const action = btn.dataset.action;
        const currentQty = state.cart[idx].quantity || 1;
        if (action === 'increase') {
          updateCartQuantity(idx, currentQty + 1);
        } else if (action === 'decrease') {
          updateCartQuantity(idx, currentQty - 1);
        }
      });
    });

    body.querySelectorAll('.cart-item-remove-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const idx = parseInt(btn.dataset.index, 10);
        removeFromCart(idx);
      });
    });

    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
      checkoutBtn.addEventListener('click', () => {
        window.location.href = '/checkout';
      });
    }

    const continueBtn = document.getElementById('cart-drawer-continue');
    if (continueBtn) {
      continueBtn.addEventListener('click', closeCart);
    }
  }

  function toggleCart() {
    const drawer = document.getElementById('cart-drawer');
    const isOpen = drawer.classList.contains('open');
    if (isOpen) {
      closeCart();
    } else {
      openCart();
    }
  }

  function openCart() {
    const drawer = document.getElementById('cart-drawer');
    const overlay = document.getElementById('cart-drawer-overlay');
    closeWishlist();
    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    renderCartDrawer();
  }

  function closeCart() {
    const drawer = document.getElementById('cart-drawer');
    const overlay = document.getElementById('cart-drawer-overlay');
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  /* ══════════════════════════════════════════════════════════
     QUICK VIEW MODAL
     ══════════════════════════════════════════════════════════ */
  function openQuickView(productId) {
    const product = PRODUCTS.find(p => p.id === productId);
    if (!product) return;

    state.selectedModalProduct = product;
    state.selectedSize = product.sizes.length > 0 ? product.sizes[0] : null;

    const modal = document.getElementById('quick-view-modal');
    const overlay = document.getElementById('modal-overlay');

    document.getElementById('modal-product-img').src = imgUrl(product.image, 640);
    document.getElementById('modal-product-img').alt = product.name;
    document.getElementById('modal-category-tag').textContent = CATEGORY_NAME_MAP[product.category] || product.category;
    modal.querySelector('.modal-title').textContent = product.name;
    modal.querySelector('.modal-subtitle').textContent = product.desc;

    const priceEl = modal.querySelector('.modal-price');
    priceEl.innerHTML = `
      <span class="current">${SITE.currency}${product.price.toFixed(2)}</span>
      ${product.originalPrice ? `<span class="original" style="margin-left: 0.5rem;">${SITE.currency}${product.originalPrice.toFixed(2)}</span>` : ''}
    `;

    const sizesEl = modal.querySelector('.modal-sizes');
    sizesEl.innerHTML = product.sizes.map((s, idx) => `
      <button class="modal-size-btn ${idx === 0 ? 'selected' : ''} ${product.outOfStockSizes.includes(s) ? 'out' : ''}" data-size="${s}" ${product.outOfStockSizes.includes(s) ? 'disabled' : ''}>${s}</button>
    `).join('');

    sizesEl.querySelectorAll('.modal-size-btn:not([disabled])').forEach(btn => {
      btn.addEventListener('click', () => {
        sizesEl.querySelectorAll('.modal-size-btn').forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        state.selectedSize = btn.dataset.size;
      });
    });

    const colorsEl = modal.querySelector('.modal-colors');
    colorsEl.innerHTML = product.colors.map((c, i) => `
      <span class="modal-color-swatch ${i === 0 ? 'selected' : ''}" style="background:${c}"></span>
    `).join('');

    const addBtn = document.getElementById('modal-add-to-cart');
    addBtn.textContent = 'Add to Cart';

    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeQuickView() {
    const overlay = document.getElementById('modal-overlay');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  /* ══════════════════════════════════════════════════════════
     SEARCH & NEWSLETTER
     ══════════════════════════════════════════════════════════ */
  /* ══════════════════════════════════════════════════════════
     SEARCH ENGINE (ECOMMERCE STANDARD)
     ══════════════════════════════════════════════════════════ */
  const SEARCH_MIN_CHARACTERS = 3;
  const RECENT_SEARCHES_KEY = 'bongo_recent_searches';

  function getRecentSearches() {
    try {
      const stored = localStorage.getItem(RECENT_SEARCHES_KEY);
      if (stored) return JSON.parse(stored);
    } catch (e) {
      /* ignore */
    }
    return ['Linen Shirt', 'Nakshi Kantha', 'Jute Bag', 'Brass Vessel'];
  }

  function saveRecentSearches(searches) {
    try {
      localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(searches.slice(0, 6)));
    } catch (e) {
      /* ignore */
    }
  }

  function addRecentSearch(query) {
    if (!query || query.length < SEARCH_MIN_CHARACTERS) return;
    let searches = getRecentSearches();
    searches = searches.filter(s => s.toLowerCase() !== query.toLowerCase());
    searches.unshift(query);
    saveRecentSearches(searches);
  }

  function removeRecentSearch(query) {
    let searches = getRecentSearches();
    searches = searches.filter(s => s.toLowerCase() !== query.toLowerCase());
    saveRecentSearches(searches);
    renderRecentSearches();
  }

  function clearAllRecentSearches() {
    saveRecentSearches([]);
    renderRecentSearches();
  }

  function handleMobileVisualViewport() {
    const overlay = document.getElementById('search-overlay');
    if (!overlay || !overlay.classList.contains('open')) return;

    if (window.innerWidth <= 768) {
      if (window.visualViewport) {
        overlay.style.top = `${window.visualViewport.offsetTop}px`;
        overlay.style.height = `${window.visualViewport.height}px`;
      } else {
        overlay.style.top = '0px';
        overlay.style.height = '100dvh';
      }
      window.scrollTo(0, 0);
    } else {
      overlay.style.top = '';
      overlay.style.height = '';
    }
  }

  if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', handleMobileVisualViewport);
    window.visualViewport.addEventListener('scroll', handleMobileVisualViewport);
  }

  function toggleSearch() {
    const overlay = document.getElementById('search-overlay');
    if (!overlay) return;

    const isOpen = overlay.classList.toggle('open');
    if (isOpen) {
      const input = document.getElementById('search-input');
      if (input) {
        input.value = '';
        toggleClearInputBtn('');
        input.focus();
      }
      renderRecentSearches();
      renderSuggestedProducts();
      handleSearchQuery('');
      document.body.classList.add('search-modal-open');
      document.documentElement.classList.add('search-modal-open');
      document.body.style.overflow = 'hidden';
      handleMobileVisualViewport();
    } else {
      document.body.classList.remove('search-modal-open');
      document.documentElement.classList.remove('search-modal-open');
      document.body.style.overflow = '';
      overlay.style.top = '';
      overlay.style.height = '';
    }
  }

  function toggleClearInputBtn(val) {
    const clearBtn = document.getElementById('search-clear-btn');
    if (!clearBtn) return;
    clearBtn.style.display = val && val.length > 0 ? 'flex' : 'none';
  }

  function renderRecentSearches() {
    const container = document.getElementById('recent-searches-list');
    const section = document.getElementById('recent-searches-section');
    if (!container) return;

    const searches = getRecentSearches();
    if (searches.length === 0) {
      if (section) section.style.display = 'none';
      return;
    }

    if (section) section.style.display = 'flex';
    container.innerHTML = searches.map(q => `
      <span class="search-recent-tag" data-query="${q}">
        <span>${q}</span>
        <button class="remove-recent-btn" data-remove-query="${q}" aria-label="Remove search ${q}">&times;</button>
      </span>
    `).join('');

    container.querySelectorAll('.search-recent-tag').forEach(tag => {
      tag.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-recent-btn')) return;
        const q = tag.dataset.query;
        const input = document.getElementById('search-input');
        if (input) {
          input.value = q;
          toggleClearInputBtn(q);
          handleSearchQuery(q);
        }
      });
    });

    container.querySelectorAll('.remove-recent-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        removeRecentSearch(btn.dataset.removeQuery);
      });
    });
  }

  function renderSuggestedProducts() {
    const container = document.getElementById('search-suggested-list');
    if (!container) return;

    const suggested = PRODUCTS.slice(0, 4);
    container.innerHTML = suggested.map(p => `
      <div class="search-result-item" data-search-product-id="${p.id}">
        <img src="${imgUrl(p.image, 160)}" alt="${p.name}" class="search-result-thumb" width="64" height="64" loading="lazy" decoding="async">
        <div class="search-result-info">
          <div class="search-result-name">${p.name}</div>
          <div class="search-result-meta">${CATEGORY_NAME_MAP[p.category] || p.category} · ${p.brand || SITE.brandName}</div>
        </div>
        <div class="search-result-price">
          <span class="current">${SITE.currency}${p.price.toFixed(2)}</span>
          ${p.originalPrice ? `<span class="original">${SITE.currency}${p.originalPrice.toFixed(2)}</span>` : ''}
        </div>
      </div>
    `).join('');

    bindSearchResultClickEvents(container);
  }

  function bindSearchResultClickEvents(container) {
    container.querySelectorAll('[data-search-product-id]').forEach(item => {
      item.addEventListener('click', () => {
        const id = parseInt(item.dataset.searchProductId);
        const input = document.getElementById('search-input');
        if (input && input.value.trim().length >= SEARCH_MIN_CHARACTERS) {
          addRecentSearch(input.value.trim());
        }
        toggleSearch();
        window.location.href = `${productUrl(id)}`;
      });
    });
  }

  function handleSearchQuery(rawQuery) {
    const q = rawQuery.trim().toLowerCase();
    const defaultState = document.getElementById('search-default-state');
    const resultsState = document.getElementById('search-results-state');
    const noResultsState = document.getElementById('search-no-results-state');
    const countEl = document.getElementById('search-results-count');
    const listEl = document.getElementById('search-results-list');
    const noResultsText = document.getElementById('no-results-text');

    if (q.length < SEARCH_MIN_CHARACTERS) {
      if (defaultState) defaultState.style.display = 'flex';
      if (resultsState) resultsState.style.display = 'none';
      if (noResultsState) noResultsState.style.display = 'none';
      return;
    }

    if (defaultState) defaultState.style.display = 'none';

    const matches = PRODUCTS.filter(p => {
      const catName = (CATEGORY_NAME_MAP[p.category] || '').toLowerCase();
      const brandName = (p.brand || '').toLowerCase();
      return (
        p.name.toLowerCase().includes(q) ||
        p.category.toLowerCase().includes(q) ||
        catName.includes(q) ||
        brandName.includes(q) ||
        p.desc.toLowerCase().includes(q)
      );
    });

    if (matches.length > 0) {
      if (noResultsState) noResultsState.style.display = 'none';
      if (resultsState) resultsState.style.display = 'block';

      if (countEl) {
        countEl.textContent = `${matches.length} result${matches.length === 1 ? '' : 's'} for "${rawQuery.trim()}"`;
      }

      if (listEl) {
        listEl.innerHTML = matches.map(p => `
          <div class="search-result-item" data-search-product-id="${p.id}">
            <img src="${imgUrl(p.image, 160)}" alt="${p.name}" class="search-result-thumb" width="64" height="64" loading="lazy" decoding="async">
            <div class="search-result-info">
              <div class="search-result-name">${p.name}</div>
              <div class="search-result-meta">${CATEGORY_NAME_MAP[p.category] || p.category} · ${p.brand || SITE.brandName}</div>
            </div>
            <div class="search-result-price">
              <span class="current">${SITE.currency}${p.price.toFixed(2)}</span>
              ${p.originalPrice ? `<span class="original">${SITE.currency}${p.originalPrice.toFixed(2)}</span>` : ''}
            </div>
          </div>
        `).join('');

        bindSearchResultClickEvents(listEl);
      }
    } else {
      if (resultsState) resultsState.style.display = 'none';
      if (noResultsState) noResultsState.style.display = 'block';
      if (noResultsText) {
        noResultsText.textContent = `We couldn't find any items matching "${rawQuery.trim()}".`;
      }
    }
  }

  function initSearchSuggestions() {
    const input = document.getElementById('search-input');
    const clearBtn = document.getElementById('search-clear-btn');
    const closeBtn = document.getElementById('search-modal-close');
    const clearRecentBtn = document.getElementById('clear-recent-searches-btn');
    const browseAllBtn = document.getElementById('search-browse-all-btn');

    if (!input) return;

    input.addEventListener('input', (e) => {
      const val = e.target.value;
      toggleClearInputBtn(val);
      handleSearchQuery(val);
    });

    input.addEventListener('keyup', (e) => {
      if (e.key === 'Enter') {
        const q = input.value.trim();
        if (q.length >= SEARCH_MIN_CHARACTERS) {
          addRecentSearch(q);
          if (window.location.pathname.includes('/products')) {
            toggleSearch();
            window.location.search = `?search=${encodeURIComponent(q)}`;
          }
        }
      }
    });

    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        input.value = '';
        toggleClearInputBtn('');
        input.focus();
        handleSearchQuery('');
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', toggleSearch);
    }

    if (clearRecentBtn) {
      clearRecentBtn.addEventListener('click', clearAllRecentSearches);
    }

    if (browseAllBtn) {
      browseAllBtn.addEventListener('click', () => {
        toggleSearch();
      });
    }
  }

  function getMobileNavOverlay() {
    let overlay = document.getElementById('mobile-nav-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.id = 'mobile-nav-overlay';
      overlay.className = 'mobile-nav-overlay';
      const nav = document.getElementById('mobile-nav');
      if (nav && nav.parentNode) {
        nav.parentNode.insertBefore(overlay, nav);
      } else {
        document.body.appendChild(overlay);
      }
      overlay.addEventListener('click', closeMobileNav);
    }
    return overlay;
  }

  function openMobileNav() {
    const nav = document.getElementById('mobile-nav');
    const hamburger = document.getElementById('hamburger');
    const overlay = getMobileNavOverlay();
    if (nav) nav.classList.add('open');
    if (hamburger) hamburger.classList.add('active');
    if (overlay) overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileNav() {
    const nav = document.getElementById('mobile-nav');
    const hamburger = document.getElementById('hamburger');
    const overlay = document.getElementById('mobile-nav-overlay');
    if (nav) nav.classList.remove('open');
    if (hamburger) hamburger.classList.remove('active');
    if (overlay) overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function toggleMobileNav() {
    const nav = document.getElementById('mobile-nav');
    if (nav && nav.classList.contains('open')) {
      closeMobileNav();
    } else {
      openMobileNav();
    }
  }

  function showToast(message, actionText, actionCallback) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const toast = document.createElement('div');
    toast.className = 'toast';

    let actionHtml = '';
    if (typeof actionText === 'string' && actionText.length > 0) {
      actionHtml = ` · <button class="toast-action-btn" type="button">${actionText}</button>`;
    }

    toast.innerHTML = `
      <span class="toast-icon">${ICONS.check}</span>
      <span>${message}${actionHtml}</span>
      <span class="toast-close">&times;</span>
    `;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));
    const timeout = setTimeout(() => dismissToast(toast), 4500);

    const actionBtn = toast.querySelector('.toast-action-btn');
    if (actionBtn && typeof actionCallback === 'function') {
      actionBtn.addEventListener('click', (e) => {
        e.preventDefault();
        clearTimeout(timeout);
        dismissToast(toast);
        actionCallback();
      });
    }

    toast.querySelector('.toast-close').addEventListener('click', () => {
      clearTimeout(timeout);
      dismissToast(toast);
    });
  }

  function dismissToast(toast) {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 400);
  }

  let launchOfferTimerInterval = null;

  function renderLaunchOfferCountdown() {
    const el = document.getElementById('launch-offer-timer');
    const strip = document.getElementById('launch-offer-strip');
    if (!el) return;

    if (launchOfferTimerInterval) {
      clearInterval(launchOfferTimerInterval);
      launchOfferTimerInterval = null;
    }

    // Launch offer end date: October 15, 2026, 23:59:59 AEST (+10:00)
    const offerEndDate = new Date('2026-10-15T23:59:59+10:00').getTime();

    function update() {
      const now = Date.now();
      const diff = offerEndDate - now;

      if (diff <= 0) {
        if (launchOfferTimerInterval) {
          clearInterval(launchOfferTimerInterval);
          launchOfferTimerInterval = null;
        }
        el.innerHTML = '<div class="countdown-expired-msg">Launch offer has concluded. Explore our full permanent collection below.</div>';
        if (strip) {
          strip.classList.add('offer-expired');
        }
        return;
      }

      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const mins = Math.floor((diff / (1000 * 60)) % 60);
      const secs = Math.floor((diff / 1000) % 60);

      el.innerHTML = `
        <div class="countdown-editorial-clock">
          <span class="countdown-clock-header">Limited Time Remaining</span>
          <div class="countdown-clock-row">
            <div class="countdown-slot" aria-label="${days} days remaining">
              <span class="countdown-slot-val">${String(days).padStart(2, '0')}</span>
              <span class="countdown-slot-tag">Days</span>
            </div>
            <span class="countdown-slot-divider" aria-hidden="true">:</span>
            <div class="countdown-slot" aria-label="${hours} hours remaining">
              <span class="countdown-slot-val">${String(hours).padStart(2, '0')}</span>
              <span class="countdown-slot-tag">Hours</span>
            </div>
            <span class="countdown-slot-divider" aria-hidden="true">:</span>
            <div class="countdown-slot" aria-label="${mins} minutes remaining">
              <span class="countdown-slot-val">${String(mins).padStart(2, '0')}</span>
              <span class="countdown-slot-tag">Mins</span>
            </div>
            <span class="countdown-slot-divider" aria-hidden="true">:</span>
            <div class="countdown-slot" aria-label="${secs} seconds remaining">
              <span class="countdown-slot-val">${String(secs).padStart(2, '0')}</span>
              <span class="countdown-slot-tag">Secs</span>
            </div>
          </div>
        </div>
      `;
    }

    update();
    launchOfferTimerInterval = setInterval(update, 1000);
  }

  function initNewsletter() {
    const form = document.getElementById('newsletter-form');
    if (!form) return;

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = form.querySelector('.newsletter-input');
      const email = input.value.trim();

      if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showToast('Please enter a valid email address');
        return;
      }

      api('POST', '/newsletter', { email }).then(res => {
        if (res.ok) {
          showToast(res.data.message || 'Thank you for subscribing to our updates!');
          input.value = '';
        } else {
          showToast(apiMessage(res));
        }
      });
    });
  }

  function initStickyNav() {
    const navbar = document.querySelector('.navbar');
    window.addEventListener('scroll', () => {
      navbar.classList.toggle('scrolled', window.scrollY > 20);
    }, { passive: true });
  }

  function initScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });

    document.querySelectorAll('.fade-up:not(.visible)').forEach(el => observer.observe(el));
  }

  /* ══════════════════════════════════════════════════════════
     INIT ALL SECTIONS
     ══════════════════════════════════════════════════════════ */
  function init() {
    renderNavbar();
    renderNavbarIcons();
    renderBottomNav();
    renderHero();
    renderCategories();
    renderProducts();
    renderCategoryProductSections();
    initFilterTabs();
    renderPreorderPreview();
    renderHomeLivingShowcase();
    renderFooter();

    renderLaunchOfferCountdown();
    initStickyNav();
    initNewsletter();
    initSearchSuggestions();

    // Event bindings
    const hamburger = document.getElementById('hamburger');
    if (hamburger) {
      hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleMobileNav();
      });
    }

    const mobileNavClose = document.getElementById('mobile-nav-close');
    if (mobileNavClose) mobileNavClose.addEventListener('click', closeMobileNav);

    const mobileNavOverlay = getMobileNavOverlay();
    if (mobileNavOverlay) mobileNavOverlay.addEventListener('click', closeMobileNav);

    // Close when clicking outside mobile nav
    document.addEventListener('click', (e) => {
      const nav = document.getElementById('mobile-nav');
      const hamb = document.getElementById('hamburger');
      if (!nav || !nav.classList.contains('open')) return;
      if (!nav.contains(e.target) && (!hamb || !hamb.contains(e.target))) {
        closeMobileNav();
      }
    });

    // Close mobile nav when resizing to desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth > 1024) {
        const nav = document.getElementById('mobile-nav');
        if (nav && nav.classList.contains('open')) {
          closeMobileNav();
        }
      }
    });

    const modalClose = document.getElementById('modal-close');
    if (modalClose) modalClose.addEventListener('click', closeQuickView);

    const quickviewModal = document.getElementById('quickview-modal') || document.getElementById('modal-overlay');
    if (quickviewModal) {
      quickviewModal.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeQuickView();
      });
    }

    const modalAddToCart = document.getElementById('modal-add-to-cart');
    if (modalAddToCart) {
      modalAddToCart.addEventListener('click', () => {
        if (state.selectedModalProduct) {
          addToCart(state.selectedModalProduct.id, state.selectedSize);
        }
      });
    }

    const searchOverlay = document.getElementById('search-overlay');
    if (searchOverlay) {
      searchOverlay.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) toggleSearch();
      });
    }

    const cartDrawerClose = document.getElementById('cart-drawer-close');
    if (cartDrawerClose) cartDrawerClose.addEventListener('click', closeCart);

    const cartDrawerOverlay = document.getElementById('cart-drawer-overlay');
    if (cartDrawerOverlay) cartDrawerOverlay.addEventListener('click', closeCart);

    const wishlistDrawerClose = document.getElementById('wishlist-drawer-close');
    if (wishlistDrawerClose) wishlistDrawerClose.addEventListener('click', closeWishlist);

    const wishlistDrawerOverlay = document.getElementById('wishlist-drawer-overlay');
    if (wishlistDrawerOverlay) wishlistDrawerOverlay.addEventListener('click', closeWishlist);

    initVariantModalEvents();
    handleAuthRedirect();
    updateWishlistCount();
    renderWishlistDrawer();
    updateAllWishlistIcons();

    // Keyboard navigation
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeVariantModal();
        closeQuickView();
        closeCart();
        closeWishlist();
        const searchOverlay = document.getElementById('search-overlay');
        if (searchOverlay && searchOverlay.classList.contains('open')) toggleSearch();
        const mobileNav = document.getElementById('mobile-nav');
        if (mobileNav && mobileNav.classList.contains('open')) closeMobileNav();
      }
    });

    requestAnimationFrame(() => {
      setTimeout(initScrollAnimations, 100);
    });
  }

  // Expose mobile nav and wishlist API globally
  window.openMobileNav = openMobileNav;
  window.closeMobileNav = closeMobileNav;
  window.toggleMobileNav = toggleMobileNav;
  window.openWishlist = openWishlist;
  window.closeWishlist = closeWishlist;
  window.toggleWishlistDrawer = toggleWishlistDrawer;
  window.toggleWishlist = toggleWishlist;
  window.updateWishlistCount = updateWishlistCount;
  window.renderWishlistDrawer = renderWishlistDrawer;
  window.addToCart = addToCart;
  window.openCart = openCart;
  window.closeCart = closeCart;
  window.toggleCart = toggleCart;
  window.renderCartDrawer = renderCartDrawer;
  window.updateCartCount = updateCartCount;
  window.openVariantModal = openVariantModal;
  window.showToast = showToast;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})();
