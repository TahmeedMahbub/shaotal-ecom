// Customer journey regression (Part 8): headless Chrome over CDP, no packages. Walks the whole COD purchase path the
// way a customer does and checks what they see at each step.
//
//   BASE=http://127.0.0.1:8124 node tests/browser/journey.mjs
//
// LOCAL / CI *_test DATABASES ONLY (sql/local_test_seed.sql + php tests/Support/browser-fixtures.php). It creates test
// rows: one new customer (journey-<time>@example.test), one COD order that it then cancels, one sourcing request with a
// photo and one newsletter address. Prints `ORDER <number>` and `CUSTOMER <email>` so the caller can inspect or delete
// them. Exit code 1 on any failure; screenshots of failed checks go to tests/browser/artifacts/.
import { mkdirSync } from 'node:fs';
import { launch } from './cdp.mjs';

const BASE = process.env.BASE || 'http://127.0.0.1:8124';
const ARTIFACTS = new URL('./artifacts/', import.meta.url).pathname;
const results = [];
let p;
const check = async (name, ok, extra = '') => {
  results.push(!!ok);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
  if (!ok && p) {
    try { mkdirSync(ARTIFACTS, { recursive: true }); await p.shot(`${ARTIFACTS}journey-fail-${results.length}.png`); } catch (e) { /* best effort */ }
  }
};
const wait = ms => new Promise(r => setTimeout(r, ms));
const clean = async (label, mark) => {
  const r = await p.eval(`({ csp: __csp, overflow: document.documentElement.scrollWidth > window.innerWidth })`);
  const errs = p.errors(mark);
  await check(`${label}: no JS errors, CSP violations or overflow`, !errs.length && !r.csp.length && !r.overflow, JSON.stringify({ errs, ...r }));
};
const cart = () => p.eval(`JSON.parse(localStorage.getItem('bongo_cart') || '[]')`);
const requests = (since, re) => p.events.slice(since).filter(e => e.method === 'Network.requestWillBeSent' && e.params.request.method === 'POST' && re.test(e.params.request.url)).length;

p = await launch(Number(process.env.CDP_PORT || 9370));
await p.send('Page.addScriptToEvaluateOnNewDocument', { source: `window.__csp=[];document.addEventListener('securitypolicyviolation',e=>__csp.push(e.violatedDirective+' '+e.blockedURI));window.confirm=()=>true;` });
const email = `journey-${Date.now()}@example.test`;
let orderNumber = null;

try {
  await p.viewport(1280, 900);

  // 1. Registration: weak password refused, a valid one signs the new customer in; sign out again.
  await p.goto(BASE + '/', 1500);
  await p.eval(`localStorage.clear(); sessionStorage.clear(); true`);
  const weak = await p.eval(`registerUser('Journey Tester', '${email}', 'short7!')`);
  await check('register: a 7-character password is refused', !weak.success, JSON.stringify(weak));
  const reg = await p.eval(`registerUser('Journey Tester', '${email}', 'Journey-Pass-123')`);
  await check('register: new account is signed in', reg.success && reg.user && reg.user.email === email, JSON.stringify(reg));
  await p.goto(BASE + '/account', 1500);
  await check('register: account page shows the signed-in view', (await p.eval(`getComputedStyle(document.getElementById('account-auth-view')).display`)) === 'grid');
  const dup = await p.eval(`(async () => { await logoutUser(); return registerUser('Again', '${email}', 'Journey-Pass-123'); })()`);
  await check('register: the same email cannot register twice', !dup.success, JSON.stringify(dup));
  await p.goto(BASE + '/', 1000);
  await p.eval(`localStorage.clear(); true`);

  // 2. Browse as a guest: home → category (server link) → product → add to cart; variant product with options.
  await p.goto(BASE + '/', 1500);
  const catHref = await p.eval(`(document.querySelector('a[href^="/products?category="]') || {}).getAttribute?.('href') || ''`);
  await check('browse: home links to a category', /category=/.test(catHref), catHref);
  await p.goto(BASE + catHref, 1500);
  const cards = await p.eval(`document.querySelectorAll('#listing-products-grid .product-card').length`);
  await check('browse: category lists products', cards > 0, String(cards));
  await p.goto(BASE + '/products/nakshi-kantha-cushion', 1500);
  const mark1 = p.events.length;
  await p.eval(`document.getElementById('detail-add-to-cart-btn').click(); true`);
  await wait(700);
  // Pages embed only the products they render, so the variant product is added from its own page.
  await p.goto(BASE + '/products/essential-heavyweight-tee', 1500);
  await p.eval(`addToCart(1, '#5C6B4F', 'M', 2, false); true`); // colours are keyed by hex (Olive Green, M)
  await wait(500);
  const c1 = await cart();
  await check('cart: simple + variant lines added as a guest', c1.length === 2 && c1.reduce((s, i) => s + (i.quantity || 1), 0) === 3, JSON.stringify(c1.map(i => [i.productId, i.color, i.size, i.quantity])));
  await clean('product page', mark1);

  // 3. Checkout as a guest sends the customer to sign in, then back to checkout with the cart merged.
  // The page script removes ?signin= from the address bar once the modal is open, so read the redirect itself.
  const redirectTo = (since, from) => (p.events.slice(since).find(e => e.method === 'Network.requestWillBeSent' && e.params.redirectResponse && e.params.redirectResponse.url.endsWith(from)) || { params: { request: {} } }).params.request.url || '';
  const markR = p.events.length;
  await p.goto(BASE + '/checkout', 1500);
  const signinUrl = redirectTo(markR, '/checkout');
  await check('checkout: guest is sent to sign in with a return path', signinUrl === BASE + '/?signin=/checkout', signinUrl);
  await check('checkout: sign-in modal opens', await p.eval(`!!document.querySelector('#auth-modal-overlay.open')`));
  const login = await p.eval(`loginUser('perf@example.test', 'Password123')`);
  await check('sign-in: fixture customer signs in', login.success, JSON.stringify(login));
  await p.goto(BASE + '/checkout', 2500);
  const mark2 = p.events.length;
  const co = await p.eval(`({ path: location.pathname, lines: document.querySelectorAll('#checkout-items-list > *, .checkout-item').length, addresses: document.querySelectorAll('#address-cards-grid .address-card').length, methods: [...document.querySelectorAll('input[name="payment-method"]')].map(i => i.value) })`);
  await check('checkout: cart, saved address and only COD offered', co.path === '/checkout' && co.addresses >= 1 && co.methods.join() === 'cod', JSON.stringify(co));
  const c2 = await cart();
  await check('checkout: guest cart survived sign-in', c2.length >= 2, String(c2.length));

  // 4. Coupon, then place the order with a double click: exactly one request is accepted, success dialog shows.
  await p.eval(`document.getElementById('coupon-toggle-btn').click(); document.getElementById('coupon-code-input').value = '10AUD'; document.getElementById('apply-coupon-btn').click(); true`);
  await wait(1200);
  await check('checkout: coupon 10AUD applied', await p.eval(`getComputedStyle(document.getElementById('coupon-applied-box') || document.body).display !== 'none' && /10AUD/i.test(document.body.innerText)`));
  await p.eval(`(document.querySelector('#address-cards-grid .address-card')).click(); document.getElementById('pay-cod').checked = true; true`);
  await wait(300);
  const mark3 = p.events.length;
  await p.eval(`const b = document.getElementById('place-order-btn'); b.click(); b.click(); true`);
  await wait(3000);
  const ok = await p.eval(`({ open: document.getElementById('success-modal-overlay').classList.contains('active'), ref: document.getElementById('success-order-ref').textContent, total: document.getElementById('success-paid-amount').textContent, label: document.querySelector('.success-modal-card').innerText.includes('Order Total'), cart: JSON.parse(localStorage.getItem('bongo_cart') || '[]').length, href: document.getElementById('success-view-order').getAttribute('href') })`);
  orderNumber = (ok.ref || '').replace('#', '');
  await check('order: success dialog with order number, "Order Total" (not paid), cart emptied', ok.open && /^INV-\d{6}$/.test(orderNumber) && ok.label && ok.cart === 0, JSON.stringify(ok));
  await check('order: double click sent one place request', requests(mark3, /\/checkout\/place$/) === 1, String(requests(mark3, /\/checkout\/place$/)));
  await clean('checkout', mark2);
  console.log(`ORDER ${orderNumber}`);

  // 5. "View My Orders" opens the new order: pending, COD, AEST/AEDT time, two items, no return form (returns off).
  await p.goto(BASE + ok.href, 1500);
  const mark4 = p.events.length;
  const od = await p.eval(`({ path: location.pathname, badge: document.getElementById('order-status-badge').textContent.trim(), pay: document.getElementById('order-payment-status').textContent.trim(), text: document.body.innerText, ret: !!document.getElementById('order-return-form'), cancel: !!document.getElementById('order-cancel-btn'), robots: document.querySelector('meta[name="robots"]').content })`);
  await check('order page: opened from the success dialog', od.path === '/account/orders/' + orderNumber, od.path);
  // "Order Placed" is the customer-facing label for order_status = pending (App\Support\OrderStatus).
  // COD payment_status = pending is shown as "Pay on delivery".
  await check('order page: Order Placed, Cash on Delivery, "Pay on delivery", time in AEST/AEDT', od.badge === 'Order Placed' && /Cash on Delivery/.test(od.text) && od.pay === 'Pay on delivery' && /AE[DS]T/.test(od.text), JSON.stringify({ badge: od.badge, pay: od.pay }));
  await check('order page: lists both products and the coupon', /Nakshi Kantha Cushion/.test(od.text) && /Essential Heavyweight/.test(od.text) && /Discount \(10AUD\)/i.test(od.text));
  await check('order page: no return form (returns are off), cancel offered, noindex', !od.ret && od.cancel && /noindex/.test(od.robots), JSON.stringify({ ret: od.ret, cancel: od.cancel, robots: od.robots }));
  await clean('order page', mark4);
  await p.goto(BASE + '/account#orders', 2000);
  await check('account: order history lists the new order', await p.eval(`document.body.innerText.includes('${orderNumber}')`));

  // 6. Customer cancels while pending; the page reloads as Cancelled without the cancel button.
  await p.goto(BASE + '/account/orders/' + orderNumber, 1500);
  await p.eval(`document.getElementById('order-cancel-btn').click(); true`);
  await wait(3000);
  const oc = await p.eval(`({ badge: document.getElementById('order-status-badge').textContent.trim(), cancel: !!document.getElementById('order-cancel-btn') })`);
  await check('cancel: order shows Cancelled and can no longer be cancelled', /cancel/i.test(oc.badge) && !oc.cancel, JSON.stringify(oc));

  // 7. Sourcing request with a real image file through the form, signed in.
  await p.goto(BASE + '/request', 1500);
  const mark5 = p.events.length;
  await p.eval(`(async () => {
    const c = document.createElement('canvas'); c.width = 64; c.height = 64; const g = c.getContext('2d'); g.fillStyle = '#5C6B4F'; g.fillRect(0, 0, 64, 64);
    const blob = await new Promise(r => c.toBlob(r, 'image/png'));
    const dt = new DataTransfer(); dt.items.add(new File([blob], 'journey.png', { type: 'image/png' }));
    const input = document.getElementById('request-file-input'); input.files = dt.files; input.dispatchEvent(new Event('change'));
    document.getElementById('request-item-name').value = 'Journey test item';
    document.getElementById('request-quantity').value = '2';
    document.getElementById('request-name').value = 'Journey Tester';
    document.getElementById('request-contact').value = 'perf@example.test';
    document.getElementById('request-postcode').value = '3053';
    document.getElementById('sourcing-request-form').requestSubmit();
    return true;
  })()`);
  await wait(3000);
  await check('sourcing: request with a photo is accepted', await p.eval(`getComputedStyle(document.getElementById('request-success-wrapper')).display !== 'none'`));
  await clean('/request', mark5);

  // 8. Newsletter from the footer.
  await p.goto(BASE + '/', 1200);
  await p.eval(`const f = document.getElementById('newsletter-form'); f.querySelector('.newsletter-input').value = 'journey-news-${Date.now()}@example.test'; f.requestSubmit ? f.requestSubmit() : f.dispatchEvent(new Event('submit', { cancelable: true })); true`);
  await wait(1500);
  await check('newsletter: subscription accepted', await p.eval(`/subscrib|thank/i.test(document.body.innerText)`));

  // 9. Mobile width: the order page and checkout have no overflow or errors.
  await p.viewport(375, 812, true);
  for (const path of ['/account/orders/' + orderNumber, '/account', '/checkout']) {
    const m = p.events.length;
    await p.goto(BASE + path, 1500);
    await clean(`375px ${path}`, m);
  }
  await p.viewport(1280, 900);

  // 10. Sign out: private data is gone from the page and the API refuses it.
  await p.goto(BASE + '/account', 1200);
  await p.eval(`logoutUser(); true`);
  await wait(1500);
  const out = await p.eval(`fetch('/account/orders', { headers: { Accept: 'application/json' } }).then(r => r.status)`);
  await check('sign-out: order API returns 401', out === 401, String(out));
  const markO = p.events.length;
  await p.goto(BASE + '/account/orders/' + orderNumber, 1200);
  const outUrl = redirectTo(markO, '/account/orders/' + orderNumber);
  await check('sign-out: order page sends the guest to sign in', outUrl === BASE + '/?signin=/account/orders/' + orderNumber, outUrl);
} finally {
  console.log(`CUSTOMER ${email}`);
  await p.close();
}
const failed = results.filter(r => !r).length;
console.log(`\n${results.length - failed}/${results.length} passed`);
process.exit(failed ? 1 : 0);
