/* Product image lightbox: click the main product image to view/zoom/pan it.
   Self-contained; the modal is built lazily and listeners exist only while open. */
(function () {
  'use strict';

  var MIN = 1, MAX = 5, STEP = 0.5, DBL = 2.5;

  var viewport = document.getElementById('main-product-image-container');
  var mainImg = document.getElementById('main-product-image');
  if (!viewport || !mainImg) return;

  // Make the existing main image area keyboard-operable (no visual change).
  viewport.setAttribute('role', 'button');
  viewport.setAttribute('tabindex', '0');
  viewport.setAttribute('aria-haspopup', 'dialog');
  viewport.setAttribute('aria-label', 'View larger image');

  var root, stage, img, zoomLabel, btnIn, btnOut, btnReset, closeBtn;
  var scale = 1, tx = 0, ty = 0, isOpen = false, prevOverflow = '', prevPad = '';
  var pointers = new Map(), pinch = null, drag = null, lastTap = 0, moved = false, closeTimer = 0;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  var hasState = false;   // we own one extra history entry while open
  var ignorePops = 0;     // popstates caused by our own history.back()

  // A reload while open keeps our marker in history.state; strip it (URL untouched).
  try {
    if (history.state && history.state.lb) {
      var st = Object.assign({}, history.state); delete st.lb;
      history.replaceState(Object.keys(st).length ? st : null, '');
    }
  } catch (e) {}

  // Device/browser Back while open: the entry is already popped, so just close.
  window.addEventListener('popstate', function () {
    if (ignorePops > 0) { ignorePops--; return; }
    if (isOpen) { hasState = false; close(); }
  });

  function hiResSrc() {
    var set = mainImg.getAttribute('srcset');
    if (set) {
      var parts = set.split(',').map(function (s) { return s.trim().split(/\s+/)[0]; }).filter(Boolean);
      if (parts.length) return parts[parts.length - 1];
    }
    return mainImg.currentSrc || mainImg.src;
  }

  function build() {
    root = document.createElement('div');
    root.className = 'lb';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Product image viewer');
    root.hidden = true;
    root.innerHTML =
      '<div class="lb-stage"><img class="lb-img" alt="" draggable="false"></div>' +
      '<button type="button" class="lb-btn lb-close" aria-label="Close image viewer">' +
      '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg></button>' +
      '<div class="lb-controls" role="group" aria-label="Zoom controls">' +
      '<button type="button" class="lb-btn" data-act="out" aria-label="Zoom out"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg></button>' +
      '<button type="button" class="lb-btn lb-reset" data-act="reset" aria-label="Reset zoom"><span class="lb-zoom" aria-live="polite">100%</span></button>' +
      '<button type="button" class="lb-btn" data-act="in" aria-label="Zoom in"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg></button>' +
      '</div>';
    document.body.appendChild(root);
    stage = root.querySelector('.lb-stage');
    img = root.querySelector('.lb-img');
    zoomLabel = root.querySelector('.lb-zoom');
    closeBtn = root.querySelector('.lb-close');
    btnIn = root.querySelector('[data-act="in"]');
    btnOut = root.querySelector('[data-act="out"]');
    btnReset = root.querySelector('[data-act="reset"]');
  }

  /* ── transform math ── */
  // Displayed (scale=1) size of the contained image inside the stage.
  function baseSize() {
    var sw = stage.clientWidth, sh = stage.clientHeight;
    var nw = img.naturalWidth || sw, nh = img.naturalHeight || sh;
    var r = Math.min(sw / nw, sh / nh);
    return { w: nw * r, h: nh * r, sw: sw, sh: sh };
  }
  function clampPan() {
    var b = baseSize();
    var maxX = Math.max(0, (b.w * scale - b.sw) / 2);
    var maxY = Math.max(0, (b.h * scale - b.sh) / 2);
    // When image is smaller than the stage on an axis, keep it centred.
    tx = b.w * scale <= b.sw ? 0 : Math.max(-maxX, Math.min(maxX, tx));
    ty = b.h * scale <= b.sh ? 0 : Math.max(-maxY, Math.min(maxY, ty));
  }
  function apply(animate) {
    clampPan();
    img.style.transition = animate && !reduce.matches ? 'transform 200ms cubic-bezier(.2,0,.2,1)' : 'none';
    img.style.transform = 'translate3d(' + tx + 'px,' + ty + 'px,0) scale(' + scale + ')';
    zoomLabel.textContent = Math.round(scale * 100) + '%';
    btnIn.disabled = scale >= MAX - 0.001;
    btnOut.disabled = scale <= MIN + 0.001;
    btnReset.disabled = scale === 1 && tx === 0 && ty === 0;
    stage.classList.toggle('is-zoomed', scale > 1.001);
  }
  // Zoom to `next`, keeping the point (cx, cy) (viewport coords) fixed.
  function zoomAt(next, cx, cy, animate) {
    next = Math.max(MIN, Math.min(MAX, next));
    var r = stage.getBoundingClientRect();
    var px = cx - (r.left + r.width / 2), py = cy - (r.top + r.height / 2);
    var k = next / scale;
    tx = px - (px - tx) * k;
    ty = py - (py - ty) * k;
    scale = next;
    if (scale === 1) { tx = 0; ty = 0; }
    apply(animate);
  }
  function centerZoom(next) {
    var r = stage.getBoundingClientRect();
    zoomAt(next, r.left + r.width / 2, r.top + r.height / 2, true);
  }
  function reset() { scale = 1; tx = 0; ty = 0; apply(true); }

  /* ── input handlers (attached on open, removed on close) ── */
  function onWheel(e) {
    e.preventDefault();
    var f = Math.exp(-e.deltaY * (e.ctrlKey ? 0.01 : 0.0022));
    zoomAt(scale * f, e.clientX, e.clientY, false);
  }
  function onPointerDown(e) {
    if (e.target.closest('.lb-btn, .lb-controls')) return;
    stage.setPointerCapture && stage.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    moved = false;
    if (pointers.size === 2) {
      var p = Array.from(pointers.values());
      pinch = { d: Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y), s: scale };
      drag = null;
    } else if (pointers.size === 1) {
      drag = { x: e.clientX, y: e.clientY, tx: tx, ty: ty };
    }
  }
  function onPointerMove(e) {
    if (!pointers.has(e.pointerId)) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pinch && pointers.size === 2) {
      var p = Array.from(pointers.values());
      var d = Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
      zoomAt(pinch.s * (d / pinch.d), (p[0].x + p[1].x) / 2, (p[0].y + p[1].y) / 2, false);
      moved = true;
    } else if (drag && scale > 1) {
      var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
      if (Math.abs(dx) + Math.abs(dy) > 3) moved = true;
      tx = drag.tx + dx; ty = drag.ty + dy;
      apply(false);
    } else if (drag && Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) > 3) {
      moved = true;
    }
  }
  function onPointerUp(e) {
    if (!pointers.has(e.pointerId)) return;
    var wasSingle = pointers.size === 1 && !pinch;
    pointers.delete(e.pointerId);
    if (pointers.size < 2) pinch = null;
    if (pointers.size === 1) {
      var rest = Array.from(pointers.values())[0];
      drag = { x: rest.x, y: rest.y, tx: tx, ty: ty };
    } else drag = null;
    if (e.type === 'pointerup' && wasSingle && !moved) {
      var now = Date.now();
      if (e.target === img || img.contains(e.target)) {
        if (now - lastTap < 320) {
          lastTap = 0;
          if (scale > 1.001) reset(); else zoomAt(DBL, e.clientX, e.clientY, true);
        } else lastTap = now;
      } else if (e.target === stage) {
        close(); // backdrop click (not image, not controls)
      }
    }
  }
  function onTouchMove(e) { e.preventDefault(); } // stop page/overscroll behind viewer
  function onKey(e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key === 'Tab') { // focus trap
      var f = Array.prototype.filter.call(root.querySelectorAll('button'), function (b) { return !b.disabled; });
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && (document.activeElement === first || !root.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      return;
    }
    if (e.key === '+' || e.key === '=') centerZoom(scale + STEP);
    else if (e.key === '-' || e.key === '_') centerZoom(scale - STEP);
    else if (e.key === '0') reset();
    else if (scale > 1 && e.key.indexOf('Arrow') === 0 && !e.target.closest('button')) {
      e.preventDefault();
      var s = 60;
      if (e.key === 'ArrowLeft') tx += s; else if (e.key === 'ArrowRight') tx -= s;
      else if (e.key === 'ArrowUp') ty += s; else ty -= s;
      apply(false);
    }
  }
  function onControls(e) {
    var b = e.target.closest('[data-act]');
    if (!b) return;
    var a = b.getAttribute('data-act');
    if (a === 'in') centerZoom(scale + STEP);
    else if (a === 'out') centerZoom(scale - STEP);
    else reset();
  }
  function onResize() { apply(false); }
  function onDbl(e) { e.preventDefault(); } // taps are handled via pointerup

  function listen(on) {
    var m = on ? 'addEventListener' : 'removeEventListener';
    stage[m]('wheel', onWheel, { passive: false });
    stage[m]('pointerdown', onPointerDown);
    stage[m]('pointermove', onPointerMove);
    stage[m]('pointerup', onPointerUp);
    stage[m]('pointercancel', onPointerUp);
    stage[m]('dblclick', onDbl);
    root[m]('touchmove', onTouchMove, { passive: false });
    root[m]('click', onControls);
    closeBtn[m]('click', close);
    document[m]('keydown', onKey);
    window[m]('resize', onResize);
  }

  function lockScroll() {
    var de = document.documentElement;
    var sbw = window.innerWidth - de.clientWidth;
    prevOverflow = de.style.overflow;
    prevPad = document.body.style.paddingRight;
    de.style.overflow = 'hidden';
    if (sbw > 0) document.body.style.paddingRight = sbw + 'px'; // avoid layout shift
  }
  function unlockScroll() {
    document.documentElement.style.overflow = prevOverflow;
    document.body.style.paddingRight = prevPad;
  }

  function open() {
    if (isOpen || ignorePops > 0) return; // ignorePops: previous close's back() still settling
    if (!root) build();
    clearTimeout(closeTimer);
    isOpen = true;
    scale = 1; tx = 0; ty = 0;
    img.alt = mainImg.alt || 'Product image';
    img.onerror = function () { img.onerror = null; img.src = mainImg.currentSrc || mainImg.src; };
    img.onload = function () { apply(false); };
    img.src = mainImg.currentSrc || mainImg.src; // instant (already cached)…
    var hi = hiResSrc();
    if (hi && hi !== img.src) { // …then swap in the sharpest candidate once loaded
      var pre = new Image();
      pre.onload = function () { if (isOpen) img.src = hi; };
      pre.src = hi;
    }
    try {
      history.pushState(Object.assign({}, history.state, { lb: 1 }), ''); // same URL
      hasState = true;
    } catch (e) { hasState = false; }
    root.hidden = false;
    lockScroll();
    apply(false);
    listen(true);
    void root.offsetWidth; // flush so the transition runs
    root.classList.add('is-open');
    closeBtn.focus({ preventScroll: true });
  }

  function close() {
    if (!isOpen) return;
    isOpen = false;
    listen(false);
    pointers.clear(); pinch = null; drag = null;
    root.classList.remove('is-open');
    unlockScroll();
    viewport.focus({ preventScroll: true });
    if (hasState) { // closed via button/backdrop/Escape: drop our history entry
      hasState = false;
      ignorePops++;
      history.back();
    }
    var done = function () { if (!isOpen) { root.hidden = true; img.removeAttribute('src'); } };
    if (reduce.matches) done(); else closeTimer = setTimeout(done, 220);
  }

  viewport.addEventListener('click', function (e) {
    if (e.target.closest('#detail-wishlist-trigger')) return;
    open();
  });
  viewport.addEventListener('keydown', function (e) {
    if (e.target !== viewport) return;
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
  });
})();
