/* ============================================================
   PRODUCTS.JS — Shaotal Product Listing Page Logic
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
    filteredProducts: [],   // products loaded so far (pages from /catalog/feed)
    total: 0,
  };

  // First page and filter options rendered by the server (later pages: /catalog/feed). Filtering, sorting and paging run
  // on the server with the same rules this script used to apply to the whole embedded catalog.
  let LISTING = null;
  try { LISTING = JSON.parse((document.getElementById('listing-data') || {}).textContent || 'null'); } catch (e) { LISTING = null; }
  let requestSeq = 0;

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
  function feedQuery(page) {
    const q = new URLSearchParams();
    if (listingState.category && listingState.category !== 'all') q.set('category', listingState.category);
    if (listingState.subcategory) q.set('subcategory', listingState.subcategory);
    listingState.brands.forEach(v => q.append('brands[]', v));
    listingState.priceRanges.forEach(v => q.append('prices[]', v));
    listingState.sizes.forEach(v => q.append('sizes[]', v));
    listingState.colors.forEach(v => q.append('colors[]', v));
    listingState.availability.forEach(v => q.append('availability[]', v));
    if (listingState.searchQuery) q.set('search', listingState.searchQuery);
    if (listingState.sort && listingState.sort !== 'featured') q.set('sort', listingState.sort);
    q.set('page', page);
    q.set('perPage', listingState.pageSize);
    return q.toString();
  }

  function fetchPage(page) {
    return fetch('/catalog/feed?' + feedQuery(page), { headers: { Accept: 'application/json' }, credentials: 'omit' })
      .then(r => (r.ok ? r.json() : Promise.reject(r.status)));
  }

  function applyPage(data, append) {
    window.BongoCatalog.add(data.products);
    listingState.filteredProducts = append ? listingState.filteredProducts.concat(data.products) : data.products.slice();
    listingState.total = data.total;
    listingState.hasMore = data.hasMore;
    listingState.currentPage = data.page;
  }

  function applyFiltersAndRender(resetPage = true) {
    if (resetPage) {
      listingState.currentPage = 1;
      listingState.hasMore = true;
    }
    const seq = ++requestSeq;

    // Initial render: the server already rendered page 1 for the URL's filters; use it instead of asking again.
    if (LISTING) {
      const data = LISTING;
      LISTING = null;
      applyPage(data, false);
      updateHeaderInfo();
      renderProductGrid();
      updateMobileBadgeCount();
      return;
    }

    updateHeaderInfo();
    updateMobileBadgeCount();
    fetchPage(1).then(data => {
      if (seq !== requestSeq) return;   // a newer filter change is already on its way
      applyPage(data, false);
      updateHeaderInfo();
      renderProductGrid();
    }).catch(() => {
      if (seq === requestSeq && typeof showToast === 'function') showToast('Could not load products. Please try again.');
    });
  }

  /* ══════════════════════════════════════════════════════════
     3. RENDER PRODUCT GRID & INFINITE SCROLL BATCHES
     ══════════════════════════════════════════════════════════ */
  function renderProductGrid() {
    const gridEl = document.getElementById('listing-products-grid');
    const emptyEl = document.getElementById('listing-empty-state');
    const sentinelEl = document.getElementById('infinite-scroll-sentinel');
    if (!gridEl) return;

    const itemsToDisplay = listingState.filteredProducts;

    if (listingState.filteredProducts.length === 0) {
      gridEl.innerHTML = '';
      if (emptyEl) emptyEl.style.display = 'block';
      if (sentinelEl) sentinelEl.style.display = 'none';
      return;
    }

    if (emptyEl) emptyEl.style.display = 'none';
    if (sentinelEl) sentinelEl.style.display = 'flex';

    gridEl.innerHTML = itemsToDisplay.map((product, i) => createProductCardHTML(product, { eager: i < 4 })).join('');
    bindProductCardEvents(gridEl);

    // Update sentinel loading indicator state
    if (sentinelEl) {
      if (listingState.hasMore) {
        sentinelEl.innerHTML = `<div class="loading-spinner" title="Loading more products..."></div>`;
      } else {
        sentinelEl.innerHTML = `<span class="all-loaded-message">All ${listingState.total} products loaded</span>`;
      }
    }
  }

  function loadMoreProducts() {
    if (listingState.isLoading || !listingState.hasMore) return;

    listingState.isLoading = true;
    const seq = requestSeq;
    fetchPage(listingState.currentPage + 1).then(data => {
      if (seq !== requestSeq) return;   // filters changed meanwhile: that request renders instead
      applyPage(data, true);
      renderProductGrid();
    }).catch(() => { /* keep what is shown; scrolling again retries */ })
      .finally(() => { listingState.isLoading = false; });
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
    document.title = `${catLabel} | ${SITE.brandName}`;
    if (descEl) descEl.textContent = catDesc;
    if (breadcrumbCurrent) breadcrumbCurrent.textContent = catLabel;
    if (countEl) {
      const total = listingState.total;
      countEl.textContent = `Showing ${total} product${total === 1 ? '' : 's'}`;
    }
  }

  /* ══════════════════════════════════════════════════════════
     5. FILTER UI SYNC & EVENT HANDLERS
     ══════════════════════════════════════════════════════════ */
  function renderFilterOptions() {
    const facets = (LISTING && LISTING.facets) || { brands: [], sizes: [], colors: [], hasPreorder: false };
    const esc = str => String(str).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));

    document.querySelectorAll('[data-dyn-options]').forEach(el => {
      const type = el.dataset.dynOptions;
      if (type === 'category') {
        const name = el.dataset.radioName || 'category';
        const options = [{ slug: 'all', name: 'All Products' }]
          .concat(window.BongoCategories.getAll().map(c => ({ slug: c.slug, name: c.name })));
        if (facets.hasPreorder) options.push({ slug: 'preorder', name: 'Pre-order' });
        el.innerHTML = options.map((o, i) => `
          <label class="filter-checkbox-label">
            <input type="radio" name="${name}" value="${esc(o.slug)}" data-filter-category ${i === 0 ? 'checked' : ''}>
            <span>${esc(o.name)}</span>
          </label>`).join('');
      } else if (type === 'brand') {
        const used = new Set(facets.brands);
        el.innerHTML = Array.from(used).sort().map(b => `
          <label class="filter-checkbox-label">
            <input type="checkbox" value="${esc(b)}" data-filter-brand>
            <span>${esc(b)}</span>
          </label>`).join('');
        if (used.size === 0 && el.closest('.filter-group')) el.closest('.filter-group').style.display = 'none';
      } else if (type === 'size') {
        const sizes = facets.sizes.slice();
        el.innerHTML = sizes.map(sz => `<button class="size-pill" data-size="${esc(sz)}">${esc(sz)}</button>`).join('');
        if (sizes.length === 0 && el.closest('.filter-group')) el.closest('.filter-group').style.display = 'none';
      } else if (type === 'color') {
        const colors = facets.colors.filter(c => /^#[0-9a-f]{3,8}$/i.test(c.hex));
        el.innerHTML = colors.map(c => `<button class="color-swatch-btn" data-color="${esc(c.hex)}" style="background-color:${esc(c.hex)}${/^#f{3,6}$/i.test(c.hex) ? '; border: 1px solid #ccc' : ''}" title="${esc(c.name || c.hex)}"></button>`).join('');
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
