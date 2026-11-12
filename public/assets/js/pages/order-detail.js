// Order details page: everything is server-rendered; this only handles cancellation and return requests.
document.addEventListener('DOMContentLoaded', () => {
  const returnForm = document.getElementById('order-return-form');
  if (returnForm) {
    returnForm.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = document.getElementById('order-return-btn');
      btn.disabled = true;
      const res = await window.bongoApi('POST', `/account/orders/${encodeURIComponent(returnForm.dataset.order)}/return`, {
        reason: returnForm.elements.reason.value, details: returnForm.elements.details.value,
      });
      btn.disabled = false;
      showToast(res.ok ? res.data.message : window.bongoApiMessage(res));
      if (res.ok || res.status === 422) setTimeout(() => window.location.reload(), 1500);
    });
  }

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
