/* ============================================================
   PRODUCT DETAIL CONTROLLER — Shaotal
   Handles dynamic product details rendering, gallery, zoom,
   color/size pickers, accordions, pre-order, and recently viewed.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Product comes from the route slug (catalog is provided by the server)
  const productSlug = (window.__PAGE__ || {}).slug;
  const allProducts = window.BongoProducts.getAll();
  const product = allProducts.find(p => p.slug === productSlug);
  if (!product) return;

  // Track Recently Viewed in localStorage
  trackRecentlyViewed(product.id);

  // Detail Page Local State
  const detailState = {
    product: product,
    selectedColor: product.colors && product.colors.length > 0 ? product.colors[0] : null,
    selectedSize: getInitialSize(product, product.colors && product.colors.length > 0 ? product.colors[0] : null),
    quantity: 1,
    activeImgIndex: 0,
    images: generateGalleryImages(product, product.colors && product.colors.length > 0 ? product.colors[0] : null),
  };

  // 2. Initialize Component Render
  renderBreadcrumb(product);
  renderProductGallery(detailState);
  renderProductInfo(detailState);
  renderPreorderNotice(product);
  renderAccordions(product);
  renderRelatedProducts(product);
  renderRecentlyViewed(product.id);

  // 3. Setup Interactive Event Listeners
  setupGalleryZoom();
  setupQuantityPicker(detailState);
  setupCtaButtons(detailState);
  setupSizeGuideModal();
  setupAccordionToggle();
});

/* ── Helper Functions ── */

function fixImgPath(src) {
  return src || window.PLACEHOLDER_IMG;
}

function sizeUnavailable(product, color, size) {
  if (!product.hasVariants) return false;
  const matches = (product.variants || []).filter(v => v.size === size && (v.color === null || v.color === color));
  return matches.length === 0 || matches.every(v => v.stock <= 0);
}

function getInitialSize(product, color) {
  if (!product.sizes || product.sizes.length === 0) return null;
  const available = product.sizes.filter(s => !sizeUnavailable(product, color, s));
  return available.length > 0 ? available[0] : product.sizes[0];
}

function generateGalleryImages(product, color) {
  let list = [];
  const byColor = (product.colorImages || {})[color];
  if (byColor && byColor.length) {
    const colourSpecific = new Set([].concat(...Object.values(product.colorImages || {})));
    list = (product.images || []).filter(u => !colourSpecific.has(u)).concat(byColor);
  } else if (Array.isArray(product.images) && product.images.length > 0) {
    list = product.images.slice();
  }
  return list.length ? list : [window.PLACEHOLDER_IMG];
}

const COLOR_NAME_MAP = window.COLOR_NAMES || {};

/* ── Render Functions ── */

function renderBreadcrumb(product) {
  const catLink = document.getElementById('detail-breadcrumb-category-link');
  const titleCurrent = document.getElementById('detail-breadcrumb-title');

  const catName = CATEGORY_NAME_MAP[product.category] || 'Shop';
  if (catLink) {
    catLink.textContent = catName;
    catLink.href = `/products?category=${product.category}`;
  }
  if (titleCurrent) {
    titleCurrent.textContent = product.name;
  }
}

function renderProductGallery(detailState) {
  const mainImg = document.getElementById('main-product-image');
  const badgeWrap = document.getElementById('detail-badge-wrap');
  const thumbnailsContainer = document.getElementById('detail-thumbnails-list');

  if (mainImg) {
    const imgUrl = fixImgPath(detailState.images[detailState.activeImgIndex]);
    mainImg.fetchPriority = 'high';
    mainImg.src = window.imgUrl(imgUrl, 800);
    const srcset = window.imgSrcset(imgUrl, [480, 800, 1200]);
    if (srcset) { mainImg.srcset = srcset; mainImg.sizes = '(max-width: 768px) 100vw, 430px'; } else { mainImg.removeAttribute('srcset'); }
    mainImg.alt = detailState.product.name || 'Product Image';
    mainImg.onerror = function() { this.onerror = null; this.src = window.PLACEHOLDER_IMG; };
    mainImg.style.transform = 'scale(1)';
    mainImg.style.transformOrigin = 'center center';
  }

  if (badgeWrap) {
    const badgeText = BADGE_MAP[detailState.product.badge];
    if (badgeText) {
      badgeWrap.innerHTML = `<span class="badge ${detailState.product.badge}">${badgeText}</span>`;
      badgeWrap.style.display = 'block';
    } else {
      badgeWrap.style.display = 'none';
    }
  }

  if (thumbnailsContainer) {
    thumbnailsContainer.innerHTML = detailState.images.map((imgUrl, idx) => `
      <div class="gallery-thumb-item ${idx === detailState.activeImgIndex ? 'active' : ''}" data-thumb-index="${idx}">
        <img src="${window.imgUrl(fixImgPath(imgUrl), 160)}" alt="Product View ${idx + 1}" width="84" height="84" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
      </div>
    `).join('');

    thumbnailsContainer.querySelectorAll('.gallery-thumb-item').forEach(item => {
      item.addEventListener('click', () => {
        const idx = parseInt(item.dataset.thumbIndex);
        detailState.activeImgIndex = idx;
        renderProductGallery(detailState);
      });
    });
  }

  updateWishlistTriggerState(detailState.product.id);
}

function updateWishlistTriggerState(productId) {
  const btn = document.getElementById('detail-wishlist-trigger');
  if (!btn) return;
  const wishlist = getStoredWishlist();
  const inWishlist = wishlist.some(item => (typeof item === 'object' ? (item.id || item.productId) : item) === productId);
  const heartIcon = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg>`;
  const heartFillIcon = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M11.645 20.91l-.007-.003-.022-.012a15.247 15.247 0 01-.383-.218 25.18 25.18 0 01-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0112 5.052 5.5 5.5 0 0116.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 01-4.244 3.17 15.247 15.247 0 01-.383.219l-.022.012-.007.004-.003.001a.752.752 0 01-.704 0l-.003-.001z"/></svg>`;

  if (inWishlist) {
    btn.classList.add('active');
    btn.innerHTML = heartFillIcon;
  } else {
    btn.classList.remove('active');
    btn.innerHTML = heartIcon;
  }
}

function getStoredWishlist() {
  try {
    return JSON.parse(localStorage.getItem('bongo_wishlist')) || [];
  } catch (e) {
    return [];
  }
}

function renderProductInfo(detailState) {
  const product = detailState.product;

  // Eyebrow & Title
  const eyebrowEl = document.getElementById('detail-eyebrow');
  const titleEl = document.getElementById('detail-title');
  const descEl = document.getElementById('detail-short-desc');

  const catName = CATEGORY_NAME_MAP[product.category] || product.category || 'General';
  const brandName = product.brand || SITE.brandName;

  if (eyebrowEl) eyebrowEl.textContent = `${catName} · ${brandName}`;
  if (titleEl) titleEl.textContent = product.name || 'Product Details';
  if (descEl) descEl.textContent = product.desc || '';

  renderPrice(detailState);

  const ratingRow = document.getElementById('detail-rating-score');
  if (ratingRow) {
    const row = ratingRow.parentElement;
    if (product.reviewCount > 0) {
      ratingRow.textContent = `${product.rating.toFixed(1)} / 5.0`;
      document.getElementById('detail-rating-count').textContent = `(${product.reviewCount} customer review${product.reviewCount === 1 ? '' : 's'})`;
    } else if (row) {
      row.style.display = 'none';
    }
  }

  // Color Swatches
  renderColorSwatches(detailState);

  // Size Pills
  renderSizePills(detailState);

  // Availability Indicator
  renderAvailabilityBadge(product);
}

function renderColorSwatches(detailState) {
  const swatchesContainer = document.getElementById('detail-color-swatches');
  const colorLabelEl = document.getElementById('selected-color-name');

  if (!swatchesContainer) return;

  if (colorLabelEl && detailState.selectedColor) {
    colorLabelEl.textContent = COLOR_NAME_MAP[detailState.selectedColor] || detailState.selectedColor;
  }

  if (detailState.product.colors && detailState.product.colors.length > 0) {
    swatchesContainer.innerHTML = detailState.product.colors.map(colorHex => `
      <button class="detail-color-swatch-item ${colorHex === detailState.selectedColor ? 'active' : ''}" 
              style="background-color: ${colorHex}" 
              data-color-hex="${colorHex}" 
              aria-label="Select color ${COLOR_NAME_MAP[colorHex] || colorHex}">
      </button>
    `).join('');

    swatchesContainer.querySelectorAll('.detail-color-swatch-item').forEach(btn => {
      btn.addEventListener('click', () => {
        detailState.selectedColor = btn.dataset.colorHex;
        if (sizeUnavailable(detailState.product, detailState.selectedColor, detailState.selectedSize)) {
          detailState.selectedSize = getInitialSize(detailState.product, detailState.selectedColor);
          renderSizePills(detailState);
        }
        detailState.images = generateGalleryImages(detailState.product, detailState.selectedColor);
        detailState.activeImgIndex = 0;
        renderColorSwatches(detailState);
        renderProductGallery(detailState);
        renderPrice(detailState);
      });
    });
  } else {
    document.getElementById('detail-color-group').style.display = 'none';
  }
}

function renderSizePills(detailState) {
  const sizesContainer = document.getElementById('detail-size-pills');
  const sizeLabelEl = document.getElementById('selected-size-name');

  if (!sizesContainer) return;

  if (sizeLabelEl && detailState.selectedSize) {
    sizeLabelEl.textContent = detailState.selectedSize;
  }

  const sizes = detailState.product.sizes || [];

  if (sizes.length > 0) {
    sizesContainer.innerHTML = sizes.map(sizeStr => {
      const isDisabled = sizeUnavailable(detailState.product, detailState.selectedColor, sizeStr);
      const isActive = sizeStr === detailState.selectedSize;
      return `
        <button class="detail-size-pill ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}" 
                data-size="${sizeStr}" 
                ${isDisabled ? 'disabled' : ''}>
          ${sizeStr}
        </button>
      `;
    }).join('');

    sizesContainer.querySelectorAll('.detail-size-pill:not(.disabled)').forEach(btn => {
      btn.addEventListener('click', () => {
        detailState.selectedSize = btn.dataset.size;
        renderSizePills(detailState);
        renderPrice(detailState);
      });
    });
  } else {
    document.getElementById('detail-size-group').style.display = 'none';
  }
}

function renderAvailabilityBadge(product) {
  const badgeEl = document.getElementById('detail-availability-wrap');
  if (!badgeEl) return;

  let dotClass = 'instock';
  let statusText = 'In Stock — Ready to dispatch from Sydney';

  if (product.availability === 'preorder') {
    dotClass = 'preorder';
    statusText = product.releaseDate
      ? `Pre-order Item — Expected dispatch ${new Date(product.releaseDate + 'T00:00:00').toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric' })}`
      : 'Pre-order Item';
  } else if (product.soldOut) {
    dotClass = 'lowstock';
    statusText = 'Currently unavailable';
  } else if (product.availability === 'lowstock') {
    dotClass = 'lowstock';
    statusText = `Low Stock — Only ${product.stockQty} left`;
  }

  badgeEl.innerHTML = `
    <span class="availability-status-dot ${dotClass}"></span>
    <span>${statusText}</span>
  `;

  if (product.soldOut) {
    ['detail-add-to-cart-btn', 'detail-buy-now-btn'].forEach(id => {
      const btn = document.getElementById(id);
      if (btn) { btn.disabled = true; btn.textContent = 'Sold Out'; }
    });
  }
}

function renderPreorderNotice(product) {
  const container = document.getElementById('detail-preorder-notice');
  if (!container) return;

  if (product.availability === 'preorder') {
    const when = product.releaseDate
      ? ` Expected dispatch: <span class="preorder-date-highlight">${new Date(product.releaseDate + 'T00:00:00').toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric' })}</span>.`
      : '';
    container.innerHTML = `
      <div class="preorder-notice-header">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>PRE-ORDER ITEM</span>
      </div>
      <div class="preorder-notice-body">
        This item is available to pre-order.${when}
      </div>
    `;
    container.style.display = 'flex';
  } else {
    container.style.display = 'none';
  }
}

function escapeHtml(str) {
  return String(str).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function renderAccordions(product) {
  const accordionGroup = document.getElementById('detail-accordion-group');
  if (!accordionGroup) return;

  const specs = product.specs || {};
  const para = text => `<p>${escapeHtml(text).replace(/\n/g, '<br>')}</p>`;
  // The full description is authored in the admin app, which may save plain text or
  // rich-text HTML (bold, lists, links, paragraphs). Render real markup when present;
  // fall back to the escaped plain-text treatment for legacy/plain descriptions so
  // nothing that used to render safely starts rendering raw user-typed text as HTML.
  const isHtml = text => /<[a-z][\s\S]*>/i.test(text);
  const richText = text => (isHtml(text) ? text : para(text));
  const accordionsData = [];

  const description = product.fullDesc || product.desc;
  if (description) accordionsData.push({ title: 'Description & Craft Story', content: richText(description) });

  const materials = [specs.material, specs.fabric, specs.pattern ? `Pattern: ${specs.pattern}` : ''].filter(Boolean).join(' · ');
  if (materials) accordionsData.push({ title: 'Materials & Fabric', content: para(materials) });

  const fit = [specs.dimensions ? `Dimensions: ${specs.dimensions}` : '', specs.fit ? `Fit: ${specs.fit}` : ''].filter(Boolean).join('\n');
  if (fit) accordionsData.push({ title: 'Dimensions & Fit', content: para(fit) });

  if (specs.care) accordionsData.push({ title: 'Care Instructions', content: para(specs.care) });

  if (accordionsData.length === 0) {
    accordionGroup.closest('.product-accordions-container')?.style.setProperty('display', 'none');
    return;
  }

  accordionGroup.innerHTML = accordionsData.map((item, idx) => `
    <div class="detail-accordion-item ${idx === 0 ? 'open' : ''}">
      <button class="accordion-header-btn">
        <span>${item.title}</span>
        <svg class="accordion-icon-chevron" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
      </button>
      <div class="accordion-content-panel" style="${idx === 0 ? 'max-height: 500px;' : ''}">
        <div class="accordion-inner-body">
          ${item.content}
        </div>
      </div>
    </div>
  `).join('');
}

function renderRelatedProducts(currentProduct) {
  const container = document.getElementById('related-products-grid');
  if (!container) return;

  const matches = PRODUCTS.filter(p => p.id !== currentProduct.id && (p.category === currentProduct.category || p.brand === currentProduct.brand)).slice(0, 4);

  if (matches.length < 4) {
    const fillers = PRODUCTS.filter(p => p.id !== currentProduct.id && !matches.includes(p)).slice(0, 4 - matches.length);
    matches.push(...fillers);
  }

  container.innerHTML = matches.map(p => createProductCardHTML(p)).join('');
  bindProductCardEvents(container);
}

function renderRecentlyViewed(currentId) {
  const container = document.getElementById('recently-viewed-grid');
  const section = document.getElementById('recently-viewed-section');
  if (!container || !section) return;

  try {
    const list = JSON.parse(localStorage.getItem('bongo_recently_viewed')) || [];
    const filteredIds = list.filter(id => id !== currentId).slice(0, 4);

    if (filteredIds.length === 0) {
      section.style.display = 'none';
      return;
    }

    const items = filteredIds.map(id => PRODUCTS.find(p => p.id === id)).filter(Boolean);

    if (items.length === 0) {
      section.style.display = 'none';
      return;
    }

    section.style.display = 'block';
    container.innerHTML = items.map(p => createProductCardHTML(p)).join('');
    bindProductCardEvents(container);
  } catch (e) {
    section.style.display = 'none';
  }
}

function trackRecentlyViewed(productId) {
  try {
    let list = JSON.parse(localStorage.getItem('bongo_recently_viewed')) || [];
    list = list.filter(id => id !== productId);
    list.unshift(productId);
    localStorage.setItem('bongo_recently_viewed', JSON.stringify(list.slice(0, 8)));
  } catch (e) {
    // LocalStorage fallback
  }
}

/* ── Interactive Setup ── */

function setupGalleryZoom() {
  const viewport = document.getElementById('main-product-image-container');
  const img = document.getElementById('main-product-image');

  if (!viewport || !img) return;

  const isHoverable = () => window.matchMedia('(hover: hover) and (pointer: fine)').matches && window.innerWidth > 768;

  viewport.addEventListener('mouseenter', () => {
    if (!isHoverable()) return;
    img.style.transition = 'transform 0.15s ease-out, transform-origin 0.1s ease-out';
    img.style.transform = 'scale(1.8)';
  });

  viewport.addEventListener('mousemove', (e) => {
    if (!isHoverable()) return;
    const rect = viewport.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;

    const clampedX = Math.max(0, Math.min(100, x));
    const clampedY = Math.max(0, Math.min(100, y));

    img.style.transformOrigin = `${clampedX}% ${clampedY}%`;
  });

  viewport.addEventListener('mouseleave', () => {
    if (!isHoverable()) return;
    img.style.transition = 'transform 0.25s ease-out';
    img.style.transform = 'scale(1)';
    img.style.transformOrigin = 'center center';
  });
}

function setupQuantityPicker(detailState) {
  const minusBtn = document.getElementById('qty-minus');
  const plusBtn = document.getElementById('qty-plus');
  const inputEl = document.getElementById('detail-qty-input');

  if (!minusBtn || !plusBtn || !inputEl) return;

  minusBtn.addEventListener('click', () => {
    if (detailState.quantity > 1) {
      detailState.quantity--;
      inputEl.value = detailState.quantity;
    }
  });

  plusBtn.addEventListener('click', () => {
    if (detailState.quantity < 99) {
      detailState.quantity++;
      inputEl.value = detailState.quantity;
    }
  });

  inputEl.addEventListener('change', () => {
    let val = parseInt(inputEl.value);
    if (isNaN(val) || val < 1) val = 1;
    detailState.quantity = val;
    inputEl.value = val;
  });
}

function setupCtaButtons(detailState) {
  const addToCartBtn = document.getElementById('detail-add-to-cart-btn');
  const buyNowBtn = document.getElementById('detail-buy-now-btn');
  const wishlistBtn = document.getElementById('detail-wishlist-trigger');

  if (addToCartBtn) {
    addToCartBtn.addEventListener('click', () => {
      executeAddToCart(detailState);
    });
  }

  if (buyNowBtn) {
    buyNowBtn.addEventListener('click', () => {
      if (detailState.product.soldOut) return;
      addToCart(detailState.product.id, detailState.selectedColor, detailState.selectedSize, detailState.quantity, false);
      window.location.href = '/checkout';
    });
  }

  if (wishlistBtn) {
    wishlistBtn.addEventListener('click', () => {
      toggleWishlistItem(detailState.product.id);
      updateWishlistTriggerState(detailState.product.id);
    });
  }
}

function executeAddToCart(detailState) {
  if (typeof addToCart === 'function') {
    addToCart(detailState.product.id, detailState.selectedColor, detailState.selectedSize, detailState.quantity);
  } else {
    // Fallback Cart Dispatch
    let cart = JSON.parse(localStorage.getItem('bongo_cart')) || [];
    const item = {
      id: detailState.product.id,
      color: detailState.selectedColor,
      size: detailState.selectedSize,
      quantity: detailState.quantity,
    };
    cart.push(item);
    localStorage.setItem('bongo_cart', JSON.stringify(cart));
    if (typeof showToast === 'function') {
      showToast(`Added ${detailState.quantity}x ${detailState.product.name} to cart`);
    }
  }
}

function toggleWishlistItem(id) {
  if (typeof window.toggleWishlist === 'function') {
    window.toggleWishlist(id);
    return;
  }
  let wishlist = getStoredWishlist();
  if (wishlist.includes(id)) {
    wishlist = wishlist.filter(x => x !== id);
    if (typeof showToast === 'function') showToast('Removed from Wishlist');
  } else {
    wishlist.push(id);
    if (typeof showToast === 'function') showToast('Added to Wishlist');
  }
  localStorage.setItem('bongo_wishlist', JSON.stringify(wishlist));
  if (typeof window.updateWishlistCount === 'function') window.updateWishlistCount();
  if (typeof window.renderWishlistDrawer === 'function') window.renderWishlistDrawer();
}

function setupAccordionToggle() {
  document.querySelectorAll('.detail-accordion-item').forEach(item => {
    const btn = item.querySelector('.accordion-header-btn');
    const panel = item.querySelector('.accordion-content-panel');
    if (!btn || !panel) return;

    btn.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      if (isOpen) {
        item.classList.remove('open');
        panel.style.maxHeight = '0';
      } else {
        item.classList.add('open');
        panel.style.maxHeight = `${panel.scrollHeight + 40}px`;
      }
    });
  });
}

function setupSizeGuideModal() {
  const openBtn = document.getElementById('btn-open-size-guide');
  const modalOverlay = document.getElementById('size-guide-modal');
  const closeBtn = document.getElementById('size-guide-close');
  const product = (window.BongoProducts.getAll().find(p => p.slug === (window.__PAGE__ || {}).slug)) || {};

  if (!openBtn || !modalOverlay) return;
  if (!product.sizeGuideKey) {
    openBtn.style.display = 'none';
    return;
  }

  let loaded = false;
  openBtn.addEventListener('click', async () => {
    modalOverlay.classList.add('open');
    if (loaded) return;
    const res = await window.bongoApi('GET', '/size-guides/' + encodeURIComponent(product.sizeGuideKey));
    if (!res.ok) return;
    loaded = true;
    const g = res.data;
    const head = g.attributes.map(a => `<th>${escapeHtml(a.name)}${a.unit ? ` (${escapeHtml(a.unit)})` : ''}</th>`).join('');
    const rows = Object.entries(g.rows).map(([size, vals]) =>
      `<tr><td><strong>${escapeHtml(size)}</strong></td>${g.attributes.map(a => `<td>${vals[a.id] ?? '—'}</td>`).join('')}</tr>`).join('');
    modalOverlay.querySelector('.size-guide-title').textContent = g.title;
    modalOverlay.querySelector('.size-guide-body').innerHTML =
      (g.details ? `<p style="font-size: var(--fs-small); color: var(--color-grey-700)">${escapeHtml(g.details)}</p>` : '') +
      `<div class="size-table-wrapper"><table class="size-guide-table"><thead><tr><th>Size</th>${head}</tr></thead><tbody>${rows}</tbody></table></div>`;
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', () => modalOverlay.classList.remove('open'));
  }

  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) modalOverlay.classList.remove('open');
  });
}


function renderPrice(detailState) {
  const product = detailState.product;
  const offeredPriceEl = document.getElementById('detail-offered-price');
  const originalPriceEl = document.getElementById('detail-original-price');
  const discountPillEl = document.getElementById('detail-discount-pill');

  const numPrice = window.bongoVariantPrice(product, detailState.selectedColor, detailState.selectedSize);
  const numOrigPrice = (product.originalPrice && numPrice === product.price) ? parseFloat(product.originalPrice) : null;

  if (offeredPriceEl) offeredPriceEl.textContent = `${SITE.currency}${numPrice.toFixed(2)}`;

  if (originalPriceEl) {
    if (numOrigPrice && numOrigPrice > numPrice) {
      originalPriceEl.textContent = `${SITE.currency}${numOrigPrice.toFixed(2)}`;
      originalPriceEl.style.display = 'inline';
    } else {
      originalPriceEl.style.display = 'none';
    }
  }

  if (discountPillEl) {
    if (numOrigPrice && numOrigPrice > numPrice) {
      const savings = numOrigPrice - numPrice;
      const pct = Math.round((savings / numOrigPrice) * 100);
      discountPillEl.textContent = `SAVE ${pct}% (${SITE.currency}${savings.toFixed(2)})`;
      discountPillEl.style.display = 'inline-block';
    } else {
      discountPillEl.style.display = 'none';
    }
  }
}
