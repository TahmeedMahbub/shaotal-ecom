// LOCAL MEASUREMENT ONLY. Page-load metrics for storefront pages in headless Chrome over CDP.
//
//   BASE=http://127.0.0.1:8124 RUNS=3 node tests/browser/perf.mjs > result.json
//
// Serve the app against a local *_test database first, with PHP output gzipped like a production server would
// (php -d zlib.output_compression=1 -S …, see STOREFRONT_PRODUCTION_READINESS_PLAN.md §14). Signed-in
// pages use perf@example.test / Password123 (created by tests/Support/perf-server.php). Profiles: "desktop"
// (1280×900, no throttling) and "mobile" (375×812, 4× CPU slowdown, 150 ms RTT, 1.6 Mbps down). Browser cache is
// disabled, so every run is a first visit. Local numbers: compare before/after, never quote them as production values.
import { launch } from './cdp.mjs';

const BASE = process.env.BASE || 'http://127.0.0.1:8124';
const RUNS = Number(process.env.RUNS || 3);
const ORDER = process.env.ORDER || 'INV-PERF01';
const PAGES = {
  home: '/', listing: '/products', category: '/products?category=fashion', subcategory: '/products?category=fashion&subcategory=t-shirts',
  search: '/products?search=jute', product: '/products/essential-heavyweight-tee', 'bulk-order': '/bulk-order', request: '/request',
  account: '/account', checkout: '/checkout', order: `/account/orders/${ORDER}`,
};
const AUTH = new Set(['account', 'checkout', 'order']);
const PROFILES = {
  desktop: { width: 1280, height: 900, mobile: false, cpu: 1, net: null, settle: 2500, timeout: 15000 },
  mobile: { width: 375, height: 812, mobile: true, cpu: 4, net: { offline: false, latency: 150, downloadThroughput: 1.6e6 / 8, uploadThroughput: 750e3 / 8 }, settle: 3000, timeout: 45000 },
};

const OBSERVERS = `window.__perf = { lcp: 0, lcpEl: '', cls: 0, tbt: 0, csp: 0 };
try {
  new PerformanceObserver(l => { for (const e of l.getEntries()) { __perf.lcp = e.startTime; __perf.lcpEl = (e.element && (e.element.id || e.element.className || e.element.tagName)) + ''; } }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) __perf.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
  new PerformanceObserver(l => { for (const e of l.getEntries()) __perf.tbt += Math.max(0, e.duration - 50); }).observe({ type: 'longtask', buffered: true });
} catch (e) {}
document.addEventListener('securitypolicyviolation', () => __perf.csp++);`;

const median = xs => { const s = [...xs].sort((a, b) => a - b); return s[Math.floor(s.length / 2)]; };
const wait = ms => new Promise(r => setTimeout(r, ms));

const p = await launch(Number(process.env.CDP_PORT || 9340));
const out = {};
try {
  await p.send('Page.addScriptToEvaluateOnNewDocument', { source: OBSERVERS });
  await p.send('Network.setCacheDisabled', { cacheDisabled: true });
  // Sign in once (cookie jar is shared by all later navigations).
  await p.goto(BASE + '/request', 500);
  const login = await p.eval(`fetch('/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ email: 'perf@example.test', password: 'Password123' }) }).then(r => r.status)`);
  if (login !== 200) throw new Error('perf sign-in failed: ' + login);

  for (const [profileName, prof] of Object.entries(PROFILES)) {
    await p.send('Emulation.setDeviceMetricsOverride', { width: prof.width, height: prof.height, deviceScaleFactor: 1, mobile: prof.mobile });
    await p.send('Emulation.setCPUThrottlingRate', { rate: prof.cpu });
    await p.send('Network.emulateNetworkConditions', prof.net || { offline: false, latency: 0, downloadThroughput: -1, uploadThroughput: -1 });
    for (const [name, path] of Object.entries(PAGES)) {
      const runs = [];
      for (let r = 0; r < RUNS; r++) {
        const mark = p.events.length;
        await p.goto(BASE + path, prof.settle, prof.timeout);
        const after = await p.send('Performance.getMetrics');
        const metric = (m, k) => (m.result.metrics.find(x => x.name === k) || {}).value || 0;
        const evs = p.events.slice(mark);
        const reqs = new Map();
        for (const e of evs) {
          if (e.method === 'Network.requestWillBeSent') reqs.set(e.params.requestId, { url: e.params.request.url, type: e.params.type, bytes: 0 });
          if (e.method === 'Network.loadingFinished' && reqs.has(e.params.requestId)) reqs.get(e.params.requestId).bytes = e.params.encodedDataLength;
        }
        const list = [...reqs.values()].filter(x => !x.url.startsWith('data:'));
        const sum = t => list.filter(x => !t || x.type === t).reduce((a, x) => a + x.bytes, 0);
        const page = await p.eval(`(() => {
          const n = performance.getEntriesByType('navigation')[0];
          const imgs = [...document.images];
          return { ttfb: n.responseStart - n.startTime, dcl: n.domContentLoadedEventEnd - n.startTime, load: n.loadEventEnd - n.startTime,
            lcp: __perf.lcp, lcpEl: __perf.lcpEl, cls: __perf.cls, tbt: __perf.tbt, csp: __perf.csp,
            images: imgs.length, lazy: imgs.filter(i => i.loading === 'lazy').length, noDims: imgs.filter(i => !i.getAttribute('width') || !i.getAttribute('height')).length,
            overflow: document.documentElement.scrollWidth > window.innerWidth };
        })()`);
        runs.push({
          status: 200, ...page, requests: list.length, totalBytes: sum(), docBytes: sum('Document'), jsBytes: sum('Script'), cssBytes: sum('Stylesheet'),
          imgBytes: sum('Image'), fontBytes: sum('Font'), xhrBytes: sum('XHR') + sum('Fetch'),
          nullRequests: list.filter(x => /\/null($|\?)/.test(x.url)).length,
          // Renderer counters restart with each document, so the value after load is this page's own total.
          scriptMs: metric(after, 'ScriptDuration') * 1000,
          errors: p.errors(mark).length,
        });
      }
      const m = k => median(runs.map(x => x[k]));
      const row = {};
      for (const k of Object.keys(runs[0])) row[k] = typeof runs[0][k] === 'number' ? Math.round(m(k) * 1000) / 1000 : runs[0][k];
      out[`${profileName}:${name}`] = row;
      console.error(`${profileName.padEnd(7)} ${name.padEnd(12)} ttfb ${row.ttfb.toFixed(0).padStart(5)} lcp ${row.lcp.toFixed(0).padStart(5)} cls ${row.cls.toFixed(3)} tbt ${row.tbt.toFixed(0).padStart(4)} script ${row.scriptMs.toFixed(0).padStart(4)}ms | req ${String(row.requests).padStart(3)} total ${(row.totalBytes / 1024).toFixed(0).padStart(5)}K doc ${(row.docBytes / 1024).toFixed(0).padStart(4)}K js ${(row.jsBytes / 1024).toFixed(0).padStart(4)}K css ${(row.cssBytes / 1024).toFixed(0).padStart(3)}K img ${(row.imgBytes / 1024).toFixed(0).padStart(5)}K | err ${row.errors} csp ${row.csp} null ${row.nullRequests}${AUTH.has(name) ? ' (signed in)' : ''}`);
    }
  }
} finally {
  await p.close();
}
console.log(JSON.stringify(out, null, 2));
