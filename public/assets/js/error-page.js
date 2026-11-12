// Error pages (standalone layout, no app scripts): "Go Back" returns to the previous page when there is one.
document.addEventListener('click', e => {
  const el = e.target.closest && e.target.closest('[data-action="history-back"]');
  if (el && history.length > 1) { e.preventDefault(); history.back(); }
});
