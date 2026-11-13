/* ══════════════════════════════════════════════════════════
   /verify-email — 4-digit code entry, resend countdown, change email.
   The server decides everything (codes, limits, destination); this only drives the form.
   ══════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  const card = document.getElementById('verify-card');
  const form = document.getElementById('verify-form');
  if (!card || !form) return; // expired-session view has nothing to drive

  const boxes = Array.from(document.querySelectorAll('.otp-input'));
  const group = document.getElementById('otp-inputs');
  const submitBtn = document.getElementById('verify-submit');
  const submitLabel = submitBtn.querySelector('.verify-submit-label');
  const errorEl = document.getElementById('verify-error');
  const successEl = document.getElementById('verify-success');
  const resendBtn = document.getElementById('verify-resend');
  const timerEl = document.getElementById('verify-timer');
  const emailEl = document.getElementById('verify-email-address');
  const changeToggle = document.getElementById('verify-change-toggle');
  const changeForm = document.getElementById('verify-change-form');
  const changeInput = document.getElementById('verify-new-email');
  const changeSubmit = document.getElementById('verify-change-submit');

  let busy = false;
  let done = false;

  /* ── messages ── */
  function show(el, text) { el.textContent = text; el.style.display = text ? 'block' : 'none'; }
  function error(text) { show(successEl, ''); show(errorEl, text); }
  function success(text) { show(errorEl, ''); show(successEl, text); }

  /* ── requests (same-origin JSON, CSRF header, one retry after a 419 token refresh) ── */
  let csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  async function post(url, body, retried) {
    let res;
    try {
      res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(body || {}),
      });
    } catch (e) {
      return { status: 0, data: { message: 'Connection problem. Please check your internet and try again.' } };
    }
    if (res.status === 419 && !retried) {
      try {
        const t = await fetch('/auth/csrf', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        csrf = (await t.json()).csrf || csrf;
      } catch (e) { /* fall through */ }
      return post(url, body, true);
    }
    let data = {};
    try { data = await res.json(); } catch (e) { /* empty body */ }
    if (!data.message && res.status === 422 && data.errors) data.message = Object.values(data.errors)[0][0];
    if (!data.message && !res.ok) data.message = 'Something went wrong. Please try again.';
    return { status: res.status, data };
  }

  /* Destination comes from the server (already validated); still only ever follow a same-site path. */
  function go(path) {
    const p = typeof path === 'string' && path.startsWith('/') && !path.startsWith('//') && !/[\\\s]/.test(path) ? path : '/account';
    window.location.assign(p);
  }

  /* ── OTP boxes ── */
  const code = () => boxes.map(b => b.value).join('');
  function fill(digits, from) {
    let i = from;
    for (const d of digits) { if (i >= boxes.length) break; boxes[i].value = d; i++; }
    boxes[Math.min(i, boxes.length - 1)].focus();
    if (code().length === boxes.length) submit();
  }
  function clearBoxes() { boxes.forEach(b => { b.value = ''; }); boxes[0].focus(); }
  function setState(cls) { group.classList.remove('is-error', 'is-success'); if (cls) { void group.offsetWidth; group.classList.add(cls); } }

  boxes.forEach((box, idx) => {
    box.addEventListener('input', () => {
      setState('');
      const digits = box.value.replace(/\D/g, '');
      if (digits.length > 1) { box.value = ''; fill(digits, idx); return; } // autofill / IME put several digits in one box
      box.value = digits;
      if (digits && idx < boxes.length - 1) boxes[idx + 1].focus();
      if (code().length === boxes.length) submit();
    });
    box.addEventListener('keydown', (e) => {
      if (e.key === 'Backspace' && !box.value && idx > 0) { e.preventDefault(); boxes[idx - 1].value = ''; boxes[idx - 1].focus(); }
      else if (e.key === 'ArrowLeft' && idx > 0) { e.preventDefault(); boxes[idx - 1].focus(); }
      else if (e.key === 'ArrowRight' && idx < boxes.length - 1) { e.preventDefault(); boxes[idx + 1].focus(); }
      else if (e.key.length === 1 && !/\d/.test(e.key) && !e.ctrlKey && !e.metaKey) e.preventDefault();
    });
    box.addEventListener('paste', (e) => {
      const digits = ((e.clipboardData || window.clipboardData).getData('text') || '').replace(/\D/g, '').slice(0, boxes.length);
      e.preventDefault();
      if (digits) fill(digits, digits.length === boxes.length ? 0 : idx);
    });
    box.addEventListener('focus', () => box.select());
  });

  /* ── submit ── */
  function loading(on) {
    busy = on;
    submitBtn.disabled = on;
    submitBtn.classList.toggle('is-loading', on);
    submitLabel.textContent = on ? 'Verifying…' : 'Verify Email';
    boxes.forEach(b => { b.readOnly = on; });
  }

  async function submit() {
    if (busy || done) return; // no duplicate submissions
    const value = code();
    if (!/^\d{4}$/.test(value)) { setState('is-error'); error('Enter the 4-digit code from the email.'); return; }
    loading(true);
    const { status, data } = await post('/verify-email', { code: value });
    if (status === 200 && data.verified) {
      done = true;
      setState('is-success');
      submitBtn.classList.remove('is-loading');
      submitLabel.textContent = 'Verified';
      success('Email verified! Redirecting you now…');
      setTimeout(() => go(data.redirect), 900);
      return;
    }
    loading(false);
    setState('is-error');
    error(data.message);
    if (data.expired) { setTimeout(() => window.location.reload(), 1800); return; }
    clearBoxes();
    if (data.codeExpired && remaining === 0) resendBtn.focus();
  }

  form.addEventListener('submit', (e) => { e.preventDefault(); submit(); });

  /* ── resend countdown ── */
  let remaining = parseInt(card.dataset.cooldown || '0', 10) || 0;
  let timer = null;
  function tick() {
    if (remaining > 0) {
      resendBtn.disabled = true;
      timerEl.textContent = `in ${remaining}s`;
      remaining--;
      return;
    }
    resendBtn.disabled = false;
    timerEl.textContent = '';
    clearInterval(timer); timer = null;
  }
  function countdown(seconds) {
    remaining = Math.max(0, parseInt(seconds, 10) || 0);
    clearInterval(timer);
    tick();
    if (!resendBtn.disabled) return; // nothing to wait for
    timer = setInterval(tick, 1000);
  }
  countdown(remaining);

  resendBtn.addEventListener('click', async () => {
    if (resendBtn.disabled || done) return;
    resendBtn.disabled = true;
    const { status, data } = await post('/verify-email/resend');
    if (status === 200 && data.verified) { done = true; success(data.message); setTimeout(() => go(data.redirect), 900); return; }
    if (data.expired) { error(data.message); setTimeout(() => window.location.reload(), 1800); return; }
    (status === 200 ? success : error)(data.message);
    countdown(data.retryAfter || (status === 200 ? 60 : 0));
    if (status === 200) { setState(''); clearBoxes(); }
  });

  /* ── change email ── */
  changeToggle.addEventListener('click', () => {
    const open = changeForm.hidden;
    changeForm.hidden = !open;
    changeToggle.setAttribute('aria-expanded', String(open));
    if (open) changeInput.focus();
  });

  changeForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (done) return;
    const email = changeInput.value.trim();
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { error('Enter a valid email address.'); changeInput.focus(); return; }
    changeSubmit.disabled = true;
    const { status, data } = await post('/verify-email/email', { email });
    changeSubmit.disabled = false;
    if (status === 200 && data.verified) { done = true; success(data.message); setTimeout(() => go(data.redirect), 900); return; }
    if (data.expired) { error(data.message); setTimeout(() => window.location.reload(), 1800); return; }
    if (status !== 200) { error(data.message); if (data.retryAfter) countdown(data.retryAfter); return; }
    emailEl.textContent = data.email;
    changeInput.value = '';
    changeForm.hidden = true;
    changeToggle.setAttribute('aria-expanded', 'false');
    success(data.message);
    countdown(data.retryAfter || 60);
    setState(''); clearBoxes();
  });

  boxes[0].focus();
})();
