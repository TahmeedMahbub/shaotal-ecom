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
    gift: '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon-svg"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.25v8.25a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-8.25M12 4.875A2.625 2.625 0 109.375 7.5H12m0-2.625V7.5m0-2.625A2.625 2.625 0 114.625 7.5H12m0 0V21m-8.625-9.75h18c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125h-18c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>',
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

  const addrFields = AddressForm.mount(document.getElementById('addr-fields'));

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
            <span class="address-type-badge">${typeIcon} ${esc(addr.customLabel || (type === 'Office' ? 'Work' : type))}</span>
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
    requoteIfDeliveryChanged();

    addressCardsGrid.querySelectorAll('.edit-btn').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        const target = addresses.find(a => String(a.id) === btn.dataset.id);
        if (!target) return;
        formPanelTitle.textContent = 'Edit Delivery Address';
        editAddressIdInput.value = target.id;
        document.getElementById('addr-fullname').value = target.fullName;
        document.getElementById('addr-phone').value = target.phone;
        addrFields.set({ state: target.state, suburb: target.suburb, postcode: target.postcode, street: target.street, addressType: target.addressType });
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

  // Optional phone check: digits with an optional leading +, spaces/dashes/brackets allowed, 8–15 digits.
  const validPhone = s => /^\+?[\d\s\-()]+$/.test(s) && (s.match(/\d/g) || []).length >= 8 && (s.match(/\d/g) || []).length <= 15;

  function showAddressForm() {
    // New address: prefill name/mobile from the profile when blank. Both stay editable; edits keep their own values.
    if (user && !editAddressIdInput.value) {
      const n = document.getElementById('addr-fullname'), p = document.getElementById('addr-phone');
      if (!n.value) n.value = user.name || '';
      if (!p.value) p.value = user.phone || '';
    }
    addressFormPanel.style.display = 'block';
    addAddressBtn.style.display = 'none'; // trigger is hidden whenever the form is open
    addressFormPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideAddressForm() {
    addressFormPanel.style.display = 'none';
    addAddressBtn.style.display = ''; // form closed: trigger available again
    newAddressForm.reset();
    addrFields.reset();
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
    const a = addrFields.get();
    const data = {
      fullName: document.getElementById('addr-fullname').value.trim(),
      phone: document.getElementById('addr-phone').value.trim(),
      street: a.street,
      suburb: a.suburb,
      state: a.state,
      postcode: a.postcode,
      addressType: a.addressType,
    };
    if (!data.fullName || !data.phone) return toast('Please enter your name and mobile number.');
    if (!validPhone(data.phone)) return toast('Please enter a valid mobile number.');
    const problem = addrFields.check();
    if (problem) {
      toast(problem[0]);
      const el = (id => document.getElementById(id + '-cb') || document.getElementById(id))('addr-' + (problem[1][0] === 'street' ? 'street' : problem[1][0]));
      if (el) el.focus();
      return;
    }

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
  const paymentCards = document.querySelectorAll('#payment-options-list .payment-option-card');
  const currentMethod = () => (document.querySelector('input[name="payment-method"]:checked') || {}).value || null;
  function selectPayment(card) {
    paymentCards.forEach(c => {
      c.classList.remove('selected');
      const radio = c.querySelector('input[type="radio"]');
      if (radio) radio.checked = false;
    });
    card.classList.add('selected');
    const radio = card.querySelector('input[type="radio"]');
    if (radio) radio.checked = true;
    if (typeof renderTotals === 'function' && lastQuote) renderTotals(lastQuote);
  }
  // The label already toggles its radio; this keeps the .selected styling in step (keyboard arrows fire 'change').
  paymentCards.forEach(card => card.querySelector('input[type="radio"]').addEventListener('change', () => selectPayment(card)));
  if (paymentCards.length) selectPayment(paymentCards[0]);

  /* ── Delivery or free pickup (the server decides the zone and fee from the selected address) ── */
  const fulfillmentCards = document.querySelectorAll('#fulfillment-options .payment-option-card');
  const currentFulfillment = () => (document.querySelector('input[name="fulfillment"]:checked') || {}).value || 'delivery';
  const pickupPanel = document.getElementById('pickup-panel');
  const deliverySection = document.getElementById('delivery-address-section');
  const pickupName = document.getElementById('pickup-fullname');
  const pickupPhone = document.getElementById('pickup-phone');
  if (pickupName && user && !pickupName.value) pickupName.value = user.name || '';
  if (pickupPhone && user && !pickupPhone.value) pickupPhone.value = user.phone || '';
  function applyFulfillment() {
    const pickup = currentFulfillment() === 'pickup';
    fulfillmentCards.forEach(c => c.classList.toggle('selected', c.querySelector('input').checked));
    if (pickupPanel) pickupPanel.style.display = pickup ? 'block' : 'none';
    if (deliverySection) deliverySection.style.display = pickup ? 'none' : '';
    document.querySelectorAll('[data-pickup-text]').forEach(el => { el.textContent = pickup ? el.dataset.pickupText : el.dataset.deliveryText; });
    requoteIfDeliveryChanged();
  }
  fulfillmentCards.forEach(card => card.querySelector('input[type="radio"]').addEventListener('change', applyFulfillment));

  /* ── Cart & totals (prices always come from the server) ── */
  function getCart() {
    try {
      const parsed = JSON.parse(localStorage.getItem('bongo_cart'));
      if (Array.isArray(parsed)) return parsed;
    } catch (e) { /* ignore */ }
    return [];
  }
  const cartPayload = () => getCart().map(i => ({ productId: i.productId, color: i.color || null, size: i.size || null, quantity: i.quantity || 1 }));
  // Gift wrapping chosen in the cart (null when off). Only the selection is sent; the server prices it.
  const giftPayload = () => (typeof window.bongoGiftPayload === 'function' ? window.bongoGiftPayload() : null);
  // Delivery choice + which address (the server looks up the saved address itself and picks Adelaide / rest of Australia).
  const deliveryPayload = () => {
    if (currentFulfillment() === 'pickup') return { fulfillment: 'pickup' };
    const sel = addresses.find(a => a.id === selectedAddressId);
    if (!sel) return { fulfillment: 'delivery' };
    return user ? { fulfillment: 'delivery', addressId: sel.id }
      : { fulfillment: 'delivery', address: { suburb: sel.suburb, state: sel.state, postcode: sel.postcode } };
  };
  const quoteBody = extra => Object.assign({ items: cartPayload(), coupon: appliedCoupon ? appliedCoupon.code : null, gift: giftPayload() }, deliveryPayload(), extra);
  // Re-quote when the delivery method or address changes (not before the first quote).
  let quotedKey = null;
  let quoteSeq = 0;
  const deliveryKey = () => (currentFulfillment() === 'pickup' ? 'pickup' : 'delivery:' + selectedAddressId);
  function requoteIfDeliveryChanged() {
    if (quotedKey !== null && deliveryKey() !== quotedKey) renderOrderSummary();
  }

  // The cart drawer is also available on this page: re-quote when the bag or the gift selection changes there.
  let requoteTimer = null;
  document.addEventListener('bongo:cart-changed', () => {
    clearTimeout(requoteTimer);
    requoteTimer = setTimeout(() => renderOrderSummary(), 400);
  });

  async function renderOrderSummary() {
    const cart = getCart();
    if (cart.length === 0) {
      document.getElementById('checkout-gift').style.display = 'none';
      document.getElementById('gift-row').style.display = 'none';
      document.getElementById('items-total-row').style.display = 'none';
      document.getElementById('product-discount-row').style.display = 'none';
      summaryItemsCount.textContent = '0 Items';
      summaryItemsList.innerHTML = '<p style="font-size: var(--fs-small); color: var(--color-grey-500);">Your cart is empty.</p>';
      summarySubtotal.textContent = money(0);
      summaryTotal.textContent = `${money(0)} AUD`;
      placeOrderBtn.disabled = true;
      lastQuote = null;
      return;
    }

    // Same rows straight away (prices pending) so the summary does not grow when the server quote arrives.
    if (!lastQuote) {
      summaryItemsList.innerHTML = cart.map(i => `
        <div class="summary-item-row">
          <img src="${esc(window.imgUrl(i.image, 160) || window.PLACEHOLDER_IMG)}" alt="${esc(i.name)}" class="summary-item-img">
          <div class="summary-item-info">
            <div class="summary-item-title">${esc(i.name)}</div>
            <div class="summary-item-meta">Qty: ${Number(i.quantity) || 1}</div>
          </div>
          <div class="summary-item-price">…</div>
        </div>`).join('');
    }

    quotedKey = deliveryKey();
    const seq = ++quoteSeq;
    const res = await api('POST', '/checkout/quote', quoteBody());
    if (seq !== quoteSeq) return;   // a newer quote (address / method change) is on its way
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
          <img src="${esc(window.imgUrl(l.image, 160) || window.PLACEHOLDER_IMG)}" alt="${esc(l.name)}" class="summary-item-img">
          <div class="summary-item-info">
            <div class="summary-item-title">${esc(l.name)}</div>
            <div class="summary-item-meta">${variantText ? `${variantText} • ` : ''}Qty: ${l.quantity}</div>
          </div>
          <div class="summary-item-price">${l.listPrice > l.price ? `<span class="summary-item-original-price">${money(l.listPrice * l.quantity)}</span>` : ''}${money(l.price * l.quantity)}</div>
        </div>`;
    }).join('') + (q.errors || []).map(m => `<p style="font-size: var(--fs-small); color: var(--color-danger); margin-top: 0.5rem;">${esc(m)}</p>`).join('');

    renderGift(q);
    renderTotals(q);
    placeOrderBtn.disabled = q.lines.length === 0 || (q.errors || []).length > 0 || paymentCards.length === 0 || !!q.giftError
      || (!q.gift && !!(window.bongoGiftSelection && window.bongoGiftSelection().enabled));
  }

  // Gift section of the order summary. Everything shown comes from the server quote (selection, quantities, fee), so it is
  // exactly what will be saved and charged. It is always visible: either the gift details or a plain "not selected".
  function renderGift(q) {
    const box = document.getElementById('checkout-gift');
    const row = document.getElementById('gift-row');
    const gift = q.gift;
    const total = q.lines.reduce((a, l) => a + l.quantity, 0);
    const local = typeof window.bongoGiftSelection === 'function' ? window.bongoGiftSelection() : { enabled: false };
    box.style.display = 'block';
    box.classList.toggle('is-gift', !!gift);
    const editLink = label => `<button type="button" class="checkout-gift-edit" id="checkout-gift-edit">${label}</button>`;
    if (q.giftError || (!gift && local.enabled)) {
      // Gift switched on in the cart but nothing valid selected, or the selection no longer matches the cart.
      box.innerHTML = `
        <div class="checkout-gift-head"><span>${SVG_ICONS.gift} Gift wrapping</span>${editLink('Review in cart')}</div>
        <p class="checkout-gift-warn">${esc(q.giftError || 'Gift wrapping is switched on but no items are selected. Choose the items to wrap, or switch it off.')}</p>`;
      row.style.display = 'none';
    } else if (!gift) {
      box.innerHTML = `
        <div class="checkout-gift-head"><span>${SVG_ICONS.gift} Gift wrapping</span>${editLink('Add in cart')}</div>
        <p class="checkout-gift-none">Not selected: this order will not be gift wrapped.</p>`;
      row.style.display = 'none';
    } else {
      const variant = i => [i.size, i.color].filter(Boolean).join(' / ');
      box.innerHTML = `
        <div class="checkout-gift-head"><span>${SVG_ICONS.gift} This order is a gift</span>${editLink('Edit in cart')}</div>
        <p class="checkout-gift-count"><strong>${gift.quantity} of ${total} item${total === 1 ? '' : 's'}</strong> will be gift wrapped (${money(q.giftFee)}):</p>
        <ul>${gift.items.map(i => `<li><span class="gift-qty">${i.quantity} &times;</span> ${esc(i.name)}${variant(i) ? ` <small>(${esc(variant(i))})</small>` : ''}</li>`).join('')}</ul>
        ${gift.message ? `<div class="checkout-gift-message"><span>Gift message</span>&ldquo;${esc(gift.message)}&rdquo;</div>` : '<p class="checkout-gift-none">No gift message.</p>'}`;
      row.style.display = 'flex';
      document.getElementById('gift-row-label').textContent = `Gift wrapping (${gift.quantity} item${gift.quantity === 1 ? '' : 's'})`;
      document.getElementById('summary-gift').textContent = money(q.giftFee);
    }
    const edit = document.getElementById('checkout-gift-edit');
    if (edit) edit.addEventListener('click', () => { if (typeof openCart === 'function') openCart(); });
  }


  // Totals for the selected method. All figures come from the server quote (q.online is the pre-booking breakdown
  // computed by OnlinePaymentPlan); nothing is calculated or hard-coded here.
  const pctText = n => String(Number(n));
  function renderTotals(q) {
    const online = currentMethod() === 'card' && q.online;
    const onlineDiscountRow = document.getElementById('online-discount-row');
    const breakdown = document.getElementById('online-breakdown');
    // Product discount = (original - selling) x qty per line, in cents; display only. Subtotal stays the server's figure
    // (sum of selling prices), and Total is Subtotal + Product Discount, so the rows always add up.
    const productDiscount = q.lines.reduce((a, l) => a + Math.max(0, Math.round(l.listPrice * 100) - Math.round(l.price * 100)) * l.quantity, 0) / 100;
    document.getElementById('items-total-row').style.display = productDiscount > 0 ? 'flex' : 'none';
    document.getElementById('product-discount-row').style.display = productDiscount > 0 ? 'flex' : 'none';
    document.getElementById('summary-items-total').textContent = money((Math.round(q.subtotal * 100) + Math.round(productDiscount * 100)) / 100);
    document.getElementById('summary-product-discount').textContent = `-${money(productDiscount)}`;
    summarySubtotal.textContent = money(q.subtotal);
    summaryShipping.textContent = q.shipping === 0 ? 'FREE' : money(q.shipping);
    const d = q.delivery || {};
    document.getElementById('summary-shipping-label').textContent = d.label
      ? 'Shipping (' + (d.fulfillment === 'delivery' && d.addressNeeded ? 'choose an address' : d.label) + ')' : 'Shipping';
    if (q.discount > 0) {
      discountRow.style.display = 'flex';
      summaryDiscount.textContent = `-${money(q.discount)}`;
    } else {
      discountRow.style.display = 'none';
    }
    if (online) {
      onlineDiscountRow.style.display = 'flex';
      document.getElementById('online-discount-label').textContent = `Online payment discount (${pctText(q.online.discountPercent)}%)`;
      document.getElementById('summary-online-discount').textContent = `-${money(q.online.discount)}`;
      document.getElementById('summary-total-label').textContent = 'Final order total';
      summaryTotal.textContent = `${money(q.online.total)} AUD`;
      breakdown.style.display = 'block';
      document.getElementById('pay-now-label').textContent = `Deposit payable now (${pctText(q.online.depositPercent)}%)`;
      document.getElementById('summary-pay-now').textContent = money(q.online.deposit);
      document.getElementById('balance-label').textContent = `Payable later (${pctText(q.online.balancePercent)}%)`;
      document.getElementById('summary-balance').textContent = money(q.online.balance);
      placeOrderBtn.textContent = `Pay ${money(q.online.deposit)} deposit & pre-book`;
    } else {
      onlineDiscountRow.style.display = 'none';
      breakdown.style.display = 'none';
      document.getElementById('summary-total-label').textContent = 'Total payable';   // "Total" is the pre-discount row above
      summaryTotal.textContent = `${money(q.total)} AUD`;
      placeOrderBtn.textContent = `Place Order • ${money(q.total)} AUD`;
    }
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

    const res = await api('POST', '/checkout/quote', quoteBody({ coupon: code }));
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
  let placing = false;   // one placement at a time; cleared when the request fails, so a genuine retry is never blocked
  placeOrderBtn.addEventListener('click', async () => {
    if (placing) return;
    if (getCart().length === 0) return toast('Your cart is empty');
    const pickup = currentFulfillment() === 'pickup';
    const contact = pickup ? { fullName: pickupName.value.trim(), phone: pickupPhone.value.trim() } : null;
    if (pickup && (!contact.fullName || !contact.phone)) {
      (contact.fullName ? pickupPhone : pickupName).focus();
      return toast('Please enter the name and mobile number of the person collecting the order');
    }
    if (pickup && !validPhone(contact.phone)) {
      pickupPhone.focus();
      return toast('Please enter a valid mobile number');
    }
    const selected = pickup ? null : addresses.find(a => a.id === selectedAddressId);
    if (!pickup && !selectedAddressId) return toast('Please select a delivery address');
    if (!pickup && !selected) return toast('Please choose a valid delivery address');
    const method = (document.querySelector('input[name="payment-method"]:checked') || {}).value;
    if (!method) return toast('Please choose a payment method');

    placing = true;
    const label = placeOrderBtn.innerHTML;
    placeOrderBtn.disabled = true;
    placeOrderBtn.setAttribute('aria-busy', 'true');
    placeOrderBtn.dataset.busyText = method === 'card' ? 'Preparing secure payment…' : 'Placing your order…';
    placeOrderBtn.innerHTML = '<svg class="icon-svg" style="animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle style="opacity: 0.25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path style="opacity: 0.75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> ' + placeOrderBtn.dataset.busyText;

    const body = quoteBody({ paymentMethod: method });
    delete body.address;
    delete body.addressId;
    if (pickup) body.contact = contact;
    else if (user) body.addressId = selected.id; else body.address = selected;
    const res = await api('POST', '/checkout/place', body);

    if (!res.ok) {
      placing = false;
      placeOrderBtn.removeAttribute('aria-busy');
      placeOrderBtn.innerHTML = label;
      placeOrderBtn.disabled = false;
      toast(window.bongoApiMessage(res));
      renderOrderSummary();
      return;
    }

    // Card (Stripe Checkout): the order is saved unpaid and the bag is kept (NOT cleared here) until Stripe's verified
    // webhook confirms the deposit; the payment-result page then clears it. A declined or cancelled payment keeps the bag.
    if (method === 'card') {
      if (res.data.redirectUrl) {
        placeOrderBtn.innerHTML = 'Redirecting to secure payment...';
        window.location.href = res.data.redirectUrl;
        return;
      }
      // Payment page could not be opened: the order exists, pay from its page (never place it twice).
      // The order is saved (and the bag kept): say so plainly, then open the order, where Pay now completes it. Placing again would be a second order.
      placeOrderBtn.innerHTML = 'Order saved. Opening your order…';
      toast(`Your order ${res.data.orderNumber} is saved, but we couldn't open the payment page. You can pay from your order page; no need to order again.`);
      setTimeout(() => { window.location.href = res.data.orderUrl || '/account#orders'; }, 2500);
      return;
    }

    // COD: the order exists, so the bag is now spent.
    showOrderSuccess(res.data);
  });

  /* The order success modal: COD right after placing, and card orders on return from Stripe once the server has a
     Stripe-confirmed payment (#checkout-placed, rendered by the server for /checkout?paid=<order>). */
  function showOrderSuccess(data) {
    // The bag became this order: clear it (in-memory, localStorage and the saved server bag), once per order. This modal is
    // also rendered on every refresh/revisit of /checkout?paid=<order>, which must not wipe a bag built after that order.
    let alreadyCleared = false;
    try { alreadyCleared = localStorage.getItem('bongo_cleared_order') === String(data.orderNumber); } catch (e) { /* storage unavailable */ }
    if (!alreadyCleared) {
      if (typeof window.bongoClearCart === 'function') window.bongoClearCart();
      else { try { localStorage.setItem('bongo_cart', JSON.stringify([])); } catch (e) { /* storage unavailable */ } }
      try { localStorage.setItem('bongo_cleared_order', String(data.orderNumber)); } catch (e) { /* storage unavailable */ }
    }
    document.getElementById('success-order-ref').textContent = `#${data.orderNumber}`;
    document.getElementById('success-address-label').textContent = data.pickup ? 'Pickup:' : 'Delivering to:';
    document.getElementById('success-address-summary').textContent = data.address;
    document.getElementById('success-paid-amount').textContent = `${money(data.total)} AUD`;
    const cardRows = document.getElementById('success-card-rows');
    if (cardRows && data.paid > 0) {
      document.getElementById('success-payment-status').textContent = data.paymentStatus;
      document.getElementById('success-card-paid').textContent = `${money(data.paid)} AUD`;
      document.getElementById('success-balance-label').textContent = data.pickup ? 'Remaining (due on collection):' : 'Remaining (due on delivery):';
      document.getElementById('success-balance-amount').textContent = `${money(data.balance)} AUD`;
      document.getElementById('success-balance-row').hidden = !(data.balance > 0);
      cardRows.hidden = false;
    }
    const viewOrder = document.getElementById('success-view-order');
    if (viewOrder && data.orderNumber) viewOrder.href = `/account/orders/${encodeURIComponent(data.orderNumber)}`;
    successModalOverlay.classList.add('active');
    toast('Order placed successfully!');
  }

  const placedEl = document.getElementById('checkout-placed');
  if (placedEl) {
    try { showOrderSuccess(JSON.parse(placedEl.textContent)); } catch (e) { /* malformed: plain checkout */ }
  }

  const breadcrumbCartLink = document.getElementById('breadcrumb-cart-link');
  if (breadcrumbCartLink) {
    breadcrumbCartLink.addEventListener('click', e => { e.preventDefault(); if (typeof openCart === 'function') openCart(); });
  }

  /* ── Initial render ── */
  (async function init() {
    if (user) {
      // Embedded by the server (no extra request before the first render); fetched only if missing.
      let embedded = null;
      try { embedded = JSON.parse((document.getElementById('checkout-addresses') || {}).textContent || 'null'); } catch (e) { embedded = null; }
      if (Array.isArray(embedded)) {
        addresses = embedded;
      } else {
        const res = await api('GET', '/account/addresses');
        addresses = res.ok ? res.data : [];
      }
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
