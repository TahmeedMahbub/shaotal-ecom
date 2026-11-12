/* ============================================================
   DATA LAYER — Shaotal
   Catalog, categories, hero slides and navigation come from the
   database (server-rendered into window.__BOOT__ by the Blade layout).
   ============================================================ */

(function () {
  // Boot data is an application/json element (smaller than a JS string literal and not executed).
  const bootTag = document.getElementById('boot-data');
  if (!window.__BOOT__ && bootTag) {
    try { window.__BOOT__ = JSON.parse(bootTag.textContent); } catch (e) { window.__BOOT__ = {}; }
  }
  const BOOT = window.__BOOT__ || {};
  if (!window.__PAGE__) {
    try { window.__PAGE__ = JSON.parse((document.getElementById('page-data') || {}).textContent || '{}'); } catch (e) { window.__PAGE__ = {}; }
  }

  /* ── No inline handlers (CSP script-src 'self') ──
     Broken images fall back to the placeholder through one capturing listener instead of onerror="" attributes; images
     that failed before this script ran are fixed by the scan below. */
  const FALLBACK = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="500" viewBox="0 0 400 500"><rect width="400" height="500" fill="#efe9e0"/></svg>');
  const fallback = img => {
    if (!img || img.tagName !== 'IMG' || img.dataset.fallback || (img.currentSrc || img.src || '').startsWith('data:')) return;
    img.dataset.fallback = '1';
    img.removeAttribute('srcset');
    img.src = window.PLACEHOLDER_IMG || FALLBACK;
  };
  document.addEventListener('error', e => fallback(e.target), true);
  const scanImages = () => Array.from(document.images).forEach(img => {
    if (img.getAttribute('src') && img.complete && img.naturalWidth === 0) fallback(img);
  });
  scanImages();
  document.addEventListener('DOMContentLoaded', scanImages);

  /* Buttons/links declare what they do with data-action (replaces onclick=""). The functions live in app.js. */
  document.addEventListener('click', e => {
    const el = e.target.closest && e.target.closest('[data-action]');
    if (!el) return;
    const call = (name, ...args) => (typeof window[name] === 'function' ? window[name](...args) : undefined);
    switch (el.dataset.action) {
      case 'open-auth': e.preventDefault(); call('openAuthModal', el.dataset.authTab || 'login'); break;
      case 'toggle-cart': e.preventDefault(); call('toggleCart'); break;
      case 'open-cart': e.preventDefault(); call('openCart'); break;
      case 'toggle-wishlist': e.preventDefault(); call('toggleWishlistDrawer'); break;
      case 'open-variant': {
        e.preventDefault();
        const id = parseInt(el.dataset.productId, 10);
        call('openVariantModal', (window.PRODUCTS || []).find(p => p.id === id));
        break;
      }
      case 'close-mobile-nav': call('closeMobileNav'); break;   // a link: navigation continues
      default: break;
    }
  });

  /* ── Image URL helper (mirrors App\Support\Img in PHP — keep in sync) ──
     Adds responsive sizing to Cloudinary and Unsplash URLs only; stored URLs are never altered,
     and any other host (or a Cloudinary URL that already has a transformation) is returned as-is. */
  const CLD_TRANSFORM = /^(?:ar|a|b|bo|c|co|d|dl|dpr|e|f|fl|g|h|l|o|pg|q|r|t|u|w|x|y|z)_[^/]+$/i;
  window.imgUrl = function (url, width) {
    if (!url || typeof url !== 'string') return url;
    let u;
    try { u = new URL(url); } catch (e) { return url; }
    if (u.protocol !== 'https:' && u.protocol !== 'http:') return url;
    if (u.hostname === 'res.cloudinary.com') {
      const m = u.pathname.match(/^(\/[^/]+\/image\/upload\/)(.+)$/);
      if (!m) return url;
      let rest = m[2];
      const first = rest.split('/')[0];
      // Same rule as App\Support\Img: a plain width (w_1600) is replaced; any other transformation is kept as set.
      if (/^w_\d+$/.test(first) && rest.indexOf('/') > 0) rest = rest.slice(first.length + 1);
      else if (CLD_TRANSFORM.test(first)) return url;
      return u.origin + m[1] + 'f_auto,q_auto,c_limit,w_' + width + '/' + rest + u.search;
    }
    if (u.hostname === 'images.unsplash.com') {
      u.searchParams.set('w', width);
      if (!u.searchParams.has('q')) u.searchParams.set('q', '75');
      if (!u.searchParams.has('auto')) u.searchParams.set('auto', 'format');
      u.searchParams.delete('h');
      u.searchParams.delete('ar');
      return u.toString();
    }
    return url;
  };
  // "url 320w, url 640w" or '' when the URL cannot be transformed.
  window.imgSrcset = function (url, widths) {
    if (!url || window.imgUrl(url, widths[0]) === url) return '';
    return widths.map(w => window.imgUrl(url, w) + ' ' + w + 'w').join(', ');
  };

  window.SITE = Object.assign({
    brandName: 'Shaotal',
    logo: '/assets/logos/logo.png',
    tagline: 'Artisan Crafted. Curated for Australia.',
    currency: 'A$',
    countryCode: 'AU',
  }, BOOT.site || {});
  window.SITE.logo = '/assets/logos/logo.png';

  /* ── Catalog ── */
  const products = (BOOT.products || []);
  const categories = (BOOT.categories || []);
  const subcategories = (BOOT.subcategories || []);
  const brands = (BOOT.brands || []);

  window.CATEGORY_NAME_MAP = { preorder: 'Pre-order' };
  categories.forEach(c => { window.CATEGORY_NAME_MAP[c.slug] = c.name; });

  window.BADGE_MAP = {
    new: 'NEW',
    launch: 'LAUNCH OFFER',
    lowstock: 'LOW STOCK',
    preorder: 'PRE-ORDER',
  };

  // Hex (or name) -> display colour name across the whole catalog.
  window.COLOR_NAMES = {};
  products.forEach(p => Object.assign(window.COLOR_NAMES, p.colorNames || {}));

  window.PRODUCTS = products;
  window.productUrl = function (idOrProduct) {
    const p = typeof idOrProduct === 'object' ? idOrProduct : products.find(x => x.id === parseInt(idOrProduct, 10));
    return p ? '/products/' + encodeURIComponent(p.slug) : '/products';
  };

  /* ── Page-scoped catalog ──
     Pages embed only the products they render. Anything else (guest cart/wishlist, recently viewed) is fetched by id
     from /catalog/products and appended to the same array, so PRODUCTS.find() keeps working everywhere. */
  const byId = new Map(products.map(p => [p.id, p]));
  window.BongoCatalog = {
    has: id => byId.has(parseInt(id, 10)),
    add(list) {
      (list || []).forEach(p => {
        if (!p || !p.id) return;
        if (byId.has(p.id)) {
          const i = products.indexOf(byId.get(p.id));
          if (i >= 0) products[i] = p;
        } else {
          products.push(p);
        }
        byId.set(p.id, p);
        Object.assign(window.COLOR_NAMES, p.colorNames || {});
      });
    },
    // Resolves true once every id is known or confirmed unlisted (absent from the response); false on a network error,
    // so callers never treat "could not check" as "product gone".
    ensure(ids) {
      const need = Array.from(new Set((ids || []).map(i => parseInt(i, 10)).filter(i => i > 0 && !byId.has(i))));
      if (!need.length) return Promise.resolve(true);
      const chunks = [];
      for (let i = 0; i < need.length; i += 60) chunks.push(need.slice(i, i + 60));
      return Promise.all(chunks.map(c => fetch('/catalog/products?ids=' + c.join(','), { headers: { Accept: 'application/json' }, credentials: 'omit' })
        .then(r => (r.ok ? r.json() : Promise.reject(r.status)))
        .then(d => window.BongoCatalog.add(d.products))))
        .then(() => true, () => false);
    },
  };

  window.BongoProducts = {
    getAll: () => products,
    getById: id => products.find(p => p.id === parseInt(id, 10)),
  };
  window.BongoCategories = { getAll: () => categories };
  window.BongoSubcategories = { getAll: () => subcategories };
  window.BongoBrands = { getAll: () => brands };

  /* ── Home page category cards ── */
  window.CATEGORIES = categories.map(c => ({
    name: c.name, slug: c.slug, desc: c.desc, image: c.image, gradient: 'linear-gradient(135deg, #7A5C43, #4A3322)',
  }));

  /* ── Hero ── */
  window.HERO_SLIDES = (BOOT.hero || []).map(h => ({
    id: h.id, label: '', headline: h.headline, subtext: h.subtext, cta: h.cta, ctaLink: h.ctaLink,
    secondaryCta: '', secondaryCtaLink: '', image: h.image, imageSrc: h.imageSrc || h.image, imageSrcMobile: h.imageSrcMobile || h.imageSrc || h.image,
    srcset: h.srcset || '', srcsetMobile: h.srcsetMobile || '',
  }));

  /* ── Navigation (categories + sub-categories from the database) ── */
  const nav = [{ name: 'New Arrivals', slug: 'new-arrivals', filterCategory: 'all', customUrl: '/products?sort=newest', megaMenu: null }];
  categories.forEach(c => {
    const subs = subcategories.filter(s => s.category === c.slug);
    // Group subcategories into mega-menu columns by their "section" (subcategories.section);
    // subcategories with no section fall back to a column headed by the category name.
    // Column order follows the order sections first appear in (i.e. subcategory sort_order).
    const sections = [];
    const bySection = new Map();
    subs.forEach(s => {
      const key = s.section || c.name;
      if (!bySection.has(key)) { bySection.set(key, []); sections.push(key); }
      bySection.get(key).push({ label: s.name, slug: c.slug, sub: s.slug });
    });
    nav.push({
      name: c.name, slug: c.slug, filterCategory: c.slug,
      megaMenu: subs.length ? sections.map(heading => ({ heading, links: bySection.get(heading) })) : null,
    });
  });
//   nav.push({ name: 'Pre-order', slug: 'preorder', filterCategory: 'preorder', megaMenu: null });
  nav.push({ name: 'Bulk Orders', slug: 'bulk-orders', filterCategory: 'bulk', customUrl: '/bulk-order', megaMenu: null });
  window.NAV_CATEGORIES = nav;

  /* ── Footer ── */
  window.FOOTER_LINKS = {
    shop: [
      { label: 'All Products', href: '/products' },
      { label: 'New Arrivals', href: '/products?sort=newest' },
    ].concat(categories.map(c => ({ label: c.name, href: '/products?category=' + c.slug })))
     .concat([{ label: 'Pre-order', href: '/products?category=preorder' }]),
    help: [
      { label: 'My Account', href: '/account' },
      { label: 'Contact Us', href: '/contact' },
      { label: 'Shipping', href: '/shipping' },
      { label: 'Returns', href: '/returns' },
      { label: 'Request Product Sourcing', href: '/request' },
      { label: 'Bulk & Corporate Orders', href: '/bulk-order' },
    ],
    about: [
      { label: 'Our Story', href: '/#bangladesh-made-section' },
      { label: 'Artisan Heritage', href: '/#bangladesh-made-section' },
      { label: 'Terms of Sale', href: '/terms' },
      { label: 'Privacy', href: '/privacy' },
    ],
  };
})();

/* Top-level aliases used by the page scripts. */
var SITE = window.SITE;
var CATEGORY_NAME_MAP = window.CATEGORY_NAME_MAP;
var BADGE_MAP = window.BADGE_MAP;
var NAV_CATEGORIES = window.NAV_CATEGORIES;
var HERO_SLIDES = window.HERO_SLIDES;
var CATEGORIES = window.CATEGORIES;
var FOOTER_LINKS = window.FOOTER_LINKS;
var PRODUCTS = window.PRODUCTS;
