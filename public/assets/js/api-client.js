/*
 * Storefront JSON/AJAX client: the ONE place that talks to the server and interprets auth/session failures.
 *   419 (CSRF token/session expired) → fetch a fresh token once and retry the request once (no loops).
 *   401 (signed out, blocked, deleted) → onSessionEnded() once per page; the caller shows the sign-in prompt.
 *   403/429/5xx/network → safe, human messages (server exception text is never shown for 5xx).
 * Loaded in the browser before app.js (window.BongoApiClient) and by Node tests (tests/js).
 */
(function (root) {
  const MESSAGES = {
    network: 'Network error. Please check your connection and try again.',
    401: 'Your session has ended. Please sign in again.',
    403: "You don't have permission to do that.",
    419: 'Your session expired. Please refresh the page and try again.',
    429: 'Too many attempts. Please wait a minute and try again.',
    500: 'Something went wrong on our side. Please try again in a moment.',
    generic: 'Something went wrong. Please try again.',
  };
  // Framework default texts that carry no useful information for a shopper.
  const FRAMEWORK_TEXT = /^(Unauthenticated\.?|Unauthorized\.?|Forbidden\.?|This action is unauthorized\.?|Too Many (Attempts|Requests)\.?|CSRF token mismatch\.?|Page Expired|Server Error|Service Unavailable|Not Found)$/i;

  /** Paths whose 401/403 answers are part of signing in (wrong password, inactive account), not an ended session. */
  function isAuthEndpoint(url) {
    return /^\/auth\//.test(String(url || ''));
  }

  /** Safe, same-site path to come back to after signing in again (never an external URL). */
  function returnPath(loc) {
    const p = (loc && loc.pathname) || '/';
    const path = p.startsWith('/') && !p.startsWith('//') ? p : '/';
    return path + ((loc && loc.search) || '') + ((loc && loc.hash) || '');
  }

  function message(res) {
    const status = (res && res.status) || 0;
    const d = (res && res.data) || {};
    if (status === 0) return MESSAGES.network;
    if (status >= 500) return MESSAGES[500];
    if (d.errors && typeof d.errors === 'object') {
      const first = Object.values(d.errors)[0];
      const text = Array.isArray(first) ? first[0] : first;
      if (text) return String(text);
    }
    const text = typeof d.message === 'string' ? d.message.trim() : '';
    if (text && !FRAMEWORK_TEXT.test(text)) return text;
    return MESSAGES[status] || MESSAGES.generic;
  }

  /**
   * @param {object} o
   * @param {Function} o.fetch           window.fetch
   * @param {Function} o.getCsrf         () => current token
   * @param {Function} o.setCsrf         (token) => void
   * @param {Function} [o.onSessionEnded] (res, url) => void — called at most once per page load
   */
  function create(o) {
    let sessionEndedNotified = false;

    async function send(method, url, body) {
      const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
      const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': o.getCsrf(), 'X-Requested-With': 'XMLHttpRequest' };
      if (!isForm && body !== undefined) headers['Content-Type'] = 'application/json';
      try {
        const r = await o.fetch(url, {
          method, credentials: 'same-origin', headers,
          body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)),
        });
        let data = null;
        try { data = await r.json(); } catch (e) { /* non-JSON (e.g. an HTML error page) */ }
        return { ok: r.ok, status: r.status, data: data && typeof data === 'object' ? data : {} };
      } catch (e) {
        return { ok: false, status: 0, data: { message: MESSAGES.network } };
      }
    }

    async function refreshCsrf() {
      const r = await send('GET', '/auth/csrf');
      if (r.ok && r.data.csrf) { o.setCsrf(r.data.csrf); return true; }
      return false;
    }

    async function request(method, url, body) {
      let res = await send(method, url, body);
      // Expired token: the server rejected the request before running it, so one retry is safe (even for orders).
      if (res.status === 419 && method !== 'GET' && await refreshCsrf()) res = await send(method, url, body);
      if (res.status === 401 && !isAuthEndpoint(url) && !sessionEndedNotified) {
        sessionEndedNotified = true;
        if (o.onSessionEnded) o.onSessionEnded(res, url);
      }
      return res;
    }

    return { request, refreshCsrf };
  }

  const api = { create, message, returnPath, isAuthEndpoint, MESSAGES };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.BongoApiClient = api;
})(typeof window !== 'undefined' ? window : globalThis);
