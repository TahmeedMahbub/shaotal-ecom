/* ============================================================
   PRODUCT DETAIL CONTROLLER — Bongo Curated
   Handles dynamic product details rendering, gallery, zoom,
   color/size pickers, accordions, pre-order, and recently viewed.
   ============================================================ */

document.addEventListener('DOMContentLoaded', () => {
  // 1. Get Product ID / Slug from URL
  const urlParams = new URLSearchParams(window.location.search);
  let productId = parseInt(urlParams.get('id'));
  const productSlug = urlParams.get('slug');

  const allProducts = window.BongoProducts ? window.BongoProducts.getAll() : (typeof PRODUCTS !== 'undefined' ? PRODUCTS : []);
  if (!productId && productSlug) {
    const foundBySlug = allProducts.find(p => p.slug === productSlug);
    if (foundBySlug) productId = foundBySlug.id;
  }

  // Fallback to active product if invalid
  let product = allProducts.find(p => p.id === productId);
  if (!product) {
    product = allProducts.find(p => !p.isDisabled) || allProducts[0];
  }

  if (!product) return;

  // Track Recently Viewed in localStorage
  trackRecentlyViewed(product.id);

  // Detail Page Local State
  const detailState = {
    product: product,
    selectedColor: product.colors && product.colors.length > 0 ? product.colors[0] : null,
    selectedSize: getInitialSize(product),
    quantity: 1,
    activeImgIndex: 0,
    images: generateGalleryImages(product),
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
  if (!src) return 'assets/images/products/shirt-white.png';
  if (src.startsWith('../../assets/')) return src.replace('../../assets/', 'assets/');
  return src;
}

function getInitialSize(product) {
  if (!product.sizes || product.sizes.length === 0) return null;
  const available = product.sizes.filter(s => !(product.outOfStockSizes || []).includes(s));
  return available.length > 0 ? available[0] : product.sizes[0];
}

function generateGalleryImages(product) {
  let list = [];
  if (Array.isArray(product.images) && product.images.length > 0) {
    list = product.images.map(img => fixImgPath(img));
  } else if (product.image) {
    list = [fixImgPath(product.image)];
  }
  if (list.length === 0) {
    list = ['assets/images/products/shirt-white.png'];
  }
  return list;
}

const COLOR_NAME_MAP = {
  '#5C6B4F': 'Olive Green',
  '#1A1A1A': 'Midnight Black',
  '#F5F0E8': 'Natural Off-White',
  '#FFFFFF': 'Pure White',
  '#E8DDD0': 'Sand Beige',
  '#8BA5B5': 'Sky Blue',
  '#C4A882': 'Warm Khaki',
  '#3A3A3A': 'Dark Slate',
  '#1B2A4A': 'Navy Blue',
  '#D6C2A8': 'Desert Camel',
  '#8C4F3B': 'Terracotta',
  '#384D48': 'Deep Forest',
  '#A06F43': 'Chestnut Tan',
  '#5E3A1C': 'Espresso Brown',
  '#C8A870': 'Antique Gold',
  '#3D342A': 'Dark Amber',
  '#D3B382': 'Honey Oak',
  '#967850': 'Teak Brown',
  '#C5A059': 'Brass Gold',
  '#7D6331': 'Deep Bronze',
  '#6B3E26': 'Leather Brown',
  '#2B1E16': 'Dark Mahogany',
  '#D8D1C5': 'Soft Linen',
  '#6F765F': 'Earthy Sage'
};

/* ── Render Functions ── */

function renderBreadcrumb(product) {
  const catLink = document.getElementById('detail-breadcrumb-category-link');
  const titleCurrent = document.getElementById('detail-breadcrumb-title');

  const catName = CATEGORY_NAME_MAP[product.category] || 'Shop';
  if (catLink) {
    catLink.textContent = catName;
    catLink.href = `products.html?category=${product.category}`;
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
    mainImg.src = imgUrl;
    mainImg.alt = detailState.product.name || 'Product Image';
    mainImg.onerror = function() { this.src = 'assets/images/products/shirt-white.png'; };
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
        <img src="${fixImgPath(imgUrl)}" alt="Product View ${idx + 1}" loading="lazy" onerror="this.src='assets/images/products/shirt-white.png';">
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
  const brandName = product.brand || 'Bongo Curated';

  if (eyebrowEl) eyebrowEl.textContent = `${catName} · ${brandName}`;
  if (titleEl) titleEl.textContent = product.name || 'Product Details';
  if (descEl) descEl.textContent = product.desc || '';

  // Price
  const offeredPriceEl = document.getElementById('detail-offered-price');
  const originalPriceEl = document.getElementById('detail-original-price');
  const discountPillEl = document.getElementById('detail-discount-pill');

  const numPrice = parseFloat(product.price) || 0;
  const numOrigPrice = product.originalPrice ? parseFloat(product.originalPrice) : null;

  if (offeredPriceEl) offeredPriceEl.textContent = `A$${numPrice.toFixed(2)}`;

  if (originalPriceEl) {
    if (numOrigPrice && numOrigPrice > numPrice) {
      originalPriceEl.textContent = `A$${numOrigPrice.toFixed(2)}`;
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
        renderColorSwatches(detailState);
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
  const outOfStock = detailState.product.outOfStockSizes || [];

  if (sizes.length > 0) {
    sizesContainer.innerHTML = sizes.map(sizeStr => {
      const isDisabled = outOfStock.includes(sizeStr);
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

  if (product.badge === 'preorder' || product.availability === 'preorder') {
    dotClass = 'preorder';
    statusText = 'Pre-order Item — Expected Sydney dispatch late October 2026';
  } else if (product.badge === 'lowstock' || product.availability === 'lowstock') {
    dotClass = 'lowstock';
    statusText = 'Low Stock — Only 3 left in Sydney warehouse';
  }

  badgeEl.innerHTML = `
    <span class="availability-status-dot ${dotClass}"></span>
    <span>${statusText}</span>
  `;
}

function renderPreorderNotice(product) {
  const container = document.getElementById('detail-preorder-notice');
  if (!container) return;

  if (product.badge === 'preorder' || product.availability === 'preorder') {
    container.innerHTML = `
      <div class="preorder-notice-header">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>PRE-ORDER ITEM — RESERVED BATCH ALLOCATION</span>
      </div>
      <div class="preorder-notice-body">
        This item is currently handcrafted in small artisan batches by master craftspeople. 
        Securing your pre-order locks in batch priority with guaranteed dispatch from our Sydney warehouse by <span class="preorder-date-highlight">Late October 2026</span>.
        Pay upfront or split into 4 interest-free instalments with Afterpay.
      </div>
    `;
    container.style.display = 'flex';
  } else {
    container.style.display = 'none';
  }
}

function renderAccordions(product) {
  const accordionGroup = document.getElementById('detail-accordion-group');
  if (!accordionGroup) return;

  // Generate customized accordion content based on category
  let fabMat = '100% OEKO-TEX Certified Combed Cotton (240GSM Heavyweight feel). Pre-shrunk weave with natural breathable density.';
  let fitDim = 'Relaxed Australian fit. Designed with comfortable sleeve proportions and clean drop shoulder.';
  let careText = 'Machine wash cold inside out with like colours. Line dry in shade. Warm iron if needed. Do not tumble dry.';
  let whatsInc = `1x ${product.name}, 1x Bongo Curated Organic Storage Pouch.`;

  if (product.category === 'home-decor') {
    fabMat = 'Hand-selected terracotta, solid timber, brass and traditional Nakshi Kantha embroidered cotton textiles.';
    fitDim = 'Dimensions: approx 45cm x 45cm / Craft weight: 650g.';
    careText = 'Spot clean with damp cloth or gentle hand wash in cold water for embroidered textiles. Keep ceramic pieces dry.';
    whatsInc = `1x ${product.name}, Authenticity & Craft Origin Tag.`;
  } else if (product.category === 'jute') {
    fabMat = '100% Natural Golden Jute Fiber thoughtfully sourced from regional artisan communities. Eco-friendly and 100% biodegradable.';
    fitDim = 'Dimensions: 38cm (H) x 42cm (W) x 15cm (D). Handle drop: 24cm.';
    careText = 'Wipe clean with a damp cloth. Avoid submerging in water. Allow to dry thoroughly in fresh air.';
    whatsInc = `1x ${product.name}.`;
  }

  const accordionsData = [
    {
      title: 'Description & Craft Story',
      content: `<p>Crafted in collaboration with skilled master artisans and tailored specifically for the relaxed Australian lifestyle. ${product.desc}. Every piece honors traditional textile heritage while adhering to international ethical production standards.</p>`
    },
    {
      title: 'Materials & Fabric',
      content: `<p>${fabMat}</p>`
    },
    {
      title: 'Dimensions & Fit',
      content: `<p>${fitDim}</p>`
    },
    {
      title: 'Care Instructions',
      content: `<p>${careText}</p>`
    },
    {
      title: "What's Included",
      content: `<p>${whatsInc}</p>`
    }
  ];

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
      executeAddToCart(detailState);
      if (typeof toggleCartDrawer === 'function') {
        toggleCartDrawer();
      }
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

  if (!openBtn || !modalOverlay) return;

  openBtn.addEventListener('click', () => {
    modalOverlay.classList.add('open');
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      modalOverlay.classList.remove('open');
    });
  }

  modalOverlay.addEventListener('click', (e) => {
    if (e.target === modalOverlay) {
      modalOverlay.classList.remove('open');
    }
  });
}
