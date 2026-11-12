// Contact form → POST /contact (stored in contact_messages; spam honeypot + server-side validation).
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contact-form');
  if (!form) return;
  const status = document.getElementById('contact-status');
  const btn = document.getElementById('contact-submit');
  const val = name => (form.elements[name] ? form.elements[name].value.trim() : '');
  const show = (text, ok) => { status.textContent = text; status.className = 'form-status ' + (ok ? 'is-ok' : 'is-error'); };

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!val('name') || !val('email') || val('message').length < 10) {
      show('Please add your name, email and a message of at least 10 characters.', false);
      return;
    }
    btn.disabled = true;
    const res = await window.bongoApi('POST', '/contact', {
      name: val('name'), email: val('email'), phone: val('phone'), orderNumber: val('orderNumber'),
      subject: val('subject'), message: val('message'), company_url: val('company_url'),
    });
    btn.disabled = false;
    if (!res.ok) { show(window.bongoApiMessage(res), false); return; }
    form.reset();
    show(res.data.message || 'Thank you, your message has been sent.', true);
  });
});
