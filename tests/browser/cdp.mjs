// Minimal Chrome DevTools Protocol helper (Node 22+, built-in WebSocket): launches headless Chrome and exposes page
// helpers. No third-party packages. CHROME_PATH overrides the Chrome binary.
import { spawn } from 'node:child_process';
import { writeFileSync, mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const CHROME = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const sleep = ms => new Promise(r => setTimeout(r, ms));

export async function launch(port = 9333) {
  const profile = mkdtempSync(join(process.env.SCRATCH || tmpdir(), 'chrome-'));
  const proc = spawn(CHROME, ['--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run', '--disable-gpu', 'about:blank'], { stdio: 'ignore' });
  let target;
  for (let i = 0; i < 50 && !target; i++) {
    await sleep(200);
    try { target = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find(t => t.type === 'page'); } catch (e) { /* not up yet */ }
  }
  const ws = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise(r => ws.addEventListener('open', r, { once: true }));
  let id = 0;
  const pending = new Map();
  const events = [];
  ws.addEventListener('message', ev => {
    const m = JSON.parse(ev.data);
    if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } else if (m.method) events.push(m);
  });
  const send = (method, params = {}) => new Promise(r => { const i = ++id; pending.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
  await send('Page.enable'); await send('Runtime.enable'); await send('Log.enable'); await send('Network.enable'); await send('Performance.enable');

  const page = {
    events,
    send,
    async goto(url, wait = 1200, timeoutMs = 10000) {
      const before = events.length;
      await send('Page.navigate', { url });
      for (let i = 0; i < timeoutMs / 100; i++) { await sleep(100); if (events.slice(before).some(e => e.method === 'Page.loadEventFired')) break; }
      await sleep(wait);
    },
    async eval(expr) {
      const r = await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true });
      if (r.result?.exceptionDetails) throw new Error(JSON.stringify(r.result.exceptionDetails).slice(0, 500));
      return r.result?.result?.value;
    },
    async viewport(width, height, mobile = false) {
      await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile });
    },
    async shot(path) {
      const r = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true });
      writeFileSync(path, Buffer.from(r.result.data, 'base64'));
    },
    errors(since = 0) {
      return events.slice(since).filter(e =>
        (e.method === 'Runtime.exceptionThrown') ||
        (e.method === 'Log.entryAdded' && e.params.entry.level === 'error' && !/favicon|Failed to load resource.*(404|cloudinary)/i.test(e.params.entry.text + (e.params.entry.url || ''))) ||
        (e.method === 'Runtime.consoleAPICalled' && e.params.type === 'error')
      ).map(e => JSON.stringify(e.params).slice(0, 300));
    },
    async close() { ws.close(); proc.kill(); },
  };
  return page;
}
