// Run: node --test tests/js
import test from 'node:test';
import assert from 'node:assert';
import { readFileSync } from 'node:fs';

// The asset is a classic browser script (package.json is "type": "module"), so evaluate its source with a fake window.
const win = {};
new Function('window', readFileSync(new URL('../../public/assets/js/size-guide-units.js', import.meta.url), 'utf8'))(win);
const U = win.SizeGuideUnits;

test('formatter: exactly one decimal, truncated never rounded (required examples)', () => {
  assert.strictEqual(U.fmt1(13.82), '13.8');
  assert.strictEqual(U.fmt1(14.21), '14.2');
  assert.strictEqual(U.fmt1(35.96), '35.9');
  assert.strictEqual(U.fmt1(37.04), '37.0');
  assert.strictEqual(U.fmt1(14), '14.0');
});

test('formatter: float noise never truncates an exact tenth down', () => {
  for (const v of [2.3, 4.6, 1.1, 0.7, 8.2, 91.4, 96.5]) assert.strictEqual(U.fmt1(v), v.toFixed(1), String(v));
  assert.strictEqual(U.fmt1(76.2 / 2.54), '30.0'); // 29.999999999999996 in binary
  assert.strictEqual(U.fmt1(29.99), '29.9'); // a genuinely lower value is still truncated
});

test('same-unit values: shown with exactly one decimal (truncated)', () => {
  assert.strictEqual(U.display('13.82', 'in', 'in'), '13.8"');
  assert.strictEqual(U.display('35.96-37.04', 'in', 'in'), '35.9"-37.0"');
  assert.strictEqual(U.display('28-30', 'in', 'in'), '28.0"-30.0"');
  assert.strictEqual(U.display('71.1-76.2', 'cm', 'cm'), '71.1-76.2');
  assert.strictEqual(U.display('33', 'cm', 'cm'), '33.0');
  assert.strictEqual(U.display('96.55', 'cm', 'cm'), '96.5');
});

test('cm to inch: converted from the exact stored value, then truncated (never whole-number rounding)', () => {
  assert.strictEqual(U.display('71.1-76.2', 'cm', 'in'), '27.9"-30.0"');
  assert.strictEqual(U.display('35.96-37.04', 'cm', 'in'), '14.1"-14.5"');
  assert.strictEqual(U.display('33', 'cm', 'in'), '12.9"'); // 12.992...
});

test('inch to cm', () => {
  assert.strictEqual(U.display('28-30', 'in', 'cm'), '71.1-76.2');
  assert.strictEqual(U.display('38.25', 'in', 'cm'), '97.1'); // 97.155 truncated
});

test('repeated switching never drifts: always derived from the stored original', () => {
  const orig = '71.1-76.2';
  const seen = new Set();
  for (let i = 0; i < 500; i++) seen.add(U.display(orig, 'cm', i % 2 ? 'cm' : 'in'));
  assert.deepStrictEqual([...seen].sort(), ['27.9"-30.0"', '71.1-76.2']);
});

test('mixed legacy units are converted per attribute', () => {
  assert.strictEqual(U.display('71.1', U.unit('Centimeters'), 'in'), '27.9"');
  assert.strictEqual(U.display('28', U.unit('Inches'), 'in'), '28.0"');
});

test('missing or invalid values never render a number', () => {
  for (const bad of [undefined, null, '', 'abc', '38-', '40-38', '1.234', 'NaN']) {
    assert.strictEqual(U.display(bad, 'in', 'cm'), '—', String(bad));
  }
});

test('unit spellings', () => {
  assert.strictEqual(U.unit(' Inch '), 'in');
  assert.strictEqual(U.unit('CM'), 'cm');
  assert.strictEqual(U.unit('kg'), null);
});
