document.addEventListener('DOMContentLoaded', () => {
  renderAccountPage();
});

function accountEsc(str) {
  return String(str ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
}

function accountList(key) {
  try {
    const parsed = JSON.parse(localStorage.getItem(key));
    if (Array.isArray(parsed)) return parsed;
  } catch (e) { /* ignore */ }
  return [];
}

function statusClass(status) {
  return String(status || '').toLowerCase().replace(/\s+/g, '-');
}

function renderAccountPage() {
  const curr = window.getStoredCurrentUser ? window.getStoredCurrentUser() : null;
  const headerCard = document.getElementById('account-header-card');
  const unauthView = document.getElementById('account-unauth-view');
  const authView = document.getElementById('account-auth-view');

  if (!curr) {
    if (headerCard) {
      headerCard.innerHTML = `
        <div class="account-user-info">
          <div class="account-avatar-lg" style="background:var(--color-grey-300)">?</div>
          <div>
            <h2 class="account-user-title">Guest User</h2>
            <div class="account-user-email">Sign in to manage your Australian orders & wishlist</div>
          </div>
        </div>
        <button class="btn btn-primary btn-sm" data-action="open-auth" data-auth-tab="login">Sign In / Register</button>
      `;
    }
    if (unauthView) unauthView.style.display = 'block';
    if (authView) authView.style.display = 'none';
    return;
  }

  if (unauthView) unauthView.style.display = 'none';
  if (authView) authView.style.display = 'grid';

  const initial = (curr.name || 'U').charAt(0).toUpperCase();
  if (headerCard) {
    headerCard.innerHTML = `
      <div class="account-user-info">
        <div class="account-avatar-lg">${accountEsc(initial)}</div>
        <div>
          <h2 class="account-user-title">G'day, ${accountEsc(curr.name)}</h2>
          <div class="account-user-email">${accountEsc(curr.email)} · Member since ${accountEsc(curr.joinedDate || '')}</div>
        </div>
      </div>
    `;
    const headerLogout = document.getElementById('account-header-logout-btn');
    if (headerLogout) headerLogout.addEventListener('click', () => window.logoutUser());
  }

  /* Profile */
  const nameInput = document.getElementById('profile-name');
  const emailInput = document.getElementById('profile-email');
  const phoneInput = document.getElementById('profile-phone');
  const postcodeInput = document.getElementById('profile-postcode');
  const addressInput = document.getElementById('profile-address');
  nameInput.value = curr.name || '';
  emailInput.value = curr.email || '';
  phoneInput.value = curr.phone || '';
  postcodeInput.value = curr.postcode || '';
  addressInput.value = curr.address || '';

  document.getElementById('profile-edit-form').onsubmit = async e => {
    e.preventDefault();
    const btn = document.getElementById('profile-save-btn');
    btn.disabled = true;
    const res = await window.bongoApi('PUT', '/account/profile', {
      name: nameInput.value, phone: phoneInput.value, postcode: postcodeInput.value, address: addressInput.value,
    });
    btn.disabled = false;
    if (!res.ok) return showToast(window.bongoApiMessage(res));
    window.__BOOT__.user = res.data.user;
    showToast('Profile details updated successfully!');
    renderAccountPage();
  };

  /* Orders (paginated: GET /account/orders?page=N -> {orders, page, hasMore}) */
  const ordersListEl = document.getElementById('account-orders-list');
  ordersListEl.innerHTML = '<p style="color:var(--color-grey-600)">Loading your orders…</p>';
  const money = v => `$${Number(v || 0).toFixed(2)}`;
  const orderCard = ord => `
      <div class="order-card">
        <div class="order-card-header">
          <div>
            <a class="order-id" href="${accountEsc(ord.url)}">Order #${accountEsc(ord.id)}</a>
            <span class="order-date">· ${accountEsc(ord.date)}</span>
          </div>
          <span class="order-status-badge ${accountEsc(ord.statusClass)}">${accountEsc(ord.status)}</span>
        </div>
        <div class="order-items-body">
          ${ord.items.map(item => `
            <div class="order-item-row">
              <img class="order-item-thumb" src="${accountEsc(window.imgUrl(item.image, 160) || window.PLACEHOLDER_IMG)}" alt="${accountEsc(item.name)}" width="44" height="44" loading="lazy">
              <div class="order-item-info">
                <div class="order-item-title">${accountEsc(item.name)}</div>
                <div class="order-item-variant">Variant: ${accountEsc(item.size || 'Standard')} / ${accountEsc(item.color || 'Default')} · Qty: ${Number(item.quantity) || 0}</div>
              </div>
              <div class="order-item-price">${money(item.price * item.quantity)}</div>
            </div>`).join('')}
        </div>
        <div class="order-item-variant" style="padding-top:6px">${accountEsc(ord.paymentMethod)} · ${accountEsc(ord.paymentStatus)}</div>
        <div class="order-footer">
          <span class="order-total-label">Order Total</span>
          <span class="order-total-value">${money(ord.total)} AUD</span>
        </div>
        <div style="text-align:right; margin-top:var(--space-sm)">
          <a href="${accountEsc(ord.url)}" class="btn btn-outline btn-sm">View Details &amp; Track</a>
        </div>
      </div>`;
  const loadOrders = page => window.bongoApi('GET', `/account/orders?page=${page}`).then(res => {
    const more = document.getElementById('account-orders-more');
    if (more) more.parentNode.removeChild(more);
    if (!res.ok) {
      if (page === 1) ordersListEl.innerHTML = `<p style="color:var(--color-grey-600)">${accountEsc(window.bongoApiMessage(res))}</p>`;
      else showToast(window.bongoApiMessage(res));
      return;
    }
    const orders = Array.isArray(res.data.orders) ? res.data.orders : [];
    if (page === 1 && orders.length === 0) {
      ordersListEl.innerHTML = `
        <div style="text-align:center; padding:var(--space-lg) 0">
          <p style="color:var(--color-grey-600); margin-bottom:var(--space-md)">You haven't placed any orders yet.</p>
          <a href="/products" class="btn btn-primary">Start Shopping</a>
        </div>`;
      return;
    }
    if (page === 1) ordersListEl.innerHTML = '';
    ordersListEl.insertAdjacentHTML('beforeend', orders.map(orderCard).join(''));
    if (res.data.hasMore) {
      ordersListEl.insertAdjacentHTML('beforeend', '<div id="account-orders-more" style="text-align:center"><button type="button" class="btn btn-secondary btn-sm">Load More Orders</button></div>');
      const btn = document.querySelector('#account-orders-more button');
      btn.addEventListener('click', () => { btn.disabled = true; btn.textContent = 'Loading…'; loadOrders(page + 1); });
    }
  });
  loadOrders(1);

  /* Wishlist */
  const wishlistGridEl = document.getElementById('account-wishlist-grid');
  const wItems = accountList('bongo_wishlist').map(i => (typeof i === 'object' ? (i.productId || i.id) : i));
  // Wishlist products are fetched by id when this page did not embed them.
  wishlistGridEl.innerHTML = '<p style="color:var(--color-grey-600)">Loading your wishlist…</p>';
  window.BongoCatalog.ensure(wItems).then(() => {
  const wProducts = wItems.map(id => PRODUCTS.find(p => p.id === id)).filter(Boolean);
  if (wProducts.length === 0) {
    wishlistGridEl.innerHTML = `
      <div style="text-align:center; padding:var(--space-lg) 0">
        <p style="color:var(--color-grey-600); margin-bottom:var(--space-md)">Your wishlist is empty.</p>
        <a href="/products" class="btn btn-primary">Discover Artisan Crafts</a>
      </div>`;
  } else {
    wishlistGridEl.innerHTML = '<div class="product-grid" style="grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:var(--space-md);">' +
      wProducts.map(product => `
        <div class="product-card" style="box-shadow:none; border:1px solid var(--color-grey-200);">
          <div class="product-card-image">
            <img src="${accountEsc(window.imgUrl(product.image, 480) || window.PLACEHOLDER_IMG)}" alt="${accountEsc(product.name)}">
          </div>
          <div class="product-card-content" style="padding:var(--space-sm);">
            <h4 class="product-card-title">${accountEsc(product.name)}</h4>
            <div class="product-card-price" style="margin:4px 0">$${product.price.toFixed(2)} AUD</div>
            <button class="btn btn-primary btn-sm" style="width:100%" data-action="open-variant" data-product-id="${Number(product.id)}">Add to Cart</button>
          </div>
        </div>`).join('') + '</div>';
  }
  });

  /* Cart */
  const cartGridEl = document.getElementById('account-cart-grid');
  const cItems = accountList('bongo_cart');
  if (cItems.length === 0) {
    cartGridEl.innerHTML = `
      <div style="text-align:center; padding:var(--space-lg) 0">
        <p style="color:var(--color-grey-600); margin-bottom:var(--space-md)">Your shopping bag is empty.</p>
        <a href="/products" class="btn btn-primary">Browse Catalog</a>
      </div>`;
  } else {
    const totalVal = cItems.reduce((s, i) => s + (i.price * (i.quantity || 1)), 0);
    cartGridEl.innerHTML = `
      <div style="display:flex; flex-direction:column; gap:var(--space-sm)">
        ${cItems.map(item => `
          <div class="cart-item" style="border:1px solid var(--color-grey-200); border-radius:var(--radius-md); padding:var(--space-sm)">
            <img src="${accountEsc(item.image || window.PLACEHOLDER_IMG)}" style="width:50px; height:50px; border-radius:var(--radius-sm); object-fit:cover;">
            <div style="flex:1; min-width:0; margin-left:var(--space-sm)">
              <div style="font-weight:bold; font-size:var(--fs-small); overflow-wrap:anywhere">${accountEsc(item.name)}</div>
              <div style="font-size:var(--fs-xs); color:var(--color-grey-600)">Variant: ${accountEsc(item.size || 'Standard')} / ${accountEsc(window.COLOR_NAMES[item.color] || item.color || 'Default')} · Qty: ${item.quantity || 1}</div>
            </div>
            <div style="font-weight:bold; font-size:var(--fs-small); color:var(--color-brand); white-space:nowrap; padding-left:var(--space-xs)">$${(item.price * (item.quantity || 1)).toFixed(2)}</div>
          </div>`).join('')}
        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--color-grey-200); padding-top:var(--space-sm); margin-top:var(--space-sm)">
          <span style="font-weight:bold">Total Subtotal: $${totalVal.toFixed(2)} AUD</span>
          <button class="btn btn-primary" data-action="toggle-cart">Checkout via Cart</button>
        </div>
      </div>`;
  }

  /* Sourcing & bulk requests */
  const requestsListEl = document.getElementById('account-requests-list');
  requestsListEl.innerHTML = '<p style="color:var(--color-grey-600)">Loading your requests…</p>';
  window.bongoApi('GET', '/account/requests').then(res => {
    const reqs = res.ok ? res.data.sourcing : [];
    const bulkReqs = res.ok ? res.data.bulk : [];
    if (reqs.length === 0 && bulkReqs.length === 0) {
      requestsListEl.innerHTML = `
        <div style="text-align:center; padding:var(--space-lg) 0">
          <p style="color:var(--color-grey-600); margin-bottom:var(--space-md)">You haven't submitted any custom sourcing or bulk order requests yet.</p>
          <div style="display:flex; flex-wrap:wrap; gap:0.75rem; justify-content:center;">
            <a href="/request" class="btn btn-outline">Submit Custom Sourcing</a>
            <a href="/bulk-order" class="btn btn-primary">Request Bulk Order</a>
          </div>
        </div>`;
      return;
    }
    let html = '';
    if (bulkReqs.length > 0) {
      html += '<h4 style="font-family:var(--font-heading); font-size:var(--fs-body); margin-bottom:0.75rem; color:var(--color-dark);">Your Bulk Order Requests:</h4>';
      html += bulkReqs.map(br => `
        <div class="request-hist-card" style="border: 1px solid var(--color-grey-300); background: var(--color-surface-card); padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1rem;">
          <div class="request-hist-header" style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
            <div>
              <span class="request-hist-title" style="font-weight:bold; color:var(--color-brand);">${accountEsc(br.requestNumber)} (${br.totalUnits} Units)</span>
              <div class="request-hist-meta" style="font-size:var(--fs-xs); color:var(--color-grey-600);">Submitted ${accountEsc(br.date)}</div>
            </div>
            <span class="order-status-badge ${accountEsc(statusClass(br.status))}" style="padding:0.2rem 0.6rem; border-radius:99px; font-weight:bold; font-size:var(--fs-xs);">${accountEsc(br.status)}</span>
          </div>
          <div style="font-size:var(--fs-xs); color:var(--color-grey-700);">
            <strong>Items:</strong> ${br.items.map(i => accountEsc(i.name) + ' (' + i.variants.map(v => accountEsc(v.color) + '/' + accountEsc(v.size) + ' x ' + v.qty).join(', ') + ')').join('; ') || '-'}
          </div>
        </div>`).join('');
    }
    if (reqs.length > 0) {
      html += '<h4 style="font-family:var(--font-heading); font-size:var(--fs-body); margin:1.25rem 0 0.75rem 0; color:var(--color-dark);">Your Custom Sourcing Requests:</h4>';
      html += reqs.map(r => `
        <div class="request-hist-card">
          <div class="request-hist-header">
            <div>
              <span class="request-hist-title">${accountEsc(r.title)}</span>
              <div class="request-hist-meta">Request ID: ${accountEsc(r.id)} · Submitted ${accountEsc(r.date)}</div>
            </div>
            <span class="order-status-badge ${accountEsc(statusClass(r.status))}">${accountEsc(r.status)}</span>
          </div>
          <div style="font-size:var(--fs-xs); color:var(--color-grey-700); margin-bottom:8px">
            <div><strong>Quantity:</strong> ${r.qty}</div>
            ${r.link ? `<div><strong>Link:</strong> <a href="${accountEsc(r.link)}" target="_blank" rel="noopener" style="color:var(--color-brand)">${accountEsc(r.link)}</a></div>` : ''}
            ${r.details ? `<div style="margin-top:4px; white-space:pre-line"><strong>Details:</strong> ${accountEsc(r.details)}</div>` : ''}
          </div>
        </div>`).join('');
    }
    requestsListEl.innerHTML = html;
  });

  /* My Locations */
  initAccountLocations();

  /* Tabs */
  document.querySelectorAll('.account-tab-item[data-account-tab]').forEach(tabBtn => {
    tabBtn.addEventListener('click', () => {
      document.querySelectorAll('.account-tab-item').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.account-tab-content-panel').forEach(p => p.classList.remove('active'));
      tabBtn.classList.add('active');
      const targetPanel = document.getElementById(`tab-panel-${tabBtn.dataset.accountTab}`);
      if (targetPanel) targetPanel.classList.add('active');
    });
  });

  const sidebarLogout = document.getElementById('account-sidebar-logout');
  if (sidebarLogout) sidebarLogout.addEventListener('click', () => window.logoutUser());

  // Deep links (/account#orders from "View My Orders", the header menu and order pages); also when only the hash changes.
  const openTabFromHash = () => {
    const key = window.location.hash.replace('#', '');
    const targetTabBtn = key && document.querySelector(`.account-tab-item[data-account-tab="${CSS.escape(key)}"]`);
    if (targetTabBtn) targetTabBtn.click();
  };
  openTabFromHash();
  if (!window.__accountHashBound) {
    window.__accountHashBound = true;
    window.addEventListener('hashchange', openTabFromHash);
  }
}

/* ── My Locations: saved delivery addresses (GET/POST/PUT/DELETE /account/addresses, PUT …/{id}/default) ── */
const LOCATION_ICONS = {
  home: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>',
  office: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>',
  other: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>',
  check: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
  edit: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>',
  trash: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>',
  phone: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>',
};
// Same rules as AccountController (PHONE_REGEX, 4-digit postcode, AU states); the server re-validates everything.
const LOCATION_PHONE_RE = /^(?=(?:\D*\d){8,15}\D*$)\+?[0-9 ()\-]{8,24}$/;
const LOCATION_FIELDS = {
  fullName: 'location-name', phone: 'location-phone', line1: 'location-line1', street: 'location-line1', line2: 'location-line2',
  suburb: 'location-suburb', state: 'location-state', postcode: 'location-postcode', customLabel: 'location-custom-label',
};
// The shared AddressForm component (assets/js/address-form.js) owns State → Suburb → Street → Postcode → Type.
const locationFields = () => AddressForm.mount(document.getElementById('location-fields'));
let accountLocations = [];

function locationLabel(addr) {
  if (addr.addressType === 'Office') return 'Work';
  if (addr.addressType === 'Other') return addr.customLabel || 'Other';
  return 'Home';
}

function renderLocations() {
  const list = document.getElementById('account-locations-list');
  if (!list) return;
  if (accountLocations.length === 0) {
    list.innerHTML = `
      <div class="location-empty">
        <p>You haven't saved any delivery locations yet.</p>
        <button type="button" class="btn btn-primary" data-location-action="add">Add Your First Location</button>
      </div>`;
    return;
  }
  list.innerHTML = accountLocations.map(addr => {
    const icon = addr.addressType === 'Office' ? LOCATION_ICONS.office : (addr.addressType === 'Home' ? LOCATION_ICONS.home : LOCATION_ICONS.other);
    const id = Number(addr.id);
    return `
      <div class="address-card${addr.isDefault ? ' selected' : ''}" data-id="${id}">
        <div class="address-card-header">
          <span class="address-type-badge">${icon} ${accountEsc(locationLabel(addr))}</span>
          ${addr.isDefault ? `<span class="address-selection-indicator">${LOCATION_ICONS.check} Default</span>` : ''}
        </div>
        <div class="address-name">${accountEsc(addr.fullName)}</div>
        <div class="address-text">
          ${accountEsc(addr.line1 || addr.street)}${addr.line2 ? `<br>${accountEsc(addr.line2)}` : ''}<br>
          ${accountEsc(addr.suburb)}${addr.suburb ? ', ' : ''}${accountEsc(addr.state)} ${accountEsc(addr.postcode)}<br>
          ${accountEsc(addr.country || 'Australia')}
        </div>
        <div class="address-phone">${LOCATION_ICONS.phone} ${accountEsc(addr.phone)}</div>
        <div class="address-actions">
          <button type="button" class="address-action-btn" data-location-action="edit" data-id="${id}">${LOCATION_ICONS.edit} Edit</button>
          <button type="button" class="address-action-btn delete-btn" data-location-action="delete" data-id="${id}">${LOCATION_ICONS.trash} Delete</button>
          ${addr.isDefault ? '' : `<button type="button" class="address-action-btn" data-location-action="default" data-id="${id}">${LOCATION_ICONS.check} Set as Default</button>`}
        </div>
        <div class="location-confirm" data-confirm-for="${id}" role="alertdialog" aria-label="Confirm delete" hidden>
          <p>Delete this location?${addr.isDefault && accountLocations.length > 1 ? ' Your oldest other location will become the default.' : ''}</p>
          <div class="location-confirm-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-location-action="cancel-delete" data-id="${id}">Keep</button>
            <button type="button" class="btn btn-sm location-delete-confirm" data-location-action="confirm-delete" data-id="${id}">Delete</button>
          </div>
        </div>
      </div>`;
  }).join('');
}

function setLocationError(message, fieldKeys) {
  const box = document.getElementById('location-form-error');
  document.querySelectorAll('#location-form [aria-invalid]').forEach(el => el.removeAttribute('aria-invalid'));
  if (!message) { box.hidden = true; box.textContent = ''; return; }
  box.textContent = message;
  box.hidden = false;
  const first = (fieldKeys || []).map(k => (id => document.getElementById(id + '-cb') || document.getElementById(id))(LOCATION_FIELDS[k] || '')).filter(Boolean);
  first.forEach(el => el.setAttribute('aria-invalid', 'true'));
  if (first[0]) first[0].focus();
}

function openLocationForm(addr) {
  const form = document.getElementById('location-form');
  setLocationError('');
  form.reset();
  document.getElementById('location-id').value = addr ? addr.id : '';
  document.getElementById('location-form-title').textContent = addr ? 'Edit Location' : 'Add a New Location';
  document.getElementById('location-save-btn').textContent = addr ? 'Save Changes' : 'Save Location';
  const set = (id, v) => { document.getElementById(id).value = v || ''; };
  const fields = locationFields();
  fields.reset();
  if (addr) {
    set('location-name', addr.fullName);
    set('location-phone', addr.phone);
    fields.set({ state: addr.state, suburb: addr.suburb, postcode: addr.postcode, street: addr.line1 || addr.street, line2: addr.line2, addressType: addr.addressType, customLabel: addr.customLabel });
  } else {
    const curr = window.getStoredCurrentUser ? window.getStoredCurrentUser() : null;
    if (curr) {
      set('location-name', curr.name);
      set('location-phone', curr.phone);
    }
  }
  // The first location always becomes the default, and the current default stays default (pick another to change it).
  const def = document.getElementById('location-default');
  def.checked = accountLocations.length === 0 || !!(addr && addr.isDefault);
  def.disabled = def.checked;
  form.hidden = false;
  document.getElementById('location-add-btn').hidden = true;
  form.scrollIntoView({ behavior: 'smooth', block: 'start' });
  document.getElementById(addr ? 'location-name' : (fields.legacy ? 'location-state' : 'location-state-cb')).focus({ preventScroll: true });
}

function closeLocationForm() {
  document.getElementById('location-form').hidden = true;
  document.getElementById('location-add-btn').hidden = false;
  setLocationError('');
}

function readLocationForm() {
  const val = id => document.getElementById(id).value.trim();
  const a = locationFields().get();
  return {
    addressType: a.addressType,
    customLabel: a.customLabel,
    fullName: val('location-name'),
    phone: val('location-phone'),
    line1: a.street,
    line2: a.line2,
    suburb: a.suburb,
    state: a.state,
    postcode: a.postcode,
    country: 'Australia',
    isDefault: document.getElementById('location-default').checked,
  };
}

function checkLocation(d) {
  if (!d.fullName || !d.phone) return ['Please fill in all required fields.', [!d.fullName ? 'fullName' : 'phone']];
  if (!LOCATION_PHONE_RE.test(d.phone)) return ['Please enter a valid phone number (8 to 15 digits).', ['phone']];
  return locationFields().check();
}

async function locationRequest(method, url, body, okMessage) {
  const res = await window.bongoApi(method, url, body);
  if (!res.ok) return res;
  if (res.data && Array.isArray(res.data.addresses)) accountLocations = res.data.addresses;
  renderLocations();
  if (okMessage) showToast(okMessage);
  return res;
}

function initAccountLocations() {
  const list = document.getElementById('account-locations-list');
  const form = document.getElementById('location-form');
  if (!list || !form) return;

  list.innerHTML = '<p style="color:var(--color-grey-600)">Loading your locations…</p>';
  window.bongoApi('GET', '/account/addresses').then(res => {
    if (!res.ok) {
      list.innerHTML = `<p style="color:var(--color-grey-600)">${accountEsc(window.bongoApiMessage(res))}</p>`;
      return;
    }
    accountLocations = Array.isArray(res.data) ? res.data : [];
    renderLocations();
  });

  if (window.__accountLocationsBound) return; // renderAccountPage() runs again after a profile save
  window.__accountLocationsBound = true;

  document.getElementById('location-add-btn').addEventListener('click', () => openLocationForm(null));
  document.getElementById('location-cancel-btn').addEventListener('click', closeLocationForm);
  locationFields(); // mount the shared address fields once

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const d = readLocationForm();
    const problem = checkLocation(d);
    if (problem) return setLocationError(problem[0], problem[1]);
    setLocationError('');
    const id = document.getElementById('location-id').value;
    const btn = document.getElementById('location-save-btn');
    btn.disabled = true;
    const res = await locationRequest(id ? 'PUT' : 'POST', id ? `/account/addresses/${encodeURIComponent(id)}` : '/account/addresses', d,
      id ? 'Location updated.' : 'Location saved.');
    btn.disabled = false;
    if (!res.ok) return setLocationError(window.bongoApiMessage(res), Object.keys((res.data && res.data.errors) || {}));
    closeLocationForm();
    list.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });

  list.addEventListener('click', async e => {
    const btn = e.target.closest('[data-location-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id);
    const confirmBox = id ? list.querySelector(`[data-confirm-for="${id}"]`) : null;
    switch (btn.dataset.locationAction) {
      case 'add':
        openLocationForm(null);
        break;
      case 'edit':
        openLocationForm(accountLocations.find(a => Number(a.id) === id));
        break;
      case 'delete':
        list.querySelectorAll('.location-confirm').forEach(b => { b.hidden = true; });
        confirmBox.hidden = false;
        confirmBox.querySelector('[data-location-action="cancel-delete"]').focus();
        break;
      case 'cancel-delete':
        confirmBox.hidden = true;
        list.querySelector(`[data-location-action="delete"][data-id="${id}"]`).focus();
        break;
      case 'confirm-delete': {
        btn.disabled = true;
        const res = await locationRequest('DELETE', `/account/addresses/${id}`, undefined, 'Location deleted.');
        if (!res.ok) { btn.disabled = false; showToast(window.bongoApiMessage(res)); break; }
        if (document.getElementById('location-id').value === String(id)) closeLocationForm();
        break;
      }
      case 'default': {
        btn.disabled = true;
        const res = await locationRequest('PUT', `/account/addresses/${id}/default`, undefined, 'Default location updated.');
        if (!res.ok) { btn.disabled = false; showToast(window.bongoApiMessage(res)); }
        break;
      }
    }
  });
}
