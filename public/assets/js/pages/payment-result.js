// Stripe return page: polls our own payment status (set only by the verified webhook) while "confirming", and handles
// "Pay now" (retry: same order, Stripe session reused or replaced) and "Cancel order".
document.addEventListener('DOMContentLoaded', () => {
  const box = document.getElementById('payment-result');
  if (!box) return;
  const order = box.dataset.order;
  const enc = encodeURIComponent(order);

  // Only a payment confirmed by the verified webhook renders data-clear-cart=1: the browser-persisted bag (and, through
  // it, the saved server bag) is emptied now. Failed / cancelled / still-confirming pages never clear it.
  if (box.dataset.clearCart === '1' && typeof window.bongoClearCart === 'function') window.bongoClearCart();

  if (box.dataset.state === 'confirming') {
    let tries = 0;
    const poll = async () => {
      tries += 1;
      const res = await window.bongoApi('GET', `/account/orders/${enc}/payment-status`);
      if (res.ok && (res.data.received || res.data.paymentStatus === 'paid')) {
        // Reload: the server renders "paid", and the saved bag (converted at confirmation) is now empty.
        window.location.reload();
        return;
      }
      if (tries < 20) {
        setTimeout(poll, 3000);
      } else {
        document.getElementById('payment-text').textContent =
          'Your payment is still being confirmed. You can safely leave this page: your order page will update once it is confirmed.';
      }
    };
    setTimeout(poll, 2000);
  }

  const retry = document.getElementById('payment-retry-btn');
  if (retry) {
    retry.addEventListener('click', async () => {
      retry.disabled = true;
      const label = retry.textContent;
      retry.textContent = 'Opening secure payment…';
      const res = await window.bongoApi('POST', `/account/orders/${enc}/pay`, {});
      if (res.ok && res.data.redirectUrl) {
        window.location.href = res.data.redirectUrl;
        return;
      }
      retry.disabled = false;
      retry.textContent = label;
      showToast(window.bongoApiMessage(res));
      if (res.status === 409 || res.status === 422) setTimeout(() => window.location.reload(), 1500);
    });
  }

  const cancel = document.getElementById('payment-cancel-order-btn');
  if (cancel) {
    cancel.addEventListener('click', async () => {
      if (!window.confirm('Cancel this order? Your bag stays saved so you can check out again.')) return;
      cancel.disabled = true;
      const res = await window.bongoApi('POST', `/account/orders/${enc}/cancel`, {});
      if (res.ok) {
        showToast('Your order has been cancelled.');
        window.location.href = '/checkout';
        return;
      }
      cancel.disabled = false;
      showToast(window.bongoApiMessage(res));
    });
  }
});
