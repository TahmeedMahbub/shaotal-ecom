/* ============================================================
   PRODUCTS.JS — Bongo Curated Product Listing Page Logic
   Supports Desktop Sidebar Filters, Mobile Filter Drawer,
   Dynamic Sorting, URL Parameter Parsing & Infinite Scrolling
   ============================================================ */

(function () {
  'use strict';

  /* ── Listing State ── */
  const listingState = {
    category: 'all',
    brands: [],
    priceRanges: [],
    sizes: [],
    colors: [],
    availability: [],
    sort: 'featured',
    searchQuery: '',
    currentPage: 1,
    pageSize: 8,
    isLoading: false,
    hasMore: true,
    filteredProducts: [],
  };

  const CATEGORY_LABEL_MAP = {
    all: 'All Products',
    fashion: 'Fashion',
    'home-decor': 'Home & Living',
    jute: 'Jute',
    handicrafts: 'Handcrafted',
    lifestyle: 'Gifts',
  };

  const CATEGORY_DESC_MAP = {
    all: 'Thoughtfully selected apparel, home decor, natural jute goods, and artisan handcrafted pieces curated for Australian living.',
    fashion: 'Everyday clothing, relaxed fits, pure flax linen shirts, and modern apparel essentials crafted for comfort and longevity.',
    'home-decor': 'Handcrafted textiles, Nakshi Kantha cushions, earthenware ceramics, and decorative accents for modern spaces.',
    jute: 'Practical and elegant accessories, tote bags, and storage baskets crafted from natural golden fiber.',
    handicrafts: 'Heirloom weaves, hammered brassware, and distinctive artisanal collectibles by master craftspeople.',
    lifestyle: 'Thoughtful full-grain leather journals, desk sets, and unique gift collections wrapped with care.',
  };

  /* ══════════════════════════════════════════════════════════
     1. INITIALIZATION & URL QUERY PARSING
     ══════════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', () => {
    const gridEl = document.getElementById('listing-products-grid');
    if (!gridEl) return;

    parseURLParams();
    bindFilterEvents();
    bindSortEvents();
    bindMobileDrawerEvents();
    setupInfiniteScroll();

    applyFiltersAndRender(true);
  });

  function parseURLParams() {
    const params = new URLSearchParams(window.location.search);
    const cat = params.get('category');
    const brand = params.get('brand');
    const search = params.get('search');
    const sort = params.get('sort');

    if (cat && (CATEGORY_LABEL_MAP[cat] || cat === 'all')) {
      listingState.category = cat;
    }
    if (brand) {
      listingState.brands = [brand];
    }
    if (search) {
      listingState.searchQuery = search.toLowerCase();
    }
    if (sort) {
      listingState.sort = sort;
      const sortSelect = document.getElementById('sort-select');
      const mobileSortSelect = document.getElementById('mobile-sort-select');
      if (sortSelect) sortSelect.value = sort;
      if (mobileSortSelect) mobileSortSelect.value = sort;
    }

    syncFilterUIState();
  }

  /* ══════════════════════════════════════════════════════════
     2. FILTER & SORT LOGIC
     ══════════════════════════════════════════════════════════ */
  function applyFiltersAndRender(resetPage = true) {
    if (resetPage) {
      listingState.currentPage = 1;
      listingState.hasMore = true;
    }

    const sourceProducts = window.BongoProducts ? window.BongoProducts.getAll() : PRODUCTS;
    let result = sourceProducts.filter(p => !p.isDisabled);

    // Category Filter
    if (listingState.category !== 'all') {
      result = result.filter(p => p.category === listingState.category);
    }

    // Brand Filter
    if (listingState.brands.length > 0) {
      result = result.filter(p => listingState.brands.includes(p.brand));
    }

    // Price Range Filter
    if (listingState.priceRanges.length > 0) {
      result = result.filter(p => {
        const price = parseFloat(p.price) || 0;
        return listingState.priceRanges.some(range => {
          if (range === 'under-30') return price < 30;
          if (range === '30-40') return price >= 30 && price <= 40;
          if (range === '40-50') return price > 40 && price <= 50;
          if (range === '50-above') return price > 50;
          return true;
        });
      });
    }

    // Size Filter
    if (listingState.sizes.length > 0) {
      result = result.filter(p => p.sizes && p.sizes.some(s => listingState.sizes.includes(s)));
    }

    // Color Filter
    if (listingState.colors.length > 0) {
      result = result.filter(p => p.colors && p.colors.some(c => listingState.colors.includes(c)));
    }

    // Availability Filter
    if (listingState.availability.length > 0) {
      result = result.filter(p => listingState.availability.includes(p.availability));
    }

    // Search Query Filter
    if (listingState.searchQuery) {
      result = result.filter(p =>
        (p.name || '').toLowerCase().includes(listingState.searchQuery) ||
        (p.desc || '').toLowerCase().includes(listingState.searchQuery) ||
        (p.category || '').toLowerCase().includes(listingState.searchQuery)
      );
    }

    // Sorting
    if (listingState.sort === 'price-low') {
      result.sort((a, b) => (parseFloat(a.price) || 0) - (parseFloat(b.price) || 0));
    } else if (listingState.sort === 'price-high') {
      result.sort((a, b) => (parseFloat(b.price) || 0) - (parseFloat(a.price) || 0));
    } else if (listingState.sort === 'newest') {
      result.sort((a, b) => (b.badge === 'new' ? 1 : 0) - (a.badge === 'new' ? 1 : 0) || b.id - a.id);
    }

    listingState.filteredProducts = result;

    updateHeaderInfo();
    renderProductGrid();
    updateMobileBadgeCount();
  }

  /* ══════════════════════════════════════════════════════════
     3. RENDER PRODUCT GRID & INFINITE SCROLL BATCHES
     ══════════════════════════════════════════════════════════ */
  function renderProductGrid() {
    const gridEl = document.getElementById('listing-products-grid');
    const emptyEl = document.getElementById('listing-empty-state');
    const sentinelEl = document.getElementById('infinite-scroll-sentinel');
    if (!gridEl) return;

    const visibleItemsCount = listingState.currentPage * listingState.pageSize;
    const itemsToDisplay = listingState.filteredProducts.slice(0, visibleItemsCount);

    listingState.hasMore = visibleItemsCount < listingState.filteredProducts.length;

    if (listingState.filteredProducts.length === 0) {
      gridEl.innerHTML = '';
      if (emptyEl) emptyEl.style.display = 'block';
      if (sentinelEl) sentinelEl.style.display = 'none';
      return;
    }

    if (emptyEl) emptyEl.style.display = 'none';
    if (sentinelEl) sentinelEl.style.display = 'flex';

    gridEl.innerHTML = itemsToDisplay.map(product => createProductCardHTML(product)).join('');
    bindProductCardEvents(gridEl);

    // Update sentinel loading indicator state
    if (sentinelEl) {
      if (listingState.hasMore) {
        sentinelEl.innerHTML = `<div class="loading-spinner" title="Loading more products..."></div>`;
      } else {
        sentinelEl.innerHTML = `<span class="all-loaded-message">All ${listingState.filteredProducts.length} products loaded</span>`;
      }
    }
  }

  function loadMoreProducts() {
    if (listingState.isLoading || !listingState.hasMore) return;

    listingState.isLoading = true;
    setTimeout(() => {
      listingState.currentPage++;
      renderProductGrid();
      listingState.isLoading = false;
    }, 350);
  }

  function setupInfiniteScroll() {
    const sentinelEl = document.getElementById('infinite-scroll-sentinel');
    if (!sentinelEl || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting && listingState.hasMore && !listingState.isLoading) {
          loadMoreProducts();
        }
      });
    }, { rootMargin: '200px' });

    observer.observe(sentinelEl);
  }

  /* ══════════════════════════════════════════════════════════
     4. HEADER & BREADCRUMB UPDATE
     ══════════════════════════════════════════════════════════ */
  function updateHeaderInfo() {
    const titleEl = document.getElementById('listing-title');
    const descEl = document.getElementById('listing-desc');
    const countEl = document.getElementById('listing-count');
    const breadcrumbCurrent = document.getElementById('breadcrumb-current');

    const catLabel = CATEGORY_LABEL_MAP[listingState.category] || 'All Products';
    const catDesc = CATEGORY_DESC_MAP[listingState.category] || CATEGORY_DESC_MAP.all;

    if (titleEl) titleEl.textContent = catLabel;
    if (descEl) descEl.textContent = catDesc;
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = catLabel;
    if (countEl) {
      const total = listingState.filteredProducts.length;
      countEl.textContent = `Showing ${total} product${total === 1 ? '' : 's'}`;
    }
  }

  /* ══════════════════════════════════════════════════════════
     5. FILTER UI SYNC & EVENT HANDLERS
     ══════════════════════════════════════════════════════════ */
  function bindFilterEvents() {
    // Category radios / links
    document.querySelectorAll('[data-filter-category]').forEach(input => {
      input.addEventListener('change', (e) => {
        listingState.category = e.target.value;
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Brand checkboxes
    document.querySelectorAll('[data-filter-brand]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const val = checkbox.value;
        if (checkbox.checked) {
          if (!listingState.brands.includes(val)) listingState.brands.push(val);
        } else {
          listingState.brands = listingState.brands.filter(b => b !== val);
        }
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Price range checkboxes
    document.querySelectorAll('[data-filter-price]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const val = checkbox.value;
        if (checkbox.checked) {
          if (!listingState.priceRanges.includes(val)) listingState.priceRanges.push(val);
        } else {
          listingState.priceRanges = listingState.priceRanges.filter(p => p !== val);
        }
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Size Pills
    document.querySelectorAll('.size-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        const size = pill.dataset.size;
        pill.classList.toggle('active');
        if (pill.classList.contains('active')) {
          if (!listingState.sizes.includes(size)) listingState.sizes.push(size);
        } else {
          listingState.sizes = listingState.sizes.filter(s => s !== size);
        }
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Color Swatches
    document.querySelectorAll('.color-swatch-btn').forEach(swatch => {
      swatch.addEventListener('click', () => {
        const color = swatch.dataset.color;
        swatch.classList.toggle('active');
        if (swatch.classList.contains('active')) {
          if (!listingState.colors.includes(color)) listingState.colors.push(color);
        } else {
          listingState.colors = listingState.colors.filter(c => c !== color);
        }
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Availability Checkboxes
    document.querySelectorAll('[data-filter-availability]').forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const val = checkbox.value;
        if (checkbox.checked) {
          if (!listingState.availability.includes(val)) listingState.availability.push(val);
        } else {
          listingState.availability = listingState.availability.filter(a => a !== val);
        }
        syncFilterUIState();
        applyFiltersAndRender(true);
      });
    });

    // Clear All Buttons
    document.querySelectorAll('.clear-all-filters-btn').forEach(btn => {
      btn.addEventListener('click', clearAllFilters);
    });
  }

  function bindSortEvents() {
    const sortSelect = document.getElementById('sort-select');
    const mobileSortSelect = document.getElementById('mobile-sort-select');

    const handleSortChange = (val) => {
      listingState.sort = val;
      if (sortSelect) sortSelect.value = val;
      if (mobileSortSelect) mobileSortSelect.value = val;
      applyFiltersAndRender(true);
    };

    if (sortSelect) {
      sortSelect.addEventListener('change', (e) => handleSortChange(e.target.value));
    }
    if (mobileSortSelect) {
      mobileSortSelect.addEventListener('change', (e) => handleSortChange(e.target.value));
    }
  }

  function bindMobileDrawerEvents() {
    const triggerBtn = document.getElementById('btn-open-mobile-filter');
    const drawerOverlay = document.getElementById('filter-drawer-overlay');
    const drawer = document.getElementById('filter-drawer');
    const closeBtn = document.getElementById('filter-drawer-close');
    const applyBtn = document.getElementById('btn-apply-mobile-filter');

    const openDrawer = () => {
      if (drawerOverlay) drawerOverlay.classList.add('open');
      if (drawer) drawer.classList.add('open');
      document.body.style.overflow = 'hidden';
    };

    const closeDrawer = () => {
      if (drawerOverlay) drawerOverlay.classList.remove('open');
      if (drawer) drawer.classList.remove('open');
      document.body.style.overflow = '';
    };

    if (triggerBtn) {
      triggerBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        openDrawer();
      });
    }

    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (drawerOverlay) drawerOverlay.addEventListener('click', closeDrawer);
    if (applyBtn) {
      applyBtn.addEventListener('click', () => {
        applyFiltersAndRender(true);
        closeDrawer();
      });
    }

    // Close when clicking outside filter drawer
    document.addEventListener('click', (e) => {
      if (!drawer || !drawer.classList.contains('open')) return;
      if (!drawer.contains(e.target) && (!triggerBtn || !triggerBtn.contains(e.target))) {
        closeDrawer();
      }
    });

    // Close on Escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && drawer && drawer.classList.contains('open')) {
        closeDrawer();
      }
    });

    // Close drawer when resized to desktop
    window.addEventListener('resize', () => {
      if (window.innerWidth > 992 && drawer && drawer.classList.contains('open')) {
        closeDrawer();
      }
    });
  }

  function clearAllFilters() {
    listingState.category = 'all';
    listingState.brands = [];
    listingState.priceRanges = [];
    listingState.sizes = [];
    listingState.colors = [];
    listingState.availability = [];
    listingState.searchQuery = '';

    syncFilterUIState();
    applyFiltersAndRender(true);
  }

  function syncFilterUIState() {
    // Sync category radios
    document.querySelectorAll('[data-filter-category]').forEach(input => {
      input.checked = (input.value === listingState.category);
    });

    // Sync brand checkboxes
    document.querySelectorAll('[data-filter-brand]').forEach(checkbox => {
      checkbox.checked = listingState.brands.includes(checkbox.value);
    });

    // Sync price checkboxes
    document.querySelectorAll('[data-filter-price]').forEach(checkbox => {
      checkbox.checked = listingState.priceRanges.includes(checkbox.value);
    });

    // Sync size pills
    document.querySelectorAll('.size-pill').forEach(pill => {
      if (listingState.sizes.includes(pill.dataset.size)) {
        pill.classList.add('active');
      } else {
        pill.classList.remove('active');
      }
    });

    // Sync color swatches
    document.querySelectorAll('.color-swatch-btn').forEach(swatch => {
      if (listingState.colors.includes(swatch.dataset.color)) {
        swatch.classList.add('active');
      } else {
        swatch.classList.remove('active');
      }
    });

    // Sync availability checkboxes
    document.querySelectorAll('[data-filter-availability]').forEach(checkbox => {
      checkbox.checked = listingState.availability.includes(checkbox.value);
    });
  }

  function updateMobileBadgeCount() {
    const badgeEl = document.getElementById('filter-badge-count');
    if (!badgeEl) return;

    let activeCount = 0;
    if (listingState.category !== 'all') activeCount++;
    activeCount += listingState.brands.length;
    activeCount += listingState.priceRanges.length;
    activeCount += listingState.sizes.length;
    activeCount += listingState.colors.length;
    activeCount += listingState.availability.length;

    if (activeCount > 0) {
      badgeEl.textContent = activeCount;
      badgeEl.style.display = 'inline-flex';
    } else {
      badgeEl.style.display = 'none';
    }
  }
})();
