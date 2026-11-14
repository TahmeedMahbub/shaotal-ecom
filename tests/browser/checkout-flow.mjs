// Checkout / cart-clearing / gift / webhook regression in headless Chrome (CDP, no packages).
//
//   CHROME_PATH="C:/Program Files/Google/Chrome/Application/chrome.exe" DB=shaotal_perf_test BASE=http://127.0.0.1:8000 \
//   node tests/browser/checkout-flow.mjs
//
// LOCAL *_test DATABASES ONLY (fixtures from tests/Support/perf-server.php: customer perf@example.test). Needs Stripe
// SANDBOX keys in the server's env for the card part (it creates a real test-mode Checkout Session, never pays it): the
// "payment succeeded" step is a locally SIGNED webhook (checkout.session.completed) posted to /webhooks/stripe, the same
// thing Stripe would send, using STRIPE_WEBHOOK_SECRET read from .env (never printed). Prints timings per step.
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { createHmac } from 'node:crypto';
import { launch } from './cdp.mjs';

const BASE = process.env.BASE || 'http://127.0.0.1:8000';
const DB = process.env.DB || 'shaotal_perf_test';
if (!/_test$|_local$/.test(DB)) throw new Error('Refusing: not a local scratch database');
const sql = q => execFileSync('mysql', ['-uroot', '-N', '-B', DB, '-e', q], { encoding: 'utf8' }).trim();
const env = Object.fromEntries(readFileSync(new URL('../../.env', import.meta.url), 'utf8').split(/\r?\n/).filter(l => /^[A-Z_]+=/.test(l)).map(l => [l.split('=')[0], l.slice(l.indexOf('=') + 1).replace(/^"|"$/g, '')]));
const WHSEC = process.env.STRIPE_WEBHOOK_SECRET || env.STRIPE_WEBHOOK_SECRET;

const results = [];
const check = (name, ok, extra = '') => { results.push(!!ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`); };
const wait = ms => new Promise(r => setTimeout(r, ms));

async function webhook(type, object, id = 'evt_flow_' + Math.random().toString(36).slice(2, 12)) {
  const body = JSON.stringify({ id, type, livemode: false, data: { object } });
  const t = Math.floor(Date.now() / 1000);
  const sig = `t=${t},v1=${createHmac('sha256', WHSEC).update(`${t}.${body}`).digest('hex')}`;
  const t0 = Date.now();
  const r = await fetch(BASE + '/webhooks/stripe', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Stripe-Signature': sig }, body });
  return { status: r.status, body: await r.json().catch(() => ({})), ms: Date.now() - t0 };
}

// Warm the server (catalog cache, opcode cache) so the first page loads are not slower than the script's waits.
for (const path of ['/', '/products', '/products/essential-heavyweight-tee', '/products/nakshi-kantha-cushion']) await fetch(BASE + path).then(r => r.text()).catch(() => {});

const p = await launch(Number(process.env.CDP_PORT || 9380));
try {
  await p.viewport(1280, 900);
  await p.goto(BASE + '/', 1500);
  await p.eval(`localStorage.clear(); true`);
  const login = await p.eval(`loginUser('perf@example.test', 'Password123')`);
  check('sign in', login.success, JSON.stringify(login));
  const cid = sql(`select id from customers where email='perf@example.test'`);

  const fillCart = async () => {
    await p.goto(BASE + '/products/essential-heavyweight-tee', 1500);
    await p.eval(`addToCart(1, '#5C6B4F', 'M', 2, false); true`);
    await wait(800);
    await p.goto(BASE + '/products/nakshi-kantha-cushion', 1500);
    await p.eval(`document.getElementById('detail-add-to-cart-btn').click(); true`);
    await wait(900);
  };
  const dbCartItems = () => Number(sql(`select count(*) from cart_items ci join carts c on c.id=ci.cart_id where c.customer_id=${cid} and c.status='active'`));
  const lsCart = () => p.eval(`JSON.parse(localStorage.getItem('bongo_cart') || '[]').length`);
  const gift = () => p.eval(`JSON.parse(localStorage.getItem('bongo_gift') || 'null')`);
  const toCheckout = async () => { await p.goto(BASE + '/checkout', 2500); };
  const place = async method => {
    await p.eval(`(document.querySelector('#address-cards-grid .address-card')).click(); const r = document.getElementById('pay-${method === 'card' ? 'card' : 'cod'}'); if (r) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); } true`);
    await wait(500);
    const t0 = Date.now();
    await p.eval(`document.getElementById('place-order-btn').click(); true`);
    return t0;
  };

  // ── 0. A cart change made just before leaving the page must reach the saved cart (it used to wait 400 ms and be lost) ──
  await p.goto(BASE + '/products/essential-heavyweight-tee', 1500);
  await p.eval(`addToCart(1, '#1A1A1A', 'S', 1, false); (document.querySelector('a[href="/products"]') || document.querySelector('a[href^="/"]')).click(); true`);
  await wait(2500);
  check('cart: a change made right before clicking a link is saved and shown on the next page', dbCartItems() === 1 && (await lsCart()) === 1, `ls=${await lsCart()} db=${dbCartItems()}`);
  await p.eval(`bongoClearCart(); true`);
  await wait(900);

  // ── 1. COD with gift wrapping (3 wrapped items) ──
  await fillCart();
  check('COD: cart has 2 lines (3 pieces) before checkout', (await lsCart()) === 2 && dbCartItems() === 2, `ls=${await lsCart()} db=${dbCartItems()}`);
  await p.eval(`(() => { const s = { enabled: true, message: 'Happy birthday!', items: {} }; JSON.parse(localStorage.getItem('bongo_cart')).forEach(i => { s.items[(i.productId||i.id)+'|'+(i.color||'')+'|'+(i.size||'')] = i.quantity || 1; }); localStorage.setItem('bongo_gift', JSON.stringify(s)); return true; })()`);
  await toCheckout();
  const giftBox = await p.eval(`({ box: (document.getElementById('checkout-gift')||{}).innerText || '', row: (document.getElementById('gift-row')||{}).innerText || '' })`);
  console.log('   gift box:', JSON.stringify(giftBox));
  check('COD: checkout shows the gift selection, quantities and a separate fee row', /gift/i.test(giftBox.box) && /A\$|\$/.test(giftBox.row), JSON.stringify(giftBox));
  const t0 = await place('cod');
  let ok = { open: false, ref: '' };
  for (let i = 0; i < 150 && !ok.open; i++) {
    await wait(100);
    ok = await p.eval(`({ open: document.getElementById('success-modal-overlay').classList.contains('active'), ref: document.getElementById('success-order-ref').textContent })`);
  }
  const codMs = Date.now() - t0;
  console.log(`   COD: click → success dialog in ${codMs} ms (browser-observed, includes server + network + render)`);
  const codNumber = (ok.ref || '').replace('#', '');
  check('COD: success dialog shown', ok.open && /^INV-\d{6}$/.test(codNumber), JSON.stringify(ok));
  check(`COD: success dialog appears quickly (${codMs} ms; email sending is off the request)`, codMs < 2500, String(codMs));
  check('COD: browser cart emptied', (await lsCart()) === 0, String(await lsCart()));
  check('COD: saved (server) cart emptied', dbCartItems() === 0, String(dbCartItems()));
  check('COD: gift selection cleared after the order', (await gift()) === null);
  const codOrder = sql(`select concat(subtotal,'|',grand_total,'|',payment_status) from orders where order_number='${codNumber}'`);
  const codGiftRow = sql(`select concat(product_name,'|',line_total) from order_items oi join orders o on o.id=oi.order_id where o.order_number='${codNumber}' and oi.sku='GIFT-WRAP'`);
  console.log('   COD order:', codOrder, '| gift row:', codGiftRow);
  check('COD: gift fee row is A$2.99 for 3 items', /3 items/.test(codGiftRow) && /\|2\.99$/.test(codGiftRow), codGiftRow);

  // ── 2. Card: pending / cancelled / incomplete keep the cart ──
  await fillCart();
  await toCheckout();
  const tc0 = Date.now();
  const mark = p.events.length;
  await place('card');
  let redirected = null;
  for (let i = 0; i < 60 && !redirected; i++) { await wait(250); redirected = await p.eval(`location.href`).then(h => (/stripe\.com/.test(h) ? h : null)).catch(() => null); }
  const placeMs = Date.now() - tc0;
  const cardNumber = sql(`select order_number from orders where customer_id=${cid} and payment_method='card' order by id desc limit 1`);
  console.log(`   card order ${cardNumber}: place → Stripe page in ${placeMs} ms (includes Stripe API latency)`);
  check('card: customer is sent to Stripe Checkout', !!redirected, String(redirected || '').slice(0, 60));
  check('card: order exists, unpaid', sql(`select payment_status from orders where order_number='${cardNumber}'`) === 'pending');
  check('card: cart kept while payment is pending (browser + saved)', (await p.goto(BASE + '/products', 800), true) && (await lsCart()) === 2 && dbCartItems() === 2, `ls=${await lsCart()} db=${dbCartItems()}`);
  await p.goto(BASE + '/checkout/payment/cancel?order=' + cardNumber, 1200);
  check('card: cancelled return keeps the cart', (await lsCart()) === 2 && dbCartItems() === 2, `ls=${await lsCart()} db=${dbCartItems()}`);

  // Success redirect WITHOUT a webhook must not clear anything.
  const sess = sql(`select provider_transaction_id from payments where order_id=(select id from orders where order_number='${cardNumber}') and provider_transaction_id is not null order by id desc limit 1`);
  await p.goto(BASE + '/checkout/payment/success?session_id=' + sess, 1500);
  const st = await p.eval(`document.getElementById('payment-result').dataset.state`);
  check('card: success redirect without webhook shows "confirming" and keeps the cart', /confirming|incomplete/.test(st) && (await lsCart()) === 2 && dbCartItems() === 2, `state=${st}`);

  // Payment declined event keeps the cart too.
  const pay = sql(`select concat(id,'|',amount) from payments where provider_transaction_id='${sess}'`).split('|');
  const meta = { order_number: cardNumber, payment_id: pay[0] };
  const cents = Math.round(Number(pay[1]) * 100);
  const declined = await webhook('payment_intent.payment_failed', { id: 'pi_flow_x', metadata: meta, last_payment_error: { decline_code: 'generic_decline' } });
  check('card: declined payment event accepted, cart kept', declined.status === 200 && dbCartItems() === 2, JSON.stringify(declined.body));

  // ── 3. Verified webhook: the browser never returns, the cart still clears; duplicates are harmless ──
  const done = { id: sess, status: 'complete', payment_status: 'paid', amount_total: cents, currency: 'aud', metadata: meta, payment_intent: 'pi_flow_x' };
  const evId = 'evt_flow_paid_' + Date.now();
  const w1 = await webhook('checkout.session.completed', done, evId);
  const w2 = await webhook('checkout.session.completed', done, evId);
  console.log(`   webhook paid: ${w1.ms} ms → ${w1.body.outcome}; duplicate: ${w2.ms} ms → ${w2.body.outcome}`);
  check('webhook: first delivery marks it paid', w1.body.outcome === 'paid', JSON.stringify(w1.body));
  check('webhook: duplicate delivery is a no-op', w2.body.outcome === 'duplicate', JSON.stringify(w2.body));
  check('webhook: saved cart cleared by the server although the browser never came back', dbCartItems() === 0, String(dbCartItems()));
  check('webhook: 30% deposit does not mark the order fully paid', sql(`select payment_status from orders where order_number='${cardNumber}'`) === 'pending');
  // Browser side: any page load for a signed-in customer takes the server cart, so the stale localStorage copy is replaced.
  await p.goto(BASE + '/products', 1500);
  check('webhook: next page load shows an empty browser cart (no stale copy)', (await lsCart()) === 0, String(await lsCart()));
  await p.goto(BASE + '/checkout/payment/success?session_id=' + sess, 1500);
  check('success return after the webhook: the COD success modal, with the card deposit rows',
    (await p.eval(`location.pathname + location.search`)) === '/checkout?paid=' + cardNumber
      && (await p.eval(`document.getElementById('success-modal-overlay').classList.contains('active')`)) === true
      && (await p.eval(`document.getElementById('success-card-rows').hidden`)) === false
      && (await p.eval(`document.getElementById('success-payment-status').textContent`)) === 'Partially Paid');
  check('card order: gift/cart state clean afterwards', (await lsCart()) === 0 && (await gift()) === null);
  console.log(`ORDERS ${codNumber} ${cardNumber}`);
} catch (e) {
  console.log('ERROR', e.message);
  results.push(false);
} finally {
  await p.close();
}
const failed = results.filter(r => !r).length;
console.log(`\n${results.length - failed}/${results.length} checks passed`);
process.exit(failed ? 1 : 0);
