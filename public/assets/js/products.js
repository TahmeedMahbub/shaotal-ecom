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
    subcategory: '',
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

  const CATEGORY_LABEL_MAP = Object.assign({ all: 'All Products', preorder: 'Pre-order' }, window.CATEGORY_NAME_MAP || {});
  const CATEGORY_DESC_MAP = { all: 'Thoughtfully selected apparel, home decor, natural jute goods, and artisan handcrafted pieces curated for Australian living.' };
  (window.BongoCategories.getAll() || []).forEach(c => { CATEGORY_DESC_MAP[c.slug] = c.desc || ''; });
  CATEGORY_DESC_MAP.preorder = 'Reserve upcoming artisan pieces before they arrive.';

  /* ══════════════════════════════════════════════════════════
     1. INITIALIZATION & URL QUERY PARSING
     ══════════════════════════════════════════════════════════ */
  document.addEventListener('DOMContentLoaded', () => {
    const gridEl = document.getElementById('listing-products-grid');
    if (!gridEl) return;

    renderFilterOptions();
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
    const sub = params.get('subcategory');
    const search = params.get('search');
    const sort = params.get('sort');

    if (cat && (CATEGORY_LABEL_MAP[cat] || cat === 'all')) {
      listingState.category = cat;
    }
    if (sub) listingState.subcategory = sub;
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

    const sourceProducts = window.BongoProducts.getAll();
    let result = sourceProducts.filter(p => !p.isDisabled);

    // Category Filter
    if (listingState.category === 'preorder') {
      result = result.filter(p => p.availability === 'preorder');
    } else if (listingState.category !== 'all') {
      result = result.filter(p => p.category === listingState.category);
    }
    if (listingState.subcategory) {
      result = result.filter(p => p.subcategory === listingState.subcategory);
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
      result = result.filter(p => listingState.availability.includes(p.availability === 'lowstock' ? 'instock' : p.availability));
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
      result.sort((a, b) => b.id - a.id);
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

    const subName = listingState.subcategory ? ((window.BongoSubcategories.getAll().find(x => x.slug === listingState.subcategory) || {}).name || '') : '';
    const catLabel = subName || CATEGORY_LABEL_MAP[listingState.category] || 'All Products';
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
  function renderFilterOptions() {
    const products = window.BongoProducts.getAll();
    const esc = str => String(str).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    document.querySelectorAll('[data-dyn-options]').forEach(el => {
      const type = el.dataset.dynOptions;
      if (type === 'category') {
        const name = el.dataset.radioName || 'category';
        const options = [{ slug: 'all', name: 'All Products' }]
          .concat(window.BongoCategories.getAll().map(c => ({ slug: c.slug, name: c.name })));
        if (products.some(p => p.availability === 'preorder')) options.push({ slug: 'preorder', name: 'Pre-order' });
        el.innerHTML = options.map((o, i) => `
          <label class="filter-checkbox-label">
            <input type="radio" name="${name}" value="${esc(o.slug)}" data-filter-category ${i === 0 ? 'checked' : ''}>
            <span>${esc(o.name)}</span>
          </label>`).join('');
      } else if (type === 'brand') {
        const used = new Set(products.map(p => p.brand).filter(Boolean));
        el.innerHTML = Array.from(used).sort().map(b => `
          <label class="filter-checkbox-label">
            <input type="checkbox" value="${esc(b)}" data-filter-brand>
            <span>${esc(b)}</span>
          </label>`).join('');
        if (used.size === 0 && el.closest('.filter-group')) el.closest('.filter-group').style.display = 'none';
      } else if (type === 'size') {
        const sizes = [];
        products.forEach(p => (p.sizes || []).forEach(sz => { if (!sizes.includes(sz)) sizes.push(sz); }));
        el.innerHTML = sizes.map(sz => `<button class="size-pill" data-size="${esc(sz)}">${esc(sz)}</button>`).join('');
        if (sizes.length === 0 && el.closest('.filter-group')) el.closest('.filter-group').style.display = 'none';
      } else if (type === 'color') {
        const colors = [];
        products.forEach(p => (p.colors || []).forEach(c => { if (/^#[0-9a-f]{3,8}$/i.test(c) && !colors.includes(c)) colors.push(c); }));
        el.innerHTML = colors.map(c => `<button class="color-swatch-btn" data-color="${esc(c)}" style="background-color:${esc(c)}${/^#f{3,6}$/i.test(c) ? '; border: 1px solid #ccc' : ''}" title="${esc((window.COLOR_NAMES || {})[c] || c)}"></button>`).join('');
        if (colors.length === 0 && el.closest('.filter-group')) el.closest('.filter-group').style.display = 'none';
      }
    });
  }

  function bindFilterEvents() {
    // Category radios / links
    document.querySelectorAll('[data-filter-category]').forEach(input => {
      input.addEventListener('change', (e) => {
        listingState.category = e.target.value;
        listingState.subcategory = '';
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
    listingState.subcategory = '';
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
