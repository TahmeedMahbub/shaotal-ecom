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
      if (!m || CLD_TRANSFORM.test(m[2].split('/')[0])) return url;
      return u.origin + m[1] + 'f_auto,q_auto,c_limit,w_' + width + '/' + m[2] + u.search;
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
    secondaryCta: '', secondaryCtaLink: '', image: h.image, imageSrc: h.imageSrc || h.image,
  }));

  /* ── Navigation (categories + sub-categories from the database) ── */
  const nav = [{ name: 'New Arrivals', slug: 'new-arrivals', filterCategory: 'all', customUrl: '/products?sort=newest', megaMenu: null }];
  categories.forEach(c => {
    const subs = subcategories.filter(s => s.category === c.slug);
    nav.push({
      name: c.name, slug: c.slug, filterCategory: c.slug,
      megaMenu: subs.length ? [{
        heading: c.name,
        links: subs.map(s => ({ label: s.name, slug: c.slug, sub: s.slug })),
      }] : null,
    });
  });
  nav.push({ name: 'Pre-order', slug: 'preorder', filterCategory: 'preorder', megaMenu: null });
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
      { label: 'Request Product Sourcing', href: '/request' },
      { label: 'Bulk & Corporate Orders', href: '/bulk-order' },
      { label: 'Contact Us', href: '/request' },
    ],
    about: [
      { label: 'Our Story', href: '/#bangladesh-made-section' },
      { label: 'Artisan Heritage', href: '/#bangladesh-made-section' },
      { label: 'Custom Sourcing', href: '/request' },
      { label: 'Bulk Orders', href: '/bulk-order' },
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
