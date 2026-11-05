/*
 * Size guide measurement display. A stored value is a number ("38", "27.5") or a range ("71.1-76.2") in its STORED unit
 * ('in' | 'cm'). Display text is always derived from that original (never from an already converted/rounded value), so
 * switching units never drifts. 1 in = 2.54 cm. Display only: nothing here is ever saved.
 * Mirrors Admin's App\Support\Measurement::display(). Loaded in the browser (window.SizeGuideUnits) and by Node tests.
 */
(function (root) {
  const CM_PER_IN = 2.54;
  const NUM = '\\d+(?:\\.\\d{1,2})?';
  const RE = new RegExp('^(' + NUM + ')(?:\\s*[-\\u2013\\u2014]\\s*(' + NUM + '))?$');

  function unit(u) {
    const s = String(u == null ? '' : u).trim().toLowerCase();
    if (['in', 'inch', 'inches', '"'].includes(s)) return 'in';
    if (['cm', 'centimeter', 'centimeters', 'centimetre', 'centimetres'].includes(s)) return 'cm';
    return null;
  }

  /** @returns {number[]|null} [min] or [min, max]; null when empty/invalid (a range must not run backwards). */
  function parse(raw) {
    const m = RE.exec(String(raw == null ? '' : raw).trim());
    if (!m) return null;
    const a = parseFloat(m[1]);
    if (m[2] === undefined) return [a];
    const b = parseFloat(m[2]);
    return a <= b ? [a, b] : null;
  }

  /**
   * The one formatter for every displayed measurement: exactly one decimal, extra digits DROPPED (truncated, never rounded).
   * 13.82 -> 13.8, 35.96 -> 35.9, 37.04 -> 37.0, 14 -> 14.0. toFixed(6) first removes binary float noise
   * (e.g. 2.3 * 10 = 22.999999999999996) so a value that is exactly x.y is never truncated down to x.(y-1).
   */
  const fmt1 = (n) => (Math.floor(Number((n * 10).toFixed(6))) / 10).toFixed(1);

  /**
   * Text for a stored value `raw` kept in unit `from`, shown in unit `to`.
   * Each end of a range is converted from the exact stored value (1 in = 2.54 cm), then formatted by fmt1.
   * Inch adds the inch mark: 28.0"-30.0"; CM: 71.1-76.2. Invalid or empty -> an em dash. Display only: never saved.
   */
  function display(raw, from, to) {
    const p = parse(raw);
    if (!p) return '\u2014';
    const k = from === to ? 1 : (from === 'in' ? CM_PER_IN : 1 / CM_PER_IN);
    return p.map((n) => fmt1(n * k) + (to === 'in' ? '"' : '')).join('-');
  }

  const api = { unit, parse, display, fmt1, CM_PER_IN };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.SizeGuideUnits = api;
})(typeof window !== 'undefined' ? window : globalThis);
