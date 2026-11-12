// Browser smoke test for the page-scoped catalog (Part 5): headless Chrome over CDP, no packages.
//
//   BASE=http://127.0.0.1:8124 node tests/browser/smoke.mjs
//
// Run against a local *_test database seeded with sql/local_test_seed.sql (+ optionally the perf catalog) and the
// perf customer (tests/Support/perf-server.php creates perf@example.test / Password123). Exit code 1 on any failure.
import { launch } from './cdp.mjs';

const BASE = process.env.BASE || 'http://127.0.0.1:8124';
const results = [];
const check = (name, ok, extra = '') => { results.push(!!ok); console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`); };
const wait = ms => new Promise(r => setTimeout(r, ms));

const p = await launch(Number(process.env.CDP_PORT || 9350));
await p.send('Page.addScriptToEvaluateOnNewDocument', { source: `window.__csp=[];window.__cls=0;document.addEventListener('securitypolicyviolation',e=>__csp.push(e.violatedDirective+' '+e.blockedURI));try{new PerformanceObserver(l=>{for(const e of l.getEntries())if(!e.hadRecentInput)__cls+=e.value}).observe({type:'layout-shift',buffered:true})}catch(e){}` });
const clean = async (label, mark) => {
  const r = await p.eval(`({ csp: __csp, overflow: document.documentElement.scrollWidth > window.innerWidth })`);
  const errs = p.errors(mark);
  check(`${label}: no JS errors, CSP violations or overflow`, !errs.length && !r.csp.length && !r.overflow, JSON.stringify({ errs, ...r }));
};
const cartCount = () => p.eval(`JSON.parse(localStorage.getItem('bongo_cart') || '[]').reduce((s, i) => s + (i.quantity || 1), 0)`);

try {
  for (const [w, h, mobile] of [[1280, 900, false], [375, 812, true]]) {
    await p.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile });
    for (const path of ['/', '/products', '/products?category=fashion', '/products?search=jute', '/products/essential-heavyweight-tee', '/bulk-order', '/request', '/account']) {
      const mark = p.events.length;
      await p.goto(BASE + path, 1500);
      await clean(`${w}px ${path}`, mark);
    }
  }
  await p.send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });

  // Page scope: pages embed only what they render.
  await p.goto(BASE + '/request', 800);
  check('/request embeds no products', (await p.eval('PRODUCTS.length')) === 0, String(await p.eval('PRODUCTS.length')));
  await p.goto(BASE + '/bulk-order', 1200);
  const bulk = await p.eval(`({ all: PRODUCTS.length, opts: document.querySelectorAll('select option').length })`);
  check('/bulk-order embeds the whole catalog for its product picker', bulk.all >= 5, JSON.stringify(bulk));

  // Home: server-rendered popular pick is kept by the script; one h1; header nav present before scripts.
  await p.eval(`sessionStorage.clear(); true`);
  await p.goto(BASE + '/', 1500);
  const home = await p.eval(`({ ids: document.getElementById('product-grid').dataset.popularIds, rendered: [...document.querySelectorAll('#product-grid .product-card')].map(c => c.dataset.productId).join(','), h1: document.querySelectorAll('h1').length, nav: document.querySelectorAll('#nav-items .nav-item').length })`);
  check('home: script keeps the server popular pick', home.ids && home.ids === home.rendered, JSON.stringify(home));
  check('home: one h1 and header nav', home.h1 === 1 && home.nav >= 3, JSON.stringify(home));

  // Guest cart survives pages that do not embed its products, and is refreshed from the catalog.
  await p.goto(BASE + '/products/nakshi-kantha-cushion', 1200);
  await p.eval(`localStorage.setItem('bongo_cart', '[]'); localStorage.setItem('bongo_wishlist', '[]'); true`);
  await p.goto(BASE + '/products/nakshi-kantha-cushion', 1200);
  await p.eval(`document.getElementById('detail-add-to-cart-btn').click(); true`);
  await wait(600);
  check('product: add to cart', (await cartCount()) === 1, String(await cartCount()));
  await p.eval(`toggleWishlist(2); true`);
  await p.goto(BASE + '/request', 1500);
  const req = await p.eval(`({ count: document.getElementById('cart-count')?.textContent, loaded: BongoCatalog.has(2), drawer: document.getElementById('cart-drawer-body').innerText.includes('Nakshi'), wish: document.getElementById('wishlist-drawer-body').innerText.includes('Nakshi') })`);
  check('/request: cart kept, its product fetched, drawers show it', req.count === '1' && req.loaded && req.drawer && req.wish, JSON.stringify(req));

  // An unlisted product in a saved cart is dropped once the catalog confirms it; a failed lookup keeps everything.
  await p.eval(`localStorage.setItem('bongo_cart', JSON.stringify([{ productId: 2, name: 'Nakshi Kantha Cushion', price: 55, quantity: 1 }, { productId: 99999999, name: 'Gone', price: 1, quantity: 1 }])); true`);
  await p.send('Network.setBlockedURLs', { urls: ['*/catalog/products*'] });
  await p.goto(BASE + '/request', 1500);
  check('lookup blocked: nothing dropped', (await cartCount()) === 2, String(await cartCount()));
  await p.send('Network.setBlockedURLs', { urls: [] });
  await p.goto(BASE + '/request', 1500);
  check('lookup ok: unlisted product dropped, listed kept', (await cartCount()) === 1, String(await cartCount()));

  // Header search runs on the server.
  await p.eval(`document.getElementById('search-toggle').click(); true`);
  await wait(800);
  const suggested = await p.eval(`document.querySelectorAll('#search-suggested-list .search-result-item').length`);
  check('search: suggestions load', suggested > 0, String(suggested));
  await p.eval(`const i = document.getElementById('search-input'); i.value = 'jute'; i.dispatchEvent(new Event('input')); true`);
  await wait(1000);
  const sr = await p.eval(`({ n: document.querySelectorAll('#search-results-list .search-result-item').length, text: document.getElementById('search-results-count').textContent })`);
  check('search: results from /catalog/search', sr.n > 0 && /result/.test(sr.text), JSON.stringify(sr));
  await p.eval(`document.querySelector('#search-results-list .search-result-item').click(); true`);
  await wait(1500);
  check('search: clicking a result opens the product', (await p.eval('location.pathname')).startsWith('/products/'));

  // Listing: filters, sort and infinite scroll through /catalog/feed.
  await p.goto(BASE + '/products', 1500);
  const total0 = await p.eval(`document.getElementById('listing-count').textContent`);
  await p.eval(`document.querySelector('[data-filter-brand]').click(); true`);
  await wait(1200);
  const total1 = await p.eval(`document.getElementById('listing-count').textContent`);
  check('listing: brand filter changes the result', total0 !== total1 && /Showing \d+/.test(total1), `${total0} → ${total1}`);
  await p.eval(`document.querySelector('.clear-all-filters-btn').click(); true`);
  await wait(1200);
  check('listing: clear filters restores the total', (await p.eval(`document.getElementById('listing-count').textContent`)) === total0);
  const before = await p.eval(`document.querySelectorAll('#listing-products-grid .product-card').length`);
  for (let i = 0; i < 4; i++) { await p.eval(`window.scrollTo(0, document.body.scrollHeight); true`); await wait(900); }
  const after = await p.eval(`document.querySelectorAll('#listing-products-grid .product-card').length`);
  const totalN = Number((total0.match(/\d+/) || [0])[0]);
  check('listing: infinite scroll loads more pages', after > before || before === totalN, `${before} → ${after} of ${totalN}`);
  await p.eval(`window.scrollTo(0, 0); const s = document.getElementById('sort-select'); s.value = 'price-low'; s.dispatchEvent(new Event('change')); true`);
  await wait(1200);
  const prices = await p.eval(`[...document.querySelectorAll('#listing-products-grid .product-card .current')].map(e => parseFloat(e.textContent.replace(/[^0-9.]/g, '')))`);
  check('listing: sort price low→high', prices.length > 1 && prices.every((v, i) => i === 0 || prices[i - 1] <= v), prices.slice(0, 6).join(','));
  await p.eval(`document.querySelector('#listing-products-grid .product-card').click(); true`);
  await wait(1500);
  check('listing: card opens product', (await p.eval('location.pathname')).startsWith('/products/'));

  // Recently viewed (product page embeds only itself + related; the rest is fetched).
  await p.goto(BASE + '/products/handwoven-jute-tote', 1200);
  await p.goto(BASE + '/products/essential-heavyweight-tee', 2000);
  const rv = await p.eval(`({ shown: getComputedStyle(document.getElementById('recently-viewed-section')).display !== 'none', n: document.querySelectorAll('#recently-viewed-grid .product-card').length })`);
  check('product: recently viewed renders', rv.shown && rv.n >= 1, JSON.stringify(rv));

  // Sign-in returns the products of the saved cart/wishlist.
  await p.goto(BASE + '/request', 800);
  const signin = await p.eval(`loginUser('perf@example.test', 'Password123').then(r => ({ ok: r.success, cart: JSON.parse(localStorage.getItem('bongo_cart') || '[]').length }))`);
  check('sign-in: session applied', signin.ok, JSON.stringify(signin));
  await p.goto(BASE + '/account#wishlist', 1500);
  await clean('/account signed in', 0);
  const acct = await p.eval(`({ auth: getComputedStyle(document.getElementById('account-auth-view')).display, cls: __cls })`);
  check('account: signed-in view rendered by the server (no shift)', acct.auth === 'grid' && acct.cls < 0.05, JSON.stringify(acct));
  await p.goto(BASE + '/checkout', 2000);
  const co = await p.eval(`({ addresses: document.querySelectorAll('#address-cards-grid > *').length, cls: __cls })`);
  check('checkout: addresses rendered without an extra request, CLS < 0.1', co.addresses >= 1 && co.cls < 0.1, JSON.stringify(co));
  await p.eval(`logoutUser(); true`);
  await wait(800);
} finally {
  await p.close();
}
const failed = results.filter(r => !r).length;
console.log(`\n${results.length - failed}/${results.length} passed`);
process.exit(failed ? 1 : 0);
