// Run: node --test tests/js/api-client.test.js
import test from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';

// Classic browser script: evaluate with a fake window (same approach as size-guide-units.test.js).
const win = {};
new Function('window', readFileSync(new URL('../../public/assets/js/api-client.js', import.meta.url), 'utf8'))(win);
const C = win.BongoApiClient;

/** Fake fetch answering from a queue per URL; records every call. */
function fakeServer(routes) {
  const calls = [];
  const fetch = async (url, init) => {
    calls.push({ url, init });
    const queue = routes[url];
    // A list of [status, body] pairs is a queue (one per call); a single [status, body] pair answers every call.
    const next = Array.isArray(queue) && (Array.isArray(queue[0]) || queue[0] === 'network') ? queue.shift() : queue;
    if (next === 'network') throw new TypeError('Failed to fetch');
    const [status, body] = next || [404, { message: 'Not Found' }];
    return { ok: status >= 200 && status < 300, status, json: async () => { if (body === undefined) throw new Error('no json'); return body; } };
  };
  return { fetch, calls };
}

function client(routes, extra = {}) {
  const srv = fakeServer(routes);
  let csrf = 'old-token';
  const ended = [];
  const c = C.create({ fetch: srv.fetch, getCsrf: () => csrf, setCsrf: t => { csrf = t; }, onSessionEnded: (res, url) => ended.push(url), ...extra });
  return { c, srv, ended, csrf: () => csrf };
}

test('419: refreshes the CSRF token once and retries the same request with the new token', async () => {
  const { c, srv, csrf } = client({ '/checkout/place': [[419, { message: 'CSRF token mismatch.' }], [201, { orderNumber: 'INV-1' }]], '/auth/csrf': [[200, { csrf: 'new-token' }]] });
  const res = await c.request('POST', '/checkout/place', { a: 1 });
  assert.strictEqual(res.status, 201);
  assert.strictEqual(csrf(), 'new-token');
  assert.deepStrictEqual(srv.calls.map(x => x.url), ['/checkout/place', '/auth/csrf', '/checkout/place']);
  assert.strictEqual(srv.calls[2].init.headers['X-CSRF-TOKEN'], 'new-token');
});

test('419 twice: no loop, one retry only, then a safe message', async () => {
  const { c, srv } = client({ '/account/cart': [[419, {}], [419, {}]], '/auth/csrf': [[200, { csrf: 't2' }]] });
  const res = await c.request('PUT', '/account/cart', { items: [] });
  assert.strictEqual(res.status, 419);
  assert.strictEqual(srv.calls.length, 3);
  assert.strictEqual(C.message(res), C.MESSAGES[419]);
});

test('401 on a protected call ends the session once per page, even for parallel requests', async () => {
  const { c, ended } = client({ '/account/orders': [401, { message: 'Your session has ended. Please sign in again.' }], '/account/requests': [401, {}] });
  await Promise.all([c.request('GET', '/account/orders'), c.request('GET', '/account/requests'), c.request('GET', '/account/orders')]);
  assert.deepStrictEqual(ended, ['/account/orders']);
});

test('401/403 from sign-in endpoints are form errors, not an ended session', async () => {
  const { c, ended } = client({ '/auth/login': [[403, { message: 'This account is not active. Please contact us.' }]] });
  const res = await c.request('POST', '/auth/login', {});
  assert.deepStrictEqual(ended, []);
  assert.strictEqual(C.message(res), 'This account is not active. Please contact us.');
});

test('messages: server exception text is never shown for 5xx; framework texts replaced; validation first error kept', () => {
  assert.strictEqual(C.message({ status: 500, data: { message: 'SQLSTATE[42S02] secret at /var/www/app.php' } }), C.MESSAGES[500]);
  assert.strictEqual(C.message({ status: 503, data: {} }), C.MESSAGES[500]);
  assert.strictEqual(C.message({ status: 403, data: { message: 'This action is unauthorized.' } }), C.MESSAGES[403]);
  assert.strictEqual(C.message({ status: 429, data: { message: 'Too Many Attempts.' } }), C.MESSAGES[429]);
  assert.strictEqual(C.message({ status: 429, data: { message: 'Too many sign-in attempts. Please try again in 3 minutes or reset your password.' } }),
    'Too many sign-in attempts. Please try again in 3 minutes or reset your password.');
  assert.strictEqual(C.message({ status: 422, data: { message: 'x', errors: { phone: ['Please enter a valid phone number (8 to 15 digits).'] } } }),
    'Please enter a valid phone number (8 to 15 digits).');
  assert.strictEqual(C.message({ status: 0, data: {} }), C.MESSAGES.network);
});

test('network failure and HTML (non-JSON) error pages become safe results', async () => {
  const { c } = client({ '/x': 'network', '/y': [[500, undefined]] });
  const a = await c.request('GET', '/x');
  assert.deepStrictEqual([a.ok, a.status], [false, 0]);
  const b = await c.request('GET', '/y');
  assert.deepStrictEqual([b.status, b.data], [500, {}]);
  assert.strictEqual(C.message(b), C.MESSAGES[500]);
});

test('FormData bodies are sent as-is without a JSON content type', async () => {
  global.FormData = class FormData {};
  const { c, srv } = client({ '/request': [[201, { requestNumber: 'REQ-1' }]] });
  const fd = new FormData();
  await c.request('POST', '/request', fd);
  assert.strictEqual(srv.calls[0].init.body, fd);
  assert.strictEqual(srv.calls[0].init.headers['Content-Type'], undefined);
  delete global.FormData;
});

test('returnPath keeps only same-site paths (no open redirect)', () => {
  assert.strictEqual(C.returnPath({ pathname: '/checkout', search: '?a=1', hash: '#x' }), '/checkout?a=1#x');
  assert.strictEqual(C.returnPath({ pathname: '//evil.example/x', search: '' }), '/');
  assert.strictEqual(C.returnPath({}), '/');
});
