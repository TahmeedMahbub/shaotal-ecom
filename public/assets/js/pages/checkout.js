document.addEventListener('DOMContentLoaded', () => {
  const api = window.bongoApi;
  const BOOT = window.__BOOT__ || {};
  const user = BOOT.user || null;
  const money = n => `${SITE.currency.replace('A', '')}${Number(n).toFixed(2)}`; // "$12.00" as in the prepared design
  const esc = str => String(str ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const toast = msg => { if (typeof showToast === 'function') showToast(msg); };

  const SVG_ICONS = {
    home: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>',
    office: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>',
    other: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>',
    check: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>',
    edit: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>',
    trash: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>',
    phone: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z"/></svg>',
  };

  /* ── Addresses: saved server-side for signed-in customers, kept in this browser for guests ── */
  const GUEST_KEY = 'bongo_saved_addresses';
  let addresses = [];
  let selectedAddressId = null;
  let appliedCoupon = null; // { code, label }
  let lastQuote = null;

  const readGuestAddresses = () => {
    try { const a = JSON.parse(localStorage.getItem(GUEST_KEY)); return Array.isArray(a) ? a : []; } catch (e) { return []; }
  };
  const writeGuestAddresses = () => {
    try { localStorage.setItem(GUEST_KEY, JSON.stringify(addresses)); } catch (e) { /* storage unavailable */ }
  };

  const addressCardsGrid = document.getElementById('address-cards-grid');
  const addAddressBtn = document.getElementById('add-address-btn');
  const addressFormPanel = document.getElementById('address-form-panel');
  const newAddressForm = document.getElementById('new-address-form');
  const cancelAddressBtn = document.getElementById('cancel-address-btn');
  const formPanelTitle = document.getElementById('form-panel-title');
  const editAddressIdInput = document.getElementById('edit-address-id');

  const summaryItemsList = document.getElementById('summary-items-list');
  const summaryItemsCount = document.getElementById('summary-items-count');
  const summarySubtotal = document.getElementById('summary-subtotal');
  const summaryShipping = document.getElementById('summary-shipping');
  const summaryDiscount = document.getElementById('summary-discount');
  const summaryTotal = document.getElementById('summary-total');
  const discountRow = document.getElementById('discount-row');

  const couponToggleBtn = document.getElementById('coupon-toggle-btn');
  const couponChevron = document.getElementById('coupon-chevron');
  const couponFormContainer = document.getElementById('coupon-form-container');
  const couponCodeInput = document.getElementById('coupon-code-input');
  const applyCouponBtn = document.getElementById('apply-coupon-btn');
  const couponErrorMsg = document.getElementById('coupon-error-msg');
  const couponAppliedBox = document.getElementById('coupon-applied-box');
  const couponAppliedText = document.getElementById('coupon-applied-text');
  const removeCouponBtn = document.getElementById('remove-coupon-btn');

  const placeOrderBtn = document.getElementById('place-order-btn');
  const successModalOverlay = document.getElementById('success-modal-overlay');

  function renderAddresses() {
    if (addresses.length === 0) {
      addressCardsGrid.innerHTML = `
        <div style="grid-column: 1/-1; text-align: center; padding: 1.5rem; background: var(--color-surface); border-radius: var(--radius-md);">
          <p style="color: var(--color-grey-700); font-size: var(--fs-small); margin-bottom: 0.5rem;">No saved addresses found.</p>
        </div>`;
      showAddressForm();
      return;
    }

    addressCardsGrid.innerHTML = addresses.map(addr => {
      const isSelected = addr.id === selectedAddressId;
      const type = addr.addressType || 'Home';
      const typeIcon = type === 'Office' ? SVG_ICONS.office : (type === 'Home' ? SVG_ICONS.home : SVG_ICONS.other);
      return `
        <div class="address-card ${isSelected ? 'selected' : ''}" data-id="${esc(addr.id)}">
          <div class="address-card-header">
            <span class="address-type-badge">${typeIcon} ${esc(type)}</span>
            ${isSelected ? `<span class="address-selection-indicator">${SVG_ICONS.check} Selected</span>` : ''}
          </div>
          <div class="address-name">${esc(addr.fullName)}</div>
          <div class="address-text">${esc(addr.street)}<br>${esc(addr.suburb)}, ${esc(addr.state)} ${esc(addr.postcode)}</div>
          <div class="address-phone">${SVG_ICONS.phone} ${esc(addr.phone)}</div>
          <div class="address-actions">
            <button type="button" class="address-action-btn edit-btn" data-id="${esc(addr.id)}">${SVG_ICONS.edit} Edit</button>
            ${addresses.length > 1 ? `<button type="button" class="address-action-btn delete-btn" data-id="${esc(addr.id)}">${SVG_ICONS.trash} Delete</button>` : ''}
          </div>
        </div>`;
    }).join('');

    addressCardsGrid.querySelectorAll('.address-card').forEach(card => {
      card.addEventListener('click', e => {
        if (e.target.closest('.address-action-btn')) return;
        selectedAddressId = idFromString(card.dataset.id);
        renderAddresses();
      });
    });

    addressCardsGrid.querySelectorAll('.edit-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const target = addresses.find(a => String(a.id) === btn.dataset.id);
        if (!target) return;
        formPanelTitle.textContent = 'Edit Delivery Address';
        editAddressIdInput.value = target.id;
        document.getElementById('addr-fullname').value = target.fullName;
        document.getElementById('addr-phone').value = target.phone;
        document.getElementById('addr-street').value = target.street;
        document.getElementById('addr-suburb').value = target.suburb;
        document.getElementById('addr-state').value = target.state;
        document.getElementById('addr-postcode').value = target.postcode;
        document.querySelectorAll('input[name="addr-type"]').forEach(r => { r.checked = (r.value === target.addressType); });
        showAddressForm();
      });
    });

    addressCardsGrid.querySelectorAll('.delete-btn').forEach(btn => {
      btn.addEventListener('click', async e => {
        e.stopPropagation();
        const id = idFromString(btn.dataset.id);
        if (user) {
          const res = await api('DELETE', `/account/addresses/${id}`);
          if (!res.ok) return toast(window.bongoApiMessage(res));
          addresses = res.data.addresses;
        } else {
          addresses = addresses.filter(a => a.id !== id);
          writeGuestAddresses();
        }
        if (selectedAddressId === id) selectedAddressId = addresses.length ? addresses[0].id : null;
        renderAddresses();
        toast('Address deleted');
      });
    });
  }

  // data-id attributes are strings; saved addresses use numeric ids, guest ones use "addr-…" strings
  const idFromString = s => (/^\d+$/.test(s) ? parseInt(s, 10) : s);

  function showAddressForm() {
    addressFormPanel.style.display = 'block';
    addressFormPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideAddressForm() {
    addressFormPanel.style.display = 'none';
    newAddressForm.reset();
    editAddressIdInput.value = '';
    formPanelTitle.textContent = 'Add New Delivery Address';
  }

  addAddressBtn.addEventListener('click', () => {
    if (addressFormPanel.style.display === 'none') showAddressForm(); else hideAddressForm();
  });
  cancelAddressBtn.addEventListener('click', hideAddressForm);

  newAddressForm.addEventListener('submit', async e => {
    e.preventDefault();
    const editingRaw = editAddressIdInput.value;
    const editingId = editingRaw ? idFromString(editingRaw) : null;
    const data = {
      fullName: document.getElementById('addr-fullname').value.trim(),
      phone: document.getElementById('addr-phone').value.trim(),
      street: document.getElementById('addr-street').value.trim(),
      suburb: document.getElementById('addr-suburb').value.trim(),
      state: document.getElementById('addr-state').value,
      postcode: document.getElementById('addr-postcode').value.trim(),
      addressType: document.querySelector('input[name="addr-type"]:checked').value,
    };

    if (user) {
      const res = editingId ? await api('PUT', `/account/addresses/${editingId}`, data) : await api('POST', '/account/addresses', data);
      if (!res.ok) return toast(window.bongoApiMessage(res));
      addresses = res.data.addresses;
      selectedAddressId = res.data.id;
    } else {
      const entry = Object.assign({ id: editingId || ('addr-' + Date.now()) }, data);
      if (editingId) {
        const idx = addresses.findIndex(a => a.id === editingId);
        if (idx !== -1) addresses[idx] = entry;
      } else {
        addresses.push(entry);
      }
      selectedAddressId = entry.id;
      writeGuestAddresses();
    }
    renderAddresses();
    hideAddressForm();
    toast(editingId ? 'Address updated' : 'New address saved & selected');
  });

  /* ── Payment selection (methods are rendered by the server) ── */
  const paymentCards = document.querySelectorAll('.payment-option-card');
  function selectPayment(card) {
    paymentCards.forEach(c => {
      c.classList.remove('selected');
      const radio = c.querySelector('input[type="radio"]');
      if (radio) radio.checked = false;
      const sub = c.querySelector('.payment-subpanel');
      if (sub) sub.style.display = 'none';
    });
    card.classList.add('selected');
    const radio = card.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    const sub = card.querySelector('.payment-subpanel');
    if (sub) sub.style.display = 'block';
  }
  paymentCards.forEach(card => card.addEventListener('click', () => selectPayment(card)));
  if (paymentCards.length) selectPayment(paymentCards[0]);

  /* ── Cart & totals (prices always come from the server) ── */
  function getCart() {
    try {
      const parsed = JSON.parse(localStorage.getItem('bongo_cart'));
      if (Array.isArray(parsed)) return parsed;
    } catch (e) { /* ignore */ }
    return [];
  }
  const cartPayload = () => getCart().map(i => ({ productId: i.productId, color: i.color || null, size: i.size || null, quantity: i.quantity || 1 }));

  async function renderOrderSummary() {
    const cart = getCart();
    if (cart.length === 0) {
      summaryItemsCount.textContent = '0 Items';
      summaryItemsList.innerHTML = '<p style="font-size: var(--fs-small); color: var(--color-grey-500);">Your cart is empty.</p>';
      summarySubtotal.textContent = money(0);
      summaryTotal.textContent = `${money(0)} AUD`;
      placeOrderBtn.disabled = true;
      lastQuote = null;
      return;
    }

    const res = await api('POST', '/checkout/quote', { items: cartPayload(), coupon: appliedCoupon ? appliedCoupon.code : null });
    if (!res.ok) {
      toast(window.bongoApiMessage(res));
      return;
    }
    const q = res.data;
    lastQuote = q;

    if (appliedCoupon && q.couponError) {
      appliedCoupon = null;
      couponAppliedBox.style.display = 'none';
      couponErrorMsg.textContent = q.couponError;
      couponErrorMsg.style.display = 'block';
    }

    const itemCount = q.lines.reduce((acc, l) => acc + l.quantity, 0);
    summaryItemsCount.textContent = `${itemCount} Item${itemCount === 1 ? '' : 's'}`;
    summaryItemsList.innerHTML = q.lines.map(l => {
      const variantText = [l.size ? `Size: ${esc(l.size)}` : '', l.color ? `Color: ${esc(l.color)}` : ''].filter(Boolean).join(' | ');
      return `
        <div class="summary-item-row">
          <img src="${esc(l.image || window.PLACEHOLDER_IMG)}" alt="${esc(l.name)}" class="summary-item-img" onerror="this.onerror=null;this.src=window.PLACEHOLDER_IMG;">
          <div class="summary-item-info">
            <div class="summary-item-title">${esc(l.name)}</div>
            <div class="summary-item-meta">${variantText ? `${variantText} • ` : ''}Qty: ${l.quantity}</div>
          </div>
          <div class="summary-item-price">${money(l.price * l.quantity)}</div>
        </div>`;
    }).join('') + (q.errors || []).map(m => `<p style="font-size: var(--fs-small); color: var(--color-danger); margin-top: 0.5rem;">${esc(m)}</p>`).join('');

    summarySubtotal.textContent = money(q.subtotal);
    summaryShipping.textContent = q.shipping === 0 ? 'FREE' : money(q.shipping);
    if (q.discount > 0) {
      discountRow.style.display = 'flex';
      summaryDiscount.textContent = `-${money(q.discount)}`;
    } else {
      discountRow.style.display = 'none';
    }
    summaryTotal.textContent = `${money(q.total)} AUD`;
    placeOrderBtn.textContent = `Place Order • ${money(q.total)} AUD`;
    placeOrderBtn.disabled = q.lines.length === 0 || (q.errors || []).length > 0 || paymentCards.length === 0;
  }

  /* ── Coupon ── */
  couponToggleBtn.addEventListener('click', () => {
    const closed = couponFormContainer.style.display === 'none';
    couponFormContainer.style.display = closed ? 'block' : 'none';
    if (couponChevron) couponChevron.style.transform = closed ? 'rotate(180deg)' : 'rotate(0deg)';
    if (closed) couponCodeInput.focus();
  });

  applyCouponBtn.addEventListener('click', async () => {
    couponErrorMsg.style.display = 'none';
    const code = couponCodeInput.value.trim().toUpperCase();
    if (!code) {
      couponErrorMsg.textContent = 'Invalid or expired coupon.';
      couponErrorMsg.style.display = 'block';
      return;
    }
    if (appliedCoupon && appliedCoupon.code.toUpperCase() === code) return toast('Coupon is already applied');

    const res = await api('POST', '/checkout/quote', { items: cartPayload(), coupon: code });
    if (!res.ok) return toast(window.bongoApiMessage(res));
    if (res.data.couponError) {
      couponErrorMsg.textContent = res.data.couponError;
      couponErrorMsg.style.display = 'block';
      return toast(res.data.couponError);
    }

    appliedCoupon = res.data.coupon;
    couponFormContainer.style.display = 'none';
    if (couponChevron) couponChevron.style.transform = 'rotate(0deg)';
    couponAppliedText.innerHTML = `${SVG_ICONS.check} Coupon "${esc(appliedCoupon.code)}" Applied (-${esc(appliedCoupon.label)})`;
    couponAppliedBox.style.display = 'flex';
    renderOrderSummary();
    toast(`Coupon "${appliedCoupon.code}" applied successfully!`);
  });

  removeCouponBtn.addEventListener('click', () => {
    appliedCoupon = null;
    couponAppliedBox.style.display = 'none';
    couponCodeInput.value = '';
    couponErrorMsg.style.display = 'none';
    renderOrderSummary();
    toast('Coupon removed');
  });

  /* ── Place order ── */
  placeOrderBtn.addEventListener('click', async () => {
    if (getCart().length === 0) return toast('Your cart is empty');
    if (!selectedAddressId) return toast('Please select a delivery address');
    const selected = addresses.find(a => a.id === selectedAddressId);
    if (!selected) return toast('Please choose a valid delivery address');
    const method = (document.querySelector('input[name="payment-method"]:checked') || {}).value;
    if (!method) return toast('Please choose a payment method');

    const label = placeOrderBtn.innerHTML;
    placeOrderBtn.disabled = true;
    placeOrderBtn.innerHTML = '<svg class="icon-svg" style="animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Processing Order...';

    const body = { items: cartPayload(), coupon: appliedCoupon ? appliedCoupon.code : null, paymentMethod: method };
    if (user) body.addressId = selected.id; else body.address = selected;
    const res = await api('POST', '/checkout/place', body);

    if (!res.ok) {
      placeOrderBtn.innerHTML = label;
      placeOrderBtn.disabled = false;
      toast(window.bongoApiMessage(res));
      renderOrderSummary();
      return;
    }

    try { localStorage.setItem('bongo_cart', JSON.stringify([])); } catch (e) { /* storage unavailable */ }
    document.getElementById('success-order-ref').textContent = `#${res.data.orderNumber}`;
    document.getElementById('success-address-summary').textContent = res.data.address;
    document.getElementById('success-paid-amount').textContent = `${money(res.data.total)} AUD`;
    successModalOverlay.classList.add('active');
    toast('Order placed successfully!');
  });

  const breadcrumbCartLink = document.getElementById('breadcrumb-cart-link');
  if (breadcrumbCartLink) {
    breadcrumbCartLink.addEventListener('click', () => { if (typeof openCart === 'function') openCart(); });
  }

  /* ── Initial render ── */
  (async function init() {
    if (user) {
      const res = await api('GET', '/account/addresses');
      addresses = res.ok ? res.data : [];
      const def = addresses.find(a => a.isDefault) || addresses[0];
      selectedAddressId = def ? def.id : null;
    } else {
      addresses = readGuestAddresses();
      selectedAddressId = addresses.length ? addresses[0].id : null;
    }
    renderAddresses();
    renderOrderSummary();
  })();
});
