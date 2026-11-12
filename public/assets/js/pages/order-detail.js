// Order details page: everything is server-rendered; this only handles customer cancellation.
document.addEventListener('DOMContentLoaded', () => {
  const btn = document.getElementById('order-cancel-btn');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    if (!window.confirm('Cancel this order? This cannot be undone.')) return;
    btn.disabled = true;
    btn.textContent = 'Cancelling…';
    const res = await window.bongoApi('POST', `/account/orders/${encodeURIComponent(btn.dataset.order)}/cancel`, {});
    if (res.ok) {
      showToast('Your order has been cancelled.');
      window.location.reload();
      return;
    }
    btn.disabled = false;
    btn.textContent = 'Cancel Order';
    showToast(window.bongoApiMessage(res));
    if (res.status === 422) window.location.reload(); // status changed meanwhile: show the current state
  });
});
