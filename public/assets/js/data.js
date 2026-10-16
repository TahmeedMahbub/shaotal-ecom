/* ============================================================
   DATA LAYER — Bongo Curated
   Catalog, categories, hero slides and navigation come from the
   database (server-rendered into window.__BOOT__ by the Blade layout).
   ============================================================ */

(function () {
  const BOOT = window.__BOOT__ || {};

  window.SITE = Object.assign({
    brandName: 'Bongo Curated',
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
    secondaryCta: '', secondaryCtaLink: '', image: h.image,
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
