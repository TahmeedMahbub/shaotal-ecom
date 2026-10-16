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
        <button class="btn btn-primary btn-sm" onclick="openAuthModal('login')">Sign In / Register</button>
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
      <button class="account-logout-btn" id="account-header-logout-btn">Log Out</button>
    `;
    document.getElementById('account-header-logout-btn').addEventListener('click', () => window.logoutUser());
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

  /* Orders */
  const ordersListEl = document.getElementById('account-orders-list');
  ordersListEl.innerHTML = '<p style="color:var(--color-grey-600)">Loading your orders…</p>';
  window.bongoApi('GET', '/account/orders').then(res => {
    const orders = res.ok ? res.data : [];
    if (orders.length === 0) {
      ordersListEl.innerHTML = `
        <div style="text-align:center; padding:var(--space-lg) 0">
          <p style="color:var(--color-grey-600); margin-bottom:var(--space-md)">You haven't placed any orders yet.</p>
          <a href="/products" class="btn btn-primary">Start Shopping</a>
        </div>`;
      return;
    }
    ordersListEl.innerHTML = orders.map(ord => `
      <div class="order-card">
        <div class="order-card-header">
          <div>
            <span class="order-id">Order #${accountEsc(ord.id)}</span>
            <span class="order-date">· ${accountEsc(ord.date)}</span>
          </div>
          <span class="order-status-badge ${accountEsc(statusClass(ord.status))}">${accountEsc(ord.status)}</span>
        </div>
        <div class="order-items-body">
          ${ord.items.map(item => `
            <div class="order-item-row">
              <img class="order-item-thumb" src="${accountEsc(item.image || window.PLACEHOLDER_IMG)}" alt="${accountEsc(item.name)}" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
              <div class="order-item-info">
                <div class="order-item-title">${accountEsc(item.name)}</div>
                <div class="order-item-variant">Variant: ${accountEsc(item.size || 'Standard')} / ${accountEsc(item.color || 'Default')} · Qty: ${item.quantity}</div>
              </div>
              <div class="order-item-price">$${(item.price * item.quantity).toFixed(2)}</div>
            </div>`).join('')}
        </div>
        <div class="order-footer">
          <span class="order-total-label">Order Total</span>
          <span class="order-total-value">$${ord.total.toFixed(2)} AUD</span>
        </div>
      </div>`).join('');
  });

  /* Wishlist */
  const wishlistGridEl = document.getElementById('account-wishlist-grid');
  const wItems = accountList('bongo_wishlist').map(i => (typeof i === 'object' ? (i.productId || i.id) : i));
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
            <img src="${accountEsc(product.image || window.PLACEHOLDER_IMG)}" alt="${accountEsc(product.name)}" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
          </div>
          <div class="product-card-content" style="padding:var(--space-sm);">
            <h4 class="product-card-title">${accountEsc(product.name)}</h4>
            <div class="product-card-price" style="margin:4px 0">$${product.price.toFixed(2)} AUD</div>
            <button class="btn btn-primary btn-sm" style="width:100%" onclick="openVariantModal(PRODUCTS.find(p=>p.id===${product.id}))">Add to Cart</button>
          </div>
        </div>`).join('') + '</div>';
  }

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
            <img src="${accountEsc(item.image || window.PLACEHOLDER_IMG)}" style="width:50px; height:50px; border-radius:var(--radius-sm); object-fit:cover;" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
            <div style="flex:1; margin-left:var(--space-sm)">
              <div style="font-weight:bold; font-size:var(--fs-small)">${accountEsc(item.name)}</div>
              <div style="font-size:var(--fs-xs); color:var(--color-grey-600)">Variant: ${accountEsc(item.size || 'Standard')} / ${accountEsc(window.COLOR_NAMES[item.color] || item.color || 'Default')} · Qty: ${item.quantity || 1}</div>
            </div>
            <div style="font-weight:bold; font-size:var(--fs-small); color:var(--color-brand)">$${(item.price * (item.quantity || 1)).toFixed(2)}</div>
          </div>`).join('')}
        <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--color-grey-200); padding-top:var(--space-sm); margin-top:var(--space-sm)">
          <span style="font-weight:bold">Total Subtotal: $${totalVal.toFixed(2)} AUD</span>
          <button class="btn btn-primary" onclick="toggleCart()">Checkout via Cart</button>
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
          <div style="display:flex; gap:0.75rem; justify-content:center;">
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
              <div class="request-hist-meta" style="font-size:var(--fs-xs); color:var(--color-grey-600);">Submitted ${new Date(br.createdAt.replace(' ', 'T')).toLocaleDateString('en-AU')}</div>
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

  if (window.location.hash) {
    const targetTabBtn = document.querySelector(`.account-tab-item[data-account-tab="${window.location.hash.replace('#', '')}"]`);
    if (targetTabBtn) targetTabBtn.click();
  }
}
