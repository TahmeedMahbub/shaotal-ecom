// Browser SEO check (Part 7): what a rendering crawler sees AFTER the page scripts ran, compared with the server HTML.
//
//   BASE=http://127.0.0.1:8124 node tests/browser/seo.mjs
//
// Run against a local *_test database. The pagination checks need a listing with more than one page (the 405-product
// perf catalog, tests/Support/perf-catalog-seed.php); on the 5-product seed they are reported as SKIP, never as PASS.
// Exit code 1 on any failure; screenshots of failed checks go to tests/browser/artifacts/ (test data only).
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
    try { mkdirSync(ARTIFACTS, { recursive: true }); await p.shot(`${ARTIFACTS}seo-fail-${results.length}.png`); } catch (e) { /* best effort */ }
  }
};
const skip = (name, why) => console.log(`SKIP  ${name}  — ${why}`);
const wait = ms => new Promise(r => setTimeout(r, ms));

// Raw server HTML (what a non-rendering crawler gets).
const raw = async path => {
  const r = await fetch(BASE + path, { redirect: 'manual' });
  return { status: r.status, location: r.headers.get('location'), html: await r.text() };
};
const rawTitle = html => ((html.match(/<title>([\s\S]*?)<\/title>/) || [])[1] || '').replace(/&amp;/g, '&').replace(/&#039;/g, "'").trim();

// Rendered DOM facts.
const SEO_STATE = `(() => {
  const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].map(s => { try { return JSON.parse(s.textContent); } catch (e) { return { invalid: String(e) }; } });
  const vis = el => !!el && !el.hidden && el.offsetParent !== null;
  return {
    title: document.title,
    h1: [...document.querySelectorAll('h1')].filter(h => h.offsetParent !== null).map(h => h.textContent.trim()),
    canonical: (document.querySelector('link[rel=canonical]') || {}).href || null,
    robots: (document.querySelector('meta[name=robots]') || {}).content || null,
    ld, invalidLd: ld.filter(x => x.invalid).length,
    grid: [...document.querySelectorAll('#listing-products-grid a[href^="/products/"]')].map(a => a.getAttribute('href')).filter((v, i, a) => a.indexOf(v) === i),
    pagerInDom: !!document.getElementById('listing-pagination'),
    pagerVisible: vis(document.getElementById('listing-pagination')),
    pagerLinks: [...document.querySelectorAll('#listing-pagination a')].map(a => a.getAttribute('href')),
    crumbParentVisible: vis(document.getElementById('breadcrumb-parent')),
    related: [...document.querySelectorAll('#related-products-grid a[href^="/products/"]')].map(a => a.getAttribute('href')).filter((v, i, a) => a.indexOf(v) === i),
    csp: window.__csp, overflow: document.documentElement.scrollWidth > window.innerWidth,
  };
})()`;

p = await launch(Number(process.env.CDP_PORT || 9360));
await p.send('Page.addScriptToEvaluateOnNewDocument', { source: `window.__csp=[];document.addEventListener('securitypolicyviolation',e=>__csp.push(e.violatedDirective+' '+e.blockedURI));` });

try {
  // Find a product and a listing with more than one page from the server HTML.
  const all = await raw('/products');
  const product = (all.html.match(/href="(\/products\/[^"?#]+)"/) || [])[1];
  const paged = all.html.includes('id="listing-pagination"') ? '/products' : null;
  const subLink = (all.html.match(/href="(\/products\?category=[^"&]+&amp;subcategory=[^"]+)"/) || [])[1];
  const sub = subLink ? subLink.replace(/&amp;/g, '&') : '/products?category=fashion&subcategory=t-shirts';

  for (const [w, h, mobile] of [[1280, 900, false], [375, 812, true]]) {
    await p.send('Emulation.setDeviceMetricsOverride', { width: w, height: h, deviceScaleFactor: 1, mobile });
    const tag = `${w}px`;

    /* 1. Indexable page types: scripts keep the server title, one visible h1, canonical + valid JSON-LD survive. */
    for (const path of ['/', '/products', sub, product]) {
      const server = await raw(path);
      const mark = p.events.length;
      await p.goto(BASE + path, 1800);
      const s = await p.eval(SEO_STATE);
      const errs = p.errors(mark);
      await check(`${tag} ${path}: rendered title = server title`, s.title === rawTitle(server.html), `${s.title} | ${rawTitle(server.html)}`);
      await check(`${tag} ${path}: exactly one visible h1, canonical, index robots`, s.h1.length === 1 && !!s.canonical && /^index/.test(s.robots || ''), JSON.stringify({ h1: s.h1, c: s.canonical, r: s.robots }));
      await check(`${tag} ${path}: JSON-LD valid after scripts`, s.ld.length > 0 && s.invalidLd === 0, `${s.ld.length} blocks`);
      await check(`${tag} ${path}: no JS errors, CSP violations or overflow`, !errs.length && !s.csp.length && !s.overflow, JSON.stringify({ errs, csp: s.csp, overflow: s.overflow }));
    }

    /* 2. Subcategory: visible breadcrumb keeps the category level. */
    await p.goto(BASE + sub, 1500);
    await check(`${tag} ${sub}: breadcrumb shows the parent category`, (await p.eval(SEO_STATE)).crumbParentVisible);

    /* 3. Product: related links rendered by the script are the ones the server embedded. */
    const serverRelated = [...new Set([...(await raw(product)).html.split('id="related-products-grid"')[1].split('</section>')[0].matchAll(/href="(\/products\/[^"?#]+)"/g)].map(m => m[1]))];
    await p.goto(BASE + product, 1800);
    const rel = (await p.eval(SEO_STATE)).related;
    await check(`${tag} ${product}: script shows the server's 4 related products`, rel.length === 4 && [...rel].sort().join() === [...serverRelated].sort().join(), JSON.stringify({ rel, serverRelated }));

    /* 4. Pagination: crawlable links in the DOM; hidden on page 1 (infinite scroll), visible on page 2+. */
    if (!paged) {
      skip(`${tag} pagination`, 'every listing fits on one page in this database');
    } else {
      await p.goto(BASE + paged, 1800);
      const one = await p.eval(SEO_STATE);
      await check(`${tag} page 1: page links in the DOM but hidden (design unchanged)`, one.pagerInDom && !one.pagerVisible && one.pagerLinks.some(h => /[?&]page=2$/.test(h)), JSON.stringify(one.pagerLinks.slice(0, 4)));
      const two = await raw(paged + '?page=2');
      const twoServerGrid = [...new Set([...two.html.split('id="listing-products-grid"')[1].split('id="infinite-scroll-sentinel"')[0].matchAll(/href="(\/products\/[^"?#]+)"/g)].map(m => m[1]))];
      const mark = p.events.length;
      await p.goto(BASE + paged + '?page=2', 1800);
      const s2 = await p.eval(SEO_STATE);
      await check(`${tag} page 2: canonical is page 2, title says Page 2`, /[?&]page=2$/.test(s2.canonical || '') && / Page 2/.test(s2.title), `${s2.canonical} | ${s2.title}`);
      await check(`${tag} page 2: script shows page 2's products (not page 1's)`, s2.grid.slice(0, twoServerGrid.length).join() === twoServerGrid.join(), JSON.stringify({ dom: s2.grid.slice(0, 3), server: twoServerGrid.slice(0, 3) }));
      await check(`${tag} page 2: page links stay visible to reach earlier pages`, s2.pagerVisible);
      await p.eval(`document.getElementById('infinite-scroll-sentinel').scrollIntoView(); true`);
      await wait(2000);
      const after = await p.eval(SEO_STATE);
      const three = await raw(paged + '?page=3');
      const threeFirst = (three.html.split('id="listing-products-grid"')[1] || '').match(/href="(\/products\/[^"?#]+)"/);
      await check(`${tag} page 2: infinite scroll continues with page 3`, after.grid.length > s2.grid.length && (!threeFirst || after.grid.includes(threeFirst[1])), `${s2.grid.length} → ${after.grid.length}`);
      const errs = p.errors(mark);
      await check(`${tag} page 2: no JS errors, CSP violations or overflow`, !errs.length && !after.csp.length && !after.overflow, JSON.stringify({ errs, csp: after.csp, overflow: after.overflow }));
    }

    /* 5. Draft policy page and private page are noindex after scripts too. */
    for (const path of ['/shipping', '/account']) {
      await p.goto(BASE + path, 1200);
      const s = await p.eval(SEO_STATE);
      await check(`${tag} ${path}: noindex, no canonical`, /noindex/.test(s.robots || '') && !s.canonical, JSON.stringify({ r: s.robots, c: s.canonical }));
    }
  }

  /* 6. URL normalisation as served (not via the test client, which trims slashes). */
  const slash = await raw('/products/?category=fashion');
  await check('trailing slash: 301 to the slash-free URL with the query kept', slash.status === 301 && /\/products\?category=fashion$/.test(slash.location || ''), `${slash.status} ${slash.location}`);
  const missing = await raw('/products/this-product-does-not-exist');
  await check('unknown product: 404 with noindex (no redirect to home)', missing.status === 404 && /noindex/.test(missing.html), String(missing.status));
} finally {
  await p.close();
}

const failed = results.filter(r => !r).length;
console.log(`\n${results.length - failed}/${results.length} passed`);
process.exit(failed ? 1 : 0);
