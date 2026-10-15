/* ============================================================
   DATA LAYER — Bongo Curated
   Clean Launch Phase Data with Strikethrough Original Prices
   ============================================================ */

const SITE = {
  brandName: 'Bongo Curated',
  logo: 'assets/logos/logo.png',
  tagline: 'Artisan Crafted. Curated for Australia.',
  announcementMessage: 'Discover distinctive handcrafted pieces, curated for Australia.',
  announcementLink: '#products-section',
  currency: 'A$',
  countryCode: 'AU',
};

const CATEGORY_NAME_MAP = {
  fashion: 'Fashion',
  'home-decor': 'Home & Living',
  jute: 'Jute',
  handicrafts: 'Handcrafted',
  lifestyle: 'Gifts',
  preorder: 'Pre-order',
};

const BADGE_MAP = {
  new: 'NEW',
  launch: 'LAUNCH OFFER',
  lowstock: 'LOW STOCK',
  preorder: 'PRE-ORDER',
};

const NAV_CATEGORIES = [
  {
    name: 'New Arrivals',
    slug: 'new-arrivals',
    filterCategory: 'all',
    megaMenu: null,
  },
  {
    name: 'Fashion',
    slug: 'fashion',
    filterCategory: 'fashion',
    megaMenu: [
      {
        heading: "Men's Apparel",
        links: [
          { label: 'Heavyweight T-Shirts', slug: 'fashion' },
          { label: 'Pure Linen Shirts', slug: 'fashion' },
          { label: 'Piqué Polos', slug: 'fashion' },
          { label: 'Everyday Chinos & Trousers', slug: 'fashion' },
        ],
      },
      {
        heading: "Women's Apparel",
        links: [
          { label: 'Linen Tunics & Tops', slug: 'fashion' },
          { label: 'Flowing Midi Dresses', slug: 'fashion' },
          { label: 'Relaxed Trousers', slug: 'fashion' },
        ],
      },
    ],
  },
  {
    name: 'Home & Living',
    slug: 'home-living',
    filterCategory: 'home-decor',
    megaMenu: [
      {
        heading: 'Living & Dining',
        links: [
          { label: 'Nakshi Kantha Cushions', slug: 'home-decor' },
          { label: 'Terracotta & Ceramics', slug: 'home-decor' },
          { label: 'Artisan Wood Bowls', slug: 'home-decor' },
          { label: 'Handwoven Runners', slug: 'home-decor' },
        ],
      },
      {
        heading: 'Decorative Accents',
        links: [
          { label: 'Hammered Brass Vessels', slug: 'home-decor' },
          { label: 'Bamboo Lightware', slug: 'home-decor' },
          { label: 'Wall Decor', slug: 'home-decor' },
        ],
      },
    ],
  },
  {
    name: 'Jute',
    slug: 'jute',
    filterCategory: 'jute',
    megaMenu: [
      {
        heading: 'Golden Fiber Collection',
        links: [
          { label: 'Handwoven Jute Tote Bags', slug: 'jute' },
          { label: 'Braided Storage Baskets', slug: 'jute' },
          { label: 'Round Jute Floor Mats & Rugs', slug: 'jute' },
          { label: 'Eco Planter Covers', slug: 'jute' },
        ],
      },
    ],
  },
  {
    name: 'Handcrafted',
    slug: 'handicrafts',
    filterCategory: 'handicrafts',
    megaMenu: [
      {
        heading: 'Artisan Treasures',
        links: [
          { label: 'Heirloom Nakshi Kantha Quilts', slug: 'heritage' },
          { label: 'Handspun Jamdani Weaves', slug: 'heritage' },
          { label: 'Vintage Brass Collectibles', slug: 'handicrafts' },
        ],
      },
    ],
  },
  {
    name: 'Gifts',
    slug: 'gifts',
    filterCategory: 'lifestyle',
    megaMenu: null,
  },
  {
    name: 'Pre-order',
    slug: 'preorder',
    filterCategory: 'preorder',
    megaMenu: null,
  },
  {
    name: 'Bulk Orders',
    slug: 'bulk-orders',
    filterCategory: 'bulk',
    customUrl: 'bulk-order.html',
    megaMenu: null,
  },
];

const HERO_SLIDES = [
  {
    id: 1,
    label: 'ARTISAN CRAFTED · CURATED FOR AUSTRALIA',
    headline: 'Thoughtfully Crafted. Curated for Australia.',
    subtext: 'Discover ethically made fashion, handcrafted home decor, natural golden jute, and timeless lifestyle essentials.',
    cta: 'Shop Collection',
    ctaLink: '#products-section',
    secondaryCta: 'Explore Categories',
    secondaryCtaLink: '#categories-section',
    image: 'assets/images/hero/hero-1.png',
  },
  {
    id: 2,
    label: 'NATURAL LIVING · HANDMADE TEXTILES',
    headline: 'Warmth & Character for Modern Spaces',
    subtext: 'Intricately embroidered Nakshi Kantha textiles, artisanal brassware, and earthen vessels crafted by heritage makers.',
    cta: 'Shop Home & Living',
    ctaLink: 'products.html?category=home-decor',
    secondaryCta: 'Discover Jute Range',
    secondaryCtaLink: 'products.html?category=jute',
    image: 'assets/images/hero/hero-homex.jpg',
  },
  {
    id: 3,
    label: 'HEIRLOOM HERITAGE · TIMELESS WEAVES',
    headline: 'Distinctive Masterpieces Born from Heritage',
    subtext: 'Connecting master South Asian artisan communities directly with Australian homes and modern wardrobes.',
    cta: 'Explore Handcrafted',
    ctaLink: 'products.html?category=handicrafts',
    secondaryCta: 'Curated Gifts',
    secondaryCtaLink: 'products.html?category=lifestyle',
    image: 'assets/images/lifestyle/craft-story.jpg',
  },
];

const CATEGORIES = [
  {
    name: 'Fashion',
    slug: 'fashion',
    desc: 'Everyday clothing, relaxed fits, linen pieces and modern essentials.',
    image: 'assets/images/products/shirt-white.png',
    gradient: 'linear-gradient(135deg, #7A5C43, #4A3322)',
  },
  {
    name: 'Home & Living',
    slug: 'home-decor',
    desc: 'Handcrafted accents, textiles, ceramics and natural materials for modern Australian spaces.',
    image: 'assets/images/products/nakshi-kantha.jpg',
    gradient: 'linear-gradient(135deg, #9C6644, #5E3821)',
  },
  {
    name: 'Jute',
    slug: 'jute',
    desc: 'Practical and beautiful products crafted from natural golden jute fiber.',
    image: 'assets/images/lifestyle/jute-showcase.jpg',
    gradient: 'linear-gradient(135deg, #B58A55, #6E4D25)',
  },
  // {
  //   name: 'Handcrafted',
  //   slug: 'handicrafts',
  //   desc: 'Distinctive pieces made by skilled master artisans and craftspeople.',
  //   image: 'assets/images/products/artisan-brass.jpg',
  //   gradient: 'linear-gradient(135deg, #7D6B58, #473B2F)',
  // },
  {
    name: 'Gifts',
    slug: 'lifestyle',
    desc: 'Thoughtful products made even more special with gift wrapping and personalised notes.',
    image: 'assets/images/products/leather-journal.jpg',
    gradient: 'linear-gradient(135deg, #606C38, #283618)',
  },
];

const INITIAL_PRODUCTS = [
  // Fashion
  {
    id: 1,
    name: 'Essential Heavyweight 240GSM Tee',
    desc: '100% Organic Combed Cotton · Relaxed Fit',
    price: 26.00,
    originalPrice: 38.00,
    image: 'assets/images/products/tshirt-olive.png',
    badge: 'new',
    category: 'fashion',
    brand: 'Heritage Weaves',
    colors: ['#5C6B4F', '#1A1A1A', '#F5F0E8'],
    sizes: ['S', 'M', 'L', 'XL', '2XL', '3XL'],
    outOfStockSizes: [],
    slug: 'essential-heavyweight-tee',
    availability: 'instock',
  },
  {
    id: 2,
    name: 'Pure Flax Linen Casual Shirt',
    desc: 'Breathable Pure Linen · Relaxed Collar',
    price: 39.00,
    originalPrice: 55.00,
    image: 'assets/images/products/shirt-white.png',
    badge: 'launch',
    category: 'fashion',
    brand: 'Heritage Weaves',
    colors: ['#FFFFFF', '#E8DDD0', '#8BA5B5'],
    sizes: ['S', 'M', 'L', 'XL', '2XL'],
    outOfStockSizes: ['S'],
    slug: 'pure-linen-casual-shirt',
    availability: 'instock',
  },
  {
    id: 3,
    name: 'Tailored Everyday Chinos',
    desc: 'Stretch Twill Cotton · Clean Tapered Cut',
    price: 38.00,
    originalPrice: 52.00,
    image: 'assets/images/products/trousers-khaki.png',
    badge: 'lowstock',
    category: 'fashion',
    brand: 'Heritage Weaves',
    colors: ['#C4A882', '#1A1A1A', '#3A3A3A'],
    sizes: ['30', '32', '34', '36', '38'],
    outOfStockSizes: [],
    slug: 'tailored-everyday-chinos',
    availability: 'lowstock',
  },
  {
    id: 4,
    name: 'Piqué Cotton Pima Polo',
    desc: 'Refined Collar · Breathable Double Piqué Weave',
    price: 40.00,
    originalPrice: 58.00,
    image: 'assets/images/products/polo-navy.png',
    badge: 'preorder',
    category: 'fashion',
    brand: 'Bongo Curated',
    colors: ['#1B2A4A', '#1A1A1A', '#5C6B4F'],
    sizes: ['S', 'M', 'L', 'XL', '2XL'],
    outOfStockSizes: [],
    slug: 'pique-cotton-polo',
    availability: 'preorder',
  },
  {
    id: 5,
    name: 'Relaxed Linen Midi Tunic',
    desc: 'Pure Natural Linen · Effortless Everyday Cut',
    price: 44.00,
    originalPrice: 62.00,
    image: 'assets/images/products/shirt-white.png',
    badge: 'new',
    category: 'fashion',
    brand: 'Heritage Weaves',
    colors: ['#FFFFFF', '#C4A882'],
    sizes: ['S', 'M', 'L', 'XL'],
    outOfStockSizes: [],
    slug: 'relaxed-linen-midi-tunic',
    availability: 'instock',
  },

  // Home Decor
  {
    id: 6,
    name: 'Hand-Embroidered Nakshi Kantha Cushion',
    desc: 'Artisan Needlework on Sand Linen · 45x45cm Cover',
    price: 34.00,
    originalPrice: 48.00,
    image: 'assets/images/products/nakshi-kantha.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#D6C2A8', '#8C4F3B', '#384D48'],
    sizes: ['45x45 cm', '50x50 cm'],
    outOfStockSizes: [],
    slug: 'nakshi-kantha-cushion',
    availability: 'instock',
  },
  {
    id: 7,
    name: 'Hand-Carved Mango Wood Decorative Bowl',
    desc: 'Mango Wood · Natural Matte Finish',
    price: 42.00,
    originalPrice: 58.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#A06F43', '#5E3A1C'],
    sizes: ['25 cm', '32 cm'],
    outOfStockSizes: [],
    slug: 'mango-wood-bowl',
    availability: 'instock',
  },
  {
    id: 8,
    name: 'Terracotta Artisan Planter Vessel',
    desc: 'Natural Clay · Hand-Thrown Earthenware',
    price: 28.00,
    originalPrice: 38.00,
    image: 'assets/images/lifestyle/jute-showcase.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#8C4F3B', '#D6C2A8'],
    sizes: ['Medium', 'Large'],
    outOfStockSizes: [],
    slug: 'terracotta-artisan-planter',
    availability: 'instock',
  },

  // Jute
  {
    id: 9,
    name: 'Handwoven Golden Jute Round Tote',
    desc: '100% Natural Golden Jute · Cotton Lining',
    price: 36.00,
    originalPrice: 49.00,
    image: 'assets/images/lifestyle/jute-showcase.jpg',
    badge: 'launch',
    category: 'jute',
    brand: 'Sonali Jute',
    colors: ['#C8A870', '#3D342A'],
    sizes: ['Medium', 'Large'],
    outOfStockSizes: [],
    slug: 'golden-jute-round-tote',
    availability: 'instock',
  },
  {
    id: 10,
    name: 'Braided Jute Nesting Storage Baskets',
    desc: 'Natural Eco-Fiber · Handcrafted Storage',
    price: 54.00,
    originalPrice: 75.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'lowstock',
    category: 'jute',
    brand: 'Sonali Jute',
    colors: ['#D3B382', '#967850'],
    sizes: ['Set of 3 (S/M/L)'],
    outOfStockSizes: [],
    slug: 'braided-jute-nesting-baskets',
    availability: 'lowstock',
  },
  {
    id: 11,
    name: 'Natural Jute Runner & Placemat Set',
    desc: 'Braided Fiber · Heat Resistant Dining Set',
    price: 32.00,
    originalPrice: 45.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'preorder',
    category: 'jute',
    brand: 'Sonali Jute',
    colors: ['#C8A870'],
    sizes: ['Standard Set'],
    outOfStockSizes: [],
    slug: 'natural-jute-runner-set',
    availability: 'preorder',
  },

  // Handicrafts
  {
    id: 12,
    name: 'Hammered Brass Vessel Set',
    desc: 'Solid Brass · Traditional Metal Craftsmanship',
    price: 38.00,
    originalPrice: 52.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'new',
    category: 'handicrafts',
    brand: 'Artisan Craft Co.',
    colors: ['#C5A059', '#7D6331'],
    sizes: ['Standard Duo'],
    outOfStockSizes: [],
    slug: 'hammered-brass-tea-light-set',
    availability: 'instock',
  },
  {
    id: 13,
    name: 'Heirloom Jamdani Woven Throw',
    desc: 'Hand-Spun Fine Cotton · Traditional Motif',
    price: 58.00,
    originalPrice: 85.00,
    image: 'assets/images/products/nakshi-kantha.jpg',
    badge: 'preorder',
    category: 'handicrafts',
    brand: 'Bongo Curated',
    colors: ['#F5F0E8', '#1A1A1A'],
    sizes: ['140x200 cm'],
    outOfStockSizes: [],
    slug: 'heirloom-jamdani-throw',
    availability: 'preorder',
  },

  // Gifts
  {
    id: 14,
    name: 'Handcrafted Leather Journal Set',
    desc: 'Full-Grain Leather · Recycled Jute Paper',
    price: 46.00,
    originalPrice: 65.00,
    image: 'assets/images/products/leather-journal.jpg',
    badge: 'launch',
    category: 'lifestyle',
    brand: 'Bongo Curated',
    colors: ['#6B3E26', '#2B1E16'],
    sizes: ['A5 Journal + Pen'],
    outOfStockSizes: [],
    slug: 'handcrafted-leather-journal-set',
    availability: 'instock',
  },
  {
    id: 15,
    name: 'Brass & Leather Desk Accessory Set',
    desc: 'Handcrafted Brass Tray & Pen Holder',
    price: 48.00,
    originalPrice: 68.00,
    image: 'assets/images/products/leather-journal.jpg',
    badge: 'new',
    category: 'lifestyle',
    brand: 'Bongo Curated',
    colors: ['#C5A059', '#6B3E26'],
    sizes: ['3-Piece Set'],
    outOfStockSizes: [],
    slug: 'brass-leather-desk-set',
    availability: 'instock',
  },
  {
    id: 16,
    name: 'Artisan Glazed Ceramic Tea Set',
    desc: 'Hand-Thrown Clay · Matte Mineral Glaze',
    price: 52.00,
    originalPrice: 72.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#384D48', '#D6C2A8'],
    sizes: ['Teapot + 4 Cups'],
    outOfStockSizes: [],
    slug: 'artisan-glazed-ceramic-tea-set',
    availability: 'instock',
  },
  { 
    id: 17, 
    name: 'Premium Cotton Oxford Shirt', 
    desc: '100% Combed Cotton · Classic Oxford Weave', 
    price: 42.00, 
    originalPrice: 59.00, 
    image: 'assets/images/products/shirt-white.png', 
    badge: 'new', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#FFFFFF', '#D8D1C5', '#1B2A4A'], 
    sizes: ['S', 'M', 'L', 'XL', '2XL'], 
    outOfStockSizes: [], 
    slug: 'premium-cotton-oxford-shirt', 
    availability: 'instock', 
  },
  { 
    id: 18, 
    name: 'Everyday Cotton Overshirt', 
    desc: 'Heavyweight Cotton · Relaxed Utility Fit', 
    price: 45.00, 
    originalPrice: 64.00, 
    image: 'assets/images/products/shirt-white.png', 
    badge: 'launch', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#C4A882', '#5C6B4F', '#1A1A1A'], 
    sizes: ['S', 'M', 'L', 'XL'], 
    outOfStockSizes: [], 
    slug: 'everyday-cotton-overshirt', 
    availability: 'instock', 
  },
  { 
    id: 19, 
    name: 'Soft Cotton Lounge Tee', 
    desc: 'Brushed Cotton Jersey · Relaxed Everyday Fit', 
    price: 24.00, 
    originalPrice: 34.00, 
    image: 'assets/images/products/tshirt-olive.png', 
    badge: 'new', 
    category: 'fashion', 
    brand: 'Bongo Curated', 
    colors: ['#5C6B4F', '#F5F0E8', '#3A3A3A'], 
    sizes: ['S', 'M', 'L', 'XL', '2XL'], 
    outOfStockSizes: ['2XL'], 
    slug: 'soft-cotton-lounge-tee', 
    availability: 'instock', 
  },
  { 
    id: 20, 
    name: 'Textured Cotton Henley', 
    desc: 'Textured Cotton Jersey · Three-Button Placket', 
    price: 32.00, 
    originalPrice: 46.00, 
    image: 'assets/images/products/tshirt-olive.png', 
    badge: 'lowstock', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#6F765F', '#D6C2A8', '#1A1A1A'], 
    sizes: ['S', 'M', 'L', 'XL'], 
    outOfStockSizes: [], 
    slug: 'textured-cotton-henley', 
    availability: 'lowstock', 
  },
  { 
    id: 21, 
    name: 'Classic Cotton Crewneck', 
    desc: 'Premium Cotton Jersey · Clean Regular Fit', 
    price: 28.00, 
    originalPrice: 40.00, 
    image: 'assets/images/products/tshirt-olive.png', 
    badge: 'preorder', 
    category: 'fashion', 
    brand: 'Bongo Curated', 
    colors: ['#1A1A1A', '#F5F0E8', '#5C6B4F'], 
    sizes: ['S', 'M', 'L', 'XL', '2XL'], 
    outOfStockSizes: [], 
    slug: 'classic-cotton-crewneck', 
    availability: 'preorder', 
  },
  { 
    id: 22, 
    name: 'Lightweight Cotton Summer Shirt', 
    desc: 'Lightweight Cotton Poplin · Relaxed Short Sleeve', 
    price: 36.00, 
    originalPrice: 49.00, 
    image: 'assets/images/products/shirt-white.png', 
    badge: 'new', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#FFFFFF', '#8BA5B5', '#C4A882'], 
    sizes: ['S', 'M', 'L', 'XL'], 
    outOfStockSizes: [], 
    slug: 'lightweight-cotton-summer-shirt', 
    availability: 'instock', 
  },
  { 
    id: 23, 
    name: 'Washed Cotton Casual Shirt', 
    desc: 'Garment-Washed Cotton · Soft Relaxed Texture', 
    price: 39.00, 
    originalPrice: 55.00, 
    image: 'assets/images/products/shirt-white.png', 
    badge: 'launch', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#D8D1C5', '#6F765F', '#384D48'], 
    sizes: ['S', 'M', 'L', 'XL', '2XL'], 
    outOfStockSizes: [], 
    slug: 'washed-cotton-casual-shirt', 
    availability: 'instock', 
  },
  { 
    id: 24, 
    name: 'Cotton Lounge Shorts', 
    desc: 'Soft Cotton Jersey · Elastic Waistband', 
    price: 26.00, 
    originalPrice: 36.00, 
    image: 'assets/images/products/trousers-khaki.png', 
    badge: 'lowstock', 
    category: 'fashion', 
    brand: 'Bongo Curated', 
    colors: ['#C4A882', '#5C6B4F', '#1A1A1A'], 
    sizes: ['S', 'M', 'L', 'XL'], 
    outOfStockSizes: ['L'], 
    slug: 'cotton-lounge-shorts', 
    availability: 'lowstock', 
  },
  { 
    id: 25, 
    name: 'Organic Cotton Ribbed Tank', 
    desc: 'Organic Cotton Rib Knit · Comfortable Everyday Layer', 
    price: 22.00, 
    originalPrice: 31.00, 
    image: 'assets/images/products/tshirt-olive.png', 
    badge: 'new', 
    category: 'fashion', 
    brand: 'Heritage Weaves', 
    colors: ['#F5F0E8', '#1A1A1A', '#6F765F'], 
    sizes: ['S', 'M', 'L', 'XL'], 
    outOfStockSizes: [], 
    slug: 'organic-cotton-ribbed-tank', 
    availability: 'instock', 
  },
  { 
    id: 26, 
    name: 'Heritage Cotton Kurta', 
    desc: 'Fine Cotton Weave · Modern Relaxed Silhouette', 
    price: 48.00, 
    originalPrice: 68.00, 
    image: 'assets/images/products/shirt-white.png', 
    badge: 'preorder', 
    category: 'fashion', 
    brand: 'Bongo Curated', 
    colors: ['#F5F0E8', '#D6C2A8', '#384D48'], 
    sizes: ['S', 'M', 'L', 'XL', '2XL'], 
    outOfStockSizes: [], 
    slug: 'heritage-cotton-kurta', 
    availability: 'preorder', 
  },

  // Additional Home & Living pieces for multi-page category sliding
  {
    id: 27,
    name: 'Handwoven Dhurrie Floor Runner',
    desc: 'Pure Natural Cotton & Jute · 60x180cm Runner',
    price: 49.00,
    originalPrice: 68.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#D6C2A8', '#384D48'],
    sizes: ['60x180 cm', '80x240 cm'],
    outOfStockSizes: [],
    slug: 'handwoven-dhurrie-floor-runner',
    availability: 'instock',
  },
  {
    id: 28,
    name: 'Hammered Solid Brass Table Vessel',
    desc: 'Hand-Hammered Solid Brass · Antique Gold Finish',
    price: 44.00,
    originalPrice: 60.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#C5A059', '#7D6331'],
    sizes: ['Medium', 'Large'],
    outOfStockSizes: [],
    slug: 'hammered-brass-table-vessel',
    availability: 'instock',
  },
  {
    id: 29,
    name: 'Bamboo & Woven Cane Accent Lantern',
    desc: 'Sustainable Natural Cane · Warm Diffused Glow',
    price: 38.00,
    originalPrice: 52.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#D6C2A8'],
    sizes: ['Standard'],
    outOfStockSizes: [],
    slug: 'bamboo-cane-accent-lantern',
    availability: 'instock',
  },
  {
    id: 30,
    name: 'Nakshi Kantha Heritage Table Runner',
    desc: 'Hand-Embroidered Motifs on Raw Cotton Canvas',
    price: 36.00,
    originalPrice: 50.00,
    image: 'assets/images/products/nakshi-kantha.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#8C4F3B', '#D6C2A8'],
    sizes: ['40x150 cm'],
    outOfStockSizes: [],
    slug: 'nakshi-kantha-table-runner',
    availability: 'instock',
  },
  {
    id: 31,
    name: 'Artisan Ceramic Serving Platter',
    desc: 'Hand-Molded Stoneware · Matte Mineral Finish',
    price: 46.00,
    originalPrice: 65.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'lowstock',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#384D48', '#5E3A1C'],
    sizes: ['30x20 cm'],
    outOfStockSizes: [],
    slug: 'artisan-ceramic-serving-platter',
    availability: 'lowstock',
  },
  {
    id: 32,
    name: 'Carved Teakwood Decorative Coaster Set',
    desc: 'Reclaimed Teakwood · Hand-Chiseled Set of 4',
    price: 24.00,
    originalPrice: 34.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#A06F43', '#5E3A1C'],
    sizes: ['Set of 4'],
    outOfStockSizes: [],
    slug: 'carved-teakwood-coaster-set',
    availability: 'instock',
  },
  {
    id: 33,
    name: 'Kalamkari Handblock Print Cushion',
    desc: 'Natural Vegetable Dyes on Pure Cotton Duck',
    price: 32.00,
    originalPrice: 45.00,
    image: 'assets/images/products/nakshi-kantha.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#8C4F3B', '#384D48'],
    sizes: ['45x45 cm'],
    outOfStockSizes: [],
    slug: 'kalamkari-handblock-cushion',
    availability: 'instock',
  },
  {
    id: 34,
    name: 'Earthen Terracotta Oil Burner',
    desc: 'Handcrafted Clay · Subtle Aromatherapy Diffuser',
    price: 26.00,
    originalPrice: 36.00,
    image: 'assets/images/lifestyle/jute-showcase.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#8C4F3B'],
    sizes: ['Standard'],
    outOfStockSizes: [],
    slug: 'earthen-terracotta-oil-burner',
    availability: 'instock',
  },
  {
    id: 35,
    name: 'Hand-Spun Cotton Textured Throw',
    desc: 'Artisan Loom-Woven Cotton with Fringed Hem',
    price: 56.00,
    originalPrice: 78.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'preorder',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#F5F0E8', '#8C4F3B'],
    sizes: ['130x170 cm'],
    outOfStockSizes: [],
    slug: 'handspun-cotton-textured-throw',
    availability: 'preorder',
  },
  {
    id: 36,
    name: 'Brass Minimalist Pillar Candleholder',
    desc: 'Solid Cast Brass · Warm Brushed Luster',
    price: 34.00,
    originalPrice: 48.00,
    image: 'assets/images/products/artisan-brass.jpg',
    badge: 'new',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#C5A059'],
    sizes: ['Small', 'Tall'],
    outOfStockSizes: [],
    slug: 'brass-minimalist-candleholder',
    availability: 'instock',
  },
  {
    id: 37,
    name: 'Stoneware Glazed Pouring Pitcher',
    desc: 'Handcrafted Ceramic Jug for Table & Florals',
    price: 38.00,
    originalPrice: 54.00,
    image: 'assets/images/lifestyle/furnitures.jpg',
    badge: 'launch',
    category: 'home-decor',
    brand: 'Artisan Craft Co.',
    colors: ['#384D48', '#D6C2A8'],
    sizes: ['1.2 Litres'],
    outOfStockSizes: [],
    slug: 'stoneware-glazed-pouring-pitcher',
    availability: 'instock',
  }
];

/* ============================================================
   DYNAMIC CATALOG DATA STORES (localStorage Synchronizer)
   ============================================================ */

// 1. CATEGORIES STORE
const INITIAL_CATEGORIES = [
  { id: 1, name: 'Fashion', slug: 'fashion', desc: 'Everyday clothing, relaxed fits, linen pieces and modern essentials.', image: 'assets/images/products/shirt-white.png', isDisabled: false },
  { id: 2, name: 'Home & Living', slug: 'home-decor', desc: 'Handcrafted accents, textiles, ceramics and natural materials for modern spaces.', image: 'assets/images/products/nakshi-kantha.jpg', isDisabled: false },
  { id: 3, name: 'Jute', slug: 'jute', desc: 'Practical and beautiful products crafted from natural golden jute fiber.', image: 'assets/images/lifestyle/jute-showcase.jpg', isDisabled: false },
  { id: 4, name: 'Handcrafted', slug: 'handicrafts', desc: 'Distinctive pieces made by skilled master artisans and craftspeople.', image: 'assets/images/products/artisan-brass.jpg', isDisabled: false },
  { id: 5, name: 'Gifts', slug: 'lifestyle', desc: 'Thoughtful products made even more special with gift wrapping and notes.', image: 'assets/images/products/leather-journal.jpg', isDisabled: false },
];

window.BongoCategories = {
  KEY: 'bongo_categories_v2',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_CATEGORIES);
    return INITIAL_CATEGORIES;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    return this.getAll().find(c => c.id === parseInt(id));
  },
  getBySlug: function(slug) {
    return this.getAll().find(c => c.slug === slug);
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, c) => c.id > max ? c.id : max, 0) + 1;
    const slug = data.slug || (data.name || 'category').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const newCat = {
      id: newId,
      name: data.name || 'New Category',
      slug: slug,
      desc: data.desc || '',
      image: data.image || 'assets/images/products/shirt-white.png',
      isDisabled: data.isDisabled || false,
      createdAt: new Date().toISOString()
    };
    list.unshift(newCat);
    this.saveAll(list);
    return newCat;
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(c => c.id === parseInt(id));
    if (idx === -1) return null;
    const updated = { ...list[idx], ...data, updatedAt: new Date().toISOString() };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(c => c.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  toggleStatus: function(id) {
    const item = this.getById(id);
    if (!item) return null;
    return this.update(id, { isDisabled: !item.isDisabled });
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_CATEGORIES);
    return INITIAL_CATEGORIES;
  }
};

// 2. SUBCATEGORIES STORE
const INITIAL_SUBCATEGORIES = [
  { id: 1, name: "Men's Apparel", slug: "mens-apparel", categoryId: 1, categorySlug: "fashion", categoryName: "Fashion", desc: "T-Shirts, linen shirts, polos, and trousers for men.", image: "assets/images/products/tshirt-olive.png", isDisabled: false },
  { id: 2, name: "Women's Apparel", slug: "womens-apparel", categoryId: 1, categorySlug: "fashion", categoryName: "Fashion", desc: "Tunics, dresses, and relaxed trousers for women.", image: "assets/images/products/shirt-white.png", isDisabled: false },
  { id: 3, name: "Living & Dining Textiles", slug: "living-textiles", categoryId: 2, categorySlug: "home-decor", categoryName: "Home & Living", desc: "Nakshi Kantha cushions, runners, and tablecloths.", image: "assets/images/products/nakshi-kantha.jpg", isDisabled: false },
  { id: 4, name: "Decorative Brassware & Ceramics", slug: "brassware-ceramics", categoryId: 2, categorySlug: "home-decor", categoryName: "Home & Living", desc: "Terracotta vessels, wood bowls, and lightware.", image: "assets/images/products/artisan-brass.jpg", isDisabled: false },
  { id: 5, name: "Golden Fiber Bags & Baskets", slug: "jute-bags-baskets", categoryId: 3, categorySlug: "jute", categoryName: "Jute", desc: "Tote bags, braided storage baskets, and floor mats.", image: "assets/images/lifestyle/jute-showcase.jpg", isDisabled: false },
  { id: 6, name: "Heirloom Weaves & Crafts", slug: "heirloom-crafts", categoryId: 4, categorySlug: "handicrafts", categoryName: "Handcrafted", desc: "Handspun Jamdani, quilts, and heritage crafts.", image: "assets/images/products/artisan-brass.jpg", isDisabled: false },
  { id: 7, name: "Leather & Gift Sets", slug: "leather-gift-sets", categoryId: 5, categorySlug: "lifestyle", categoryName: "Gifts", desc: "Journals, desk sets, and curated gift boxes.", image: "assets/images/products/leather-journal.jpg", isDisabled: false },
];

window.BongoSubcategories = {
  KEY: 'bongo_subcategories_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_SUBCATEGORIES);
    return INITIAL_SUBCATEGORIES;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    return this.getAll().find(s => s.id === parseInt(id));
  },
  getByCategory: function(catIdOrSlug) {
    return this.getAll().filter(s => s.categoryId === parseInt(catIdOrSlug) || s.categorySlug === catIdOrSlug);
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, s) => s.id > max ? s.id : max, 0) + 1;
    const slug = data.slug || (data.name || 'subcategory').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    
    // Resolve parent category
    let parentCat = window.BongoCategories ? window.BongoCategories.getById(data.categoryId) : null;
    if (!parentCat && window.BongoCategories) {
      parentCat = window.BongoCategories.getBySlug(data.categorySlug);
    }

    const newSub = {
      id: newId,
      name: data.name || 'New Subcategory',
      slug: slug,
      categoryId: parentCat ? parentCat.id : (parseInt(data.categoryId) || 1),
      categorySlug: parentCat ? parentCat.slug : (data.categorySlug || 'fashion'),
      categoryName: parentCat ? parentCat.name : (data.categoryName || 'Fashion'),
      desc: data.desc || '',
      image: data.image || 'assets/images/products/shirt-white.png',
      isDisabled: data.isDisabled || false,
      createdAt: new Date().toISOString()
    };
    list.unshift(newSub);
    this.saveAll(list);
    return newSub;
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(s => s.id === parseInt(id));
    if (idx === -1) return null;

    let parentCat = null;
    if (data.categoryId && window.BongoCategories) {
      parentCat = window.BongoCategories.getById(data.categoryId);
    }

    const updated = {
      ...list[idx],
      ...data,
      categoryId: parentCat ? parentCat.id : (data.categoryId ? parseInt(data.categoryId) : list[idx].categoryId),
      categorySlug: parentCat ? parentCat.slug : (data.categorySlug || list[idx].categorySlug),
      categoryName: parentCat ? parentCat.name : (data.categoryName || list[idx].categoryName),
      updatedAt: new Date().toISOString()
    };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(s => s.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  toggleStatus: function(id) {
    const item = this.getById(id);
    if (!item) return null;
    return this.update(id, { isDisabled: !item.isDisabled });
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_SUBCATEGORIES);
    return INITIAL_SUBCATEGORIES;
  }
};

// 3. BRANDS STORE
const INITIAL_BRANDS = [
  { id: 1, name: 'Heritage Weaves', slug: 'heritage-weaves', logo: 'assets/images/products/shirt-white.png', desc: 'Organic cotton and pure flax linen apparel.', origin: 'Artisan Crafted', isDisabled: false },
  { id: 2, name: 'Bongo Curated', slug: 'bongo-curated', logo: 'assets/images/products/tshirt-olive.png', desc: 'In-house signature line curated for Australia.', origin: 'Curated for Australia', isDisabled: false },
  { id: 3, name: 'Golden Fiber Co.', slug: 'golden-fiber-co', logo: 'assets/images/lifestyle/jute-showcase.jpg', desc: 'Sustainable golden fiber jute products.', origin: 'Thoughtfully Sourced', isDisabled: false },
  { id: 4, name: 'Nakshi Guild', slug: 'nakshi-guild', logo: 'assets/images/products/nakshi-kantha.jpg', desc: 'Heritage Nakshi Kantha textiles by master artisans.', origin: 'Artisan Crafted', isDisabled: false },
  { id: 5, name: 'Heritage Artisan Craft', slug: 'heritage-artisan-craft', logo: 'assets/images/products/artisan-brass.jpg', desc: 'Master brassmiths and woodcraft makers.', origin: 'Artisan Crafted', isDisabled: false },
];

window.BongoBrands = {
  KEY: 'bongo_brands_v2',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_BRANDS);
    return INITIAL_BRANDS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    return this.getAll().find(b => b.id === parseInt(id));
  },
  getBySlug: function(slug) {
    return this.getAll().find(b => b.slug === slug);
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, b) => b.id > max ? b.id : max, 0) + 1;
    const slug = data.slug || (data.name || 'brand').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const newBrand = {
      id: newId,
      name: data.name || 'New Brand',
      slug: slug,
      logo: data.logo || 'assets/images/products/shirt-white.png',
      desc: data.desc || '',
      origin: data.origin || 'Artisan Crafted',
      isDisabled: data.isDisabled || false,
      createdAt: new Date().toISOString()
    };
    list.unshift(newBrand);
    this.saveAll(list);
    return newBrand;
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(b => b.id === parseInt(id));
    if (idx === -1) return null;
    const updated = { ...list[idx], ...data, updatedAt: new Date().toISOString() };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(b => b.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  toggleStatus: function(id) {
    const item = this.getById(id);
    if (!item) return null;
    return this.update(id, { isDisabled: !item.isDisabled });
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_BRANDS);
    return INITIAL_BRANDS;
  }
};

// 4. PRODUCTS STORE (EXPANDED SCHEMA WITH VARIANTS & SPECS)
window.BongoProducts = {
  STORAGE_KEY: 'bongo_products_v2',

  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length >= INITIAL_PRODUCTS.length) return parsed;
      }
    } catch(e) {
      console.warn('Error reading bongo_products from localStorage:', e);
    }
    this.saveAll(INITIAL_PRODUCTS);
    return INITIAL_PRODUCTS;
  },

  saveAll: function(items) {
    try {
      localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items));
    } catch(e) {
      console.error('Error saving products to localStorage:', e);
    }
    if (typeof PRODUCTS !== 'undefined' && Array.isArray(PRODUCTS)) {
      PRODUCTS.length = 0;
      PRODUCTS.push(...items);
    }
  },

  getById: function(id) {
    return this.getAll().find(p => p.id === parseInt(id));
  },

  create: function(productData) {
    const list = this.getAll();
    const newId = list.reduce((max, p) => p.id > max ? p.id : max, 0) + 1;
    const slug = productData.slug || (productData.name || 'product').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
    const price = parseFloat(productData.price) || 0;
    const origPrice = productData.originalPrice ? parseFloat(productData.originalPrice) : null;
    const discount = (origPrice && origPrice > price) ? Math.round(((origPrice - price) / origPrice) * 100) : null;

    const newProduct = {
      id: newId,
      sku: productData.sku || `SKU-BG-${newId.toString().padStart(4, '0')}`,
      name: productData.name || 'Untitled Product',
      slug: slug,
      category: productData.category || 'fashion',
      categoryId: productData.categoryId || null,
      subcategory: productData.subcategory || '',
      subcategoryId: productData.subcategoryId || null,
      brand: productData.brand || 'Bongo Curated',
      brandId: productData.brandId || null,
      desc: productData.desc || '',
      fullDesc: productData.fullDesc || productData.desc || '',
      image: productData.image || 'assets/images/products/shirt-white.png',
      images: Array.isArray(productData.images) ? productData.images : (productData.image ? [productData.image] : ['assets/images/products/shirt-white.png']),
      price: price,
      originalPrice: origPrice,
      discountPercentage: discount,
      trackStock: productData.trackStock !== undefined ? Boolean(productData.trackStock) : true,
      stockQty: productData.stockQty !== undefined ? parseInt(productData.stockQty) : 25,
      availability: productData.availability || 'instock',
      badge: productData.badge || '',
      isFeatured: productData.isFeatured || false,
      isDisabled: productData.isDisabled || false,
      isPreorder: productData.availability === 'preorder' || productData.isPreorder || false,
      preorderSettings: productData.preorderSettings || { releaseDate: '', note: '' },
      hasVariants: Boolean(productData.hasVariants),
      variantType: productData.variantType || 'none',
      variants: Array.isArray(productData.variants) ? productData.variants : [],
      colors: Array.isArray(productData.colors) ? productData.colors : (productData.colors ? productData.colors.split(',').map(c => c.trim()).filter(Boolean) : []),
      sizes: Array.isArray(productData.sizes) ? productData.sizes : (productData.sizes ? productData.sizes.split(',').map(s => s.trim()).filter(Boolean) : []),
      specs: productData.specs || {
        material: productData.material || '',
        fabric: productData.fabric || '',
        dimensions: productData.dimensions || '',
        fit: productData.fit || '',
        pattern: productData.pattern || '',
        finish: productData.finish || '',
        weight: productData.weight || '',
        care: productData.care || ''
      },
      createdAt: new Date().toISOString()
    };

    list.unshift(newProduct);
    this.saveAll(list);
    return newProduct;
  },

  update: function(id, updatedFields) {
    const list = this.getAll();
    const index = list.findIndex(p => p.id === parseInt(id));
    if (index === -1) return null;

    const current = list[index];
    const price = updatedFields.price !== undefined ? parseFloat(updatedFields.price) : current.price;
    const origPrice = updatedFields.originalPrice !== undefined ? (updatedFields.originalPrice ? parseFloat(updatedFields.originalPrice) : null) : current.originalPrice;
    const discount = (origPrice && origPrice > price) ? Math.round(((origPrice - price) / origPrice) * 100) : null;

    const updated = {
      ...current,
      ...updatedFields,
      price: price,
      originalPrice: origPrice,
      discountPercentage: discount,
      colors: Array.isArray(updatedFields.colors) ? updatedFields.colors : (typeof updatedFields.colors === 'string' ? updatedFields.colors.split(',').map(c => c.trim()).filter(Boolean) : current.colors),
      sizes: Array.isArray(updatedFields.sizes) ? updatedFields.sizes : (typeof updatedFields.sizes === 'string' ? updatedFields.sizes.split(',').map(s => s.trim()).filter(Boolean) : current.sizes),
      specs: { ...current.specs, ...updatedFields.specs },
      updatedAt: new Date().toISOString()
    };

    list[index] = updated;
    this.saveAll(list);
    return updated;
  },

  delete: function(id) {
    const list = this.getAll();
    const filtered = list.filter(p => p.id !== parseInt(id));
    this.saveAll(filtered);
    return true;
  },

  toggleStatus: function(id) {
    const product = this.getById(id);
    if (!product) return null;
    return this.update(id, { isDisabled: !product.isDisabled });
  },

  resetToDefault: function() {
    this.saveAll(INITIAL_PRODUCTS);
    return INITIAL_PRODUCTS;
  }
};

// Global PRODUCTS array for storefront compatibility
window.PRODUCTS = window.BongoProducts.getAll();
var PRODUCTS = window.PRODUCTS;

/* ============================================================
   SALES MODULE DATA STORES (CUSTOMERS, ORDERS, ABANDONED CARTS)
   ============================================================ */

// 5. INITIAL CUSTOMERS DATA
const INITIAL_CUSTOMERS = [
  {
    id: 1,
    name: 'Sarah Jenkins',
    email: 'sarah.j@example.com.au',
    phone: '+61 412 345 678',
    avatar: 'SJ',
    joinedDate: '2025-11-15T10:00:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    billingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    notes: 'VIP customer. Prefers eco-friendly packaging.',
    wishlist: [22, 23]
  },
  {
    id: 2,
    name: 'Liam Hemsworth',
    email: 'liam.h@melbourne.vic.gov.au',
    phone: '+61 423 789 012',
    avatar: 'LH',
    joinedDate: '2026-01-20T14:22:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Liam Hemsworth',
      street: '158 Collins Street',
      address2: '',
      city: 'Melbourne',
      state: 'VIC',
      postcode: '3000',
      country: 'Australia',
      phone: '+61 423 789 012'
    },
    billingAddress: {
      recipientName: 'Liam Hemsworth',
      street: '158 Collins Street',
      address2: '',
      city: 'Melbourne',
      state: 'VIC',
      postcode: '3000',
      country: 'Australia',
      phone: '+61 423 789 012'
    },
    notes: '',
    wishlist: [21]
  },
  {
    id: 3,
    name: 'Emma Watson-Smith',
    email: 'emma.ws@outlook.com.au',
    phone: '+61 434 567 890',
    avatar: 'EW',
    joinedDate: '2025-08-10T09:15:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Emma Watson-Smith',
      street: '89 Queen Street',
      address2: 'Apt 12',
      city: 'Brisbane',
      state: 'QLD',
      postcode: '4000',
      country: 'Australia',
      phone: '+61 434 567 890'
    },
    billingAddress: {
      recipientName: 'Emma Watson-Smith',
      street: '89 Queen Street',
      address2: 'Apt 12',
      city: 'Brisbane',
      state: 'QLD',
      postcode: '4000',
      country: 'Australia',
      phone: '+61 434 567 890'
    },
    notes: 'Frequent buyer of organic cotton apparel.',
    wishlist: [24, 26]
  },
  {
    id: 4,
    name: 'Oliver Taylor',
    email: 'oliver.taylor@gmail.com',
    phone: '+61 445 123 789',
    avatar: 'OT',
    joinedDate: '2026-03-05T11:40:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Oliver Taylor',
      street: '14 St Georges Terrace',
      address2: '',
      city: 'Perth',
      state: 'WA',
      postcode: '6000',
      country: 'Australia',
      phone: '+61 445 123 789'
    },
    billingAddress: {
      recipientName: 'Oliver Taylor',
      street: '14 St Georges Terrace',
      address2: '',
      city: 'Perth',
      state: 'WA',
      postcode: '6000',
      country: 'Australia',
      phone: '+61 445 123 789'
    },
    notes: '',
    wishlist: []
  },
  {
    id: 5,
    name: 'Charlotte Brown',
    email: 'charlotte.b@fastmail.com',
    phone: '+61 456 234 567',
    avatar: 'CB',
    joinedDate: '2026-02-14T16:05:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Charlotte Brown',
      street: '72 King William St',
      address2: '',
      city: 'Adelaide',
      state: 'SA',
      postcode: '5000',
      country: 'Australia',
      phone: '+61 456 234 567'
    },
    billingAddress: {
      recipientName: 'Charlotte Brown',
      street: '72 King William St',
      address2: '',
      city: 'Adelaide',
      state: 'SA',
      postcode: '5000',
      country: 'Australia',
      phone: '+61 456 234 567'
    },
    notes: '',
    wishlist: [25]
  },
  {
    id: 6,
    name: 'Jack MacIntyre',
    email: 'jack.mac@sydneytech.edu.au',
    phone: '+61 467 890 123',
    avatar: 'JM',
    joinedDate: '2026-04-01T08:30:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Jack MacIntyre',
      street: '200 Broadway',
      address2: 'Level 2',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2008',
      country: 'Australia',
      phone: '+61 467 890 123'
    },
    billingAddress: {
      recipientName: 'Jack MacIntyre',
      street: '200 Broadway',
      address2: 'Level 2',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2008',
      country: 'Australia',
      phone: '+61 467 890 123'
    },
    notes: 'Created active cart recently.',
    wishlist: [26, 24]
  },
  {
    id: 7,
    name: 'Sophie Nguyen',
    email: 'sophie.nguyen@designco.com.au',
    phone: '+61 478 901 234',
    avatar: 'SN',
    joinedDate: '2025-09-18T13:45:00.000Z',
    isDisabled: false,
    shippingAddress: {
      recipientName: 'Sophie Nguyen',
      street: '55 Elizabeth Street',
      address2: '',
      city: 'Hobart',
      state: 'TAS',
      postcode: '7000',
      country: 'Australia',
      phone: '+61 478 901 234'
    },
    billingAddress: {
      recipientName: 'Sophie Nguyen',
      street: '55 Elizabeth Street',
      address2: '',
      city: 'Hobart',
      state: 'TAS',
      postcode: '7000',
      country: 'Australia',
      phone: '+61 478 901 234'
    },
    notes: 'Prefers Express Courier shipping.',
    wishlist: [22]
  },
  {
    id: 8,
    name: 'Harrison Forde',
    email: 'harrison.f@gmail.com',
    phone: '+61 489 012 345',
    avatar: 'HF',
    joinedDate: '2026-03-12T17:10:00.000Z',
    isDisabled: true,
    shippingAddress: {
      recipientName: 'Harrison Forde',
      street: '10 Constitution Ave',
      address2: '',
      city: 'Canberra',
      state: 'ACT',
      postcode: '2601',
      country: 'Australia',
      phone: '+61 489 012 345'
    },
    billingAddress: {
      recipientName: 'Harrison Forde',
      street: '10 Constitution Ave',
      address2: '',
      city: 'Canberra',
      state: 'ACT',
      postcode: '2601',
      country: 'Australia',
      phone: '+61 489 012 345'
    },
    notes: 'Account suspended following order cancellation request.',
    wishlist: []
  }
];

// 6. INITIAL ORDERS DATA
const INITIAL_ORDERS = [
  {
    id: 1008,
    orderNumber: '#ORD-2026-1008',
    createdAt: '2026-10-04T10:15:00.000Z',
    customerId: 1,
    customerName: 'Sarah Jenkins',
    customerEmail: 'sarah.j@example.com.au',
    customerPhone: '+61 412 345 678',
    orderStatus: 'Delivered',
    paymentStatus: 'Paid',
    paymentMethod: 'Credit Card (Stripe)',
    transactionId: 'ch_3M92837492831',
    trackingNumber: 'AUS-948271039',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 10.00,
    discount: 10.00,
    discountCode: 'SPRING10',
    subtotal: 94.00,
    tax: 8.40,
    total: 94.00,
    items: [
      {
        productId: 22,
        productName: 'Lightweight Cotton Summer Shirt',
        sku: 'SKU-BG-0022',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'White',
        variantSize: 'L',
        unitPrice: 36.00,
        quantity: 2,
        lineTotal: 72.00
      },
      {
        productId: 25,
        productName: 'Organic Cotton Ribbed Tank',
        sku: 'SKU-BG-0025',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Olive',
        variantSize: 'M',
        unitPrice: 22.00,
        quantity: 1,
        lineTotal: 22.00
      }
    ],
    itemCount: 3,
    shippingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    billingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed by customer online', timestamp: '2026-10-04T10:15:00.000Z', author: 'System' },
      { status: 'Confirmed', note: 'Payment verified via Stripe', timestamp: '2026-10-04T10:16:30.000Z', author: 'System' },
      { status: 'Processing', note: 'Dispatched to Sydney Fulfillment Depot', timestamp: '2026-10-04T11:30:00.000Z', author: 'Tahmeed M.' },
      { status: 'Shipped', note: 'Australia Post tracking generated: AUS-948271039', timestamp: '2026-10-04T14:20:00.000Z', author: 'Tahmeed M.' },
      { status: 'Delivered', note: 'Delivered to recipient address front door', timestamp: '2026-10-06T09:10:00.000Z', author: 'AU Post' }
    ]
  },
  {
    id: 1007,
    orderNumber: '#ORD-2026-1007',
    createdAt: '2026-10-04T08:45:00.000Z',
    customerId: 3,
    customerName: 'Emma Watson-Smith',
    customerEmail: 'emma.ws@outlook.com.au',
    customerPhone: '+61 434 567 890',
    orderStatus: 'Shipped',
    paymentStatus: 'Paid',
    paymentMethod: 'PayPal',
    transactionId: 'PP-981273918273',
    trackingNumber: 'AUS-983710294',
    shippingMethod: 'Express Courier',
    shippingFee: 0.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 87.00,
    tax: 7.91,
    total: 87.00,
    items: [
      {
        productId: 26,
        productName: 'Heritage Cotton Kurta',
        sku: 'SKU-BG-0026',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'White',
        variantSize: 'M',
        unitPrice: 48.00,
        quantity: 1,
        lineTotal: 48.00
      },
      {
        productId: 23,
        productName: 'Washed Cotton Casual Shirt',
        sku: 'SKU-BG-0023',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'Khaki',
        variantSize: 'L',
        unitPrice: 39.00,
        quantity: 1,
        lineTotal: 39.00
      }
    ],
    itemCount: 2,
    shippingAddress: {
      recipientName: 'Emma Watson-Smith',
      street: '89 Queen Street',
      address2: 'Apt 12',
      city: 'Brisbane',
      state: 'QLD',
      postcode: '4000',
      country: 'Australia',
      phone: '+61 434 567 890'
    },
    billingAddress: {
      recipientName: 'Emma Watson-Smith',
      street: '89 Queen Street',
      address2: 'Apt 12',
      city: 'Brisbane',
      state: 'QLD',
      postcode: '4000',
      country: 'Australia',
      phone: '+61 434 567 890'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed via PayPal Express', timestamp: '2026-10-04T08:45:00.000Z', author: 'System' },
      { status: 'Confirmed', note: 'Payment captured', timestamp: '2026-10-04T08:46:00.000Z', author: 'System' },
      { status: 'Processing', note: 'Items packed and labeled', timestamp: '2026-10-04T10:00:00.000Z', author: 'Tahmeed M.' },
      { status: 'Shipped', note: 'Handed to Express Courier: AUS-983710294', timestamp: '2026-10-04T13:15:00.000Z', author: 'Tahmeed M.' }
    ]
  },
  {
    id: 1006,
    orderNumber: '#ORD-2026-1006',
    createdAt: '2026-10-03T16:20:00.000Z',
    customerId: 2,
    customerName: 'Liam Hemsworth',
    customerEmail: 'liam.h@melbourne.vic.gov.au',
    customerPhone: '+61 423 789 012',
    orderStatus: 'Processing',
    paymentStatus: 'Paid',
    paymentMethod: 'Credit Card (Stripe)',
    transactionId: 'ch_3M92837492832',
    trackingNumber: '',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 12.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 84.00,
    tax: 8.73,
    total: 96.00,
    items: [
      {
        productId: 24,
        productName: 'Cotton Lounge Shorts',
        sku: 'SKU-BG-0024',
        image: 'assets/images/products/trousers-khaki.png',
        variantColor: 'Khaki',
        variantSize: 'M',
        unitPrice: 26.00,
        quantity: 2,
        lineTotal: 52.00
      },
      {
        productId: 20,
        productName: 'Textured Cotton Henley',
        sku: 'SKU-BG-0020',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Olive',
        variantSize: 'L',
        unitPrice: 32.00,
        quantity: 1,
        lineTotal: 32.00
      }
    ],
    itemCount: 3,
    shippingAddress: {
      recipientName: 'Liam Hemsworth',
      street: '158 Collins Street',
      address2: '',
      city: 'Melbourne',
      state: 'VIC',
      postcode: '3000',
      country: 'Australia',
      phone: '+61 423 789 012'
    },
    billingAddress: {
      recipientName: 'Liam Hemsworth',
      street: '158 Collins Street',
      address2: '',
      city: 'Melbourne',
      state: 'VIC',
      postcode: '3000',
      country: 'Australia',
      phone: '+61 423 789 012'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed by customer', timestamp: '2026-10-03T16:20:00.000Z', author: 'System' },
      { status: 'Confirmed', note: 'Payment verified', timestamp: '2026-10-03T16:21:00.000Z', author: 'System' },
      { status: 'Processing', note: 'Sent to packing desk', timestamp: '2026-10-04T09:00:00.000Z', author: 'Tahmeed M.' }
    ]
  },
  {
    id: 1005,
    orderNumber: '#ORD-2026-1005',
    createdAt: '2026-10-03T11:05:00.000Z',
    customerId: 5,
    customerName: 'Charlotte Brown',
    customerEmail: 'charlotte.b@fastmail.com',
    customerPhone: '+61 456 234 567',
    orderStatus: 'Confirmed',
    paymentStatus: 'Paid',
    paymentMethod: 'Afterpay',
    transactionId: 'AP-819238192',
    trackingNumber: '',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 10.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 96.00,
    tax: 9.64,
    total: 106.00,
    items: [
      {
        productId: 26,
        productName: 'Heritage Cotton Kurta',
        sku: 'SKU-BG-0026',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'White',
        variantSize: 'L',
        unitPrice: 48.00,
        quantity: 2,
        lineTotal: 96.00
      }
    ],
    itemCount: 2,
    shippingAddress: {
      recipientName: 'Charlotte Brown',
      street: '72 King William St',
      address2: '',
      city: 'Adelaide',
      state: 'SA',
      postcode: '5000',
      country: 'Australia',
      phone: '+61 456 234 567'
    },
    billingAddress: {
      recipientName: 'Charlotte Brown',
      street: '72 King William St',
      address2: '',
      city: 'Adelaide',
      state: 'SA',
      postcode: '5000',
      country: 'Australia',
      phone: '+61 456 234 567'
    },
    timeline: [
      { status: 'Pending', note: 'Order submitted with Afterpay', timestamp: '2026-10-03T11:05:00.000Z', author: 'System' },
      { status: 'Confirmed', note: 'Afterpay transaction authorized', timestamp: '2026-10-03T11:06:00.000Z', author: 'System' }
    ]
  },
  {
    id: 1004,
    orderNumber: '#ORD-2026-1004',
    createdAt: '2026-10-02T19:40:00.000Z',
    customerId: 4,
    customerName: 'Oliver Taylor',
    customerEmail: 'oliver.taylor@gmail.com',
    customerPhone: '+61 445 123 789',
    orderStatus: 'Pending',
    paymentStatus: 'Pending',
    paymentMethod: 'Bank Transfer (EFT)',
    transactionId: 'PENDING_EFT',
    trackingNumber: '',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 10.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 78.00,
    tax: 8.00,
    total: 88.00,
    items: [
      {
        productId: 23,
        productName: 'Washed Cotton Casual Shirt',
        sku: 'SKU-BG-0023',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'Blue',
        variantSize: 'XL',
        unitPrice: 39.00,
        quantity: 2,
        lineTotal: 78.00
      }
    ],
    itemCount: 2,
    shippingAddress: {
      recipientName: 'Oliver Taylor',
      street: '14 St Georges Terrace',
      address2: '',
      city: 'Perth',
      state: 'WA',
      postcode: '6000',
      country: 'Australia',
      phone: '+61 445 123 789'
    },
    billingAddress: {
      recipientName: 'Oliver Taylor',
      street: '14 St Georges Terrace',
      address2: '',
      city: 'Perth',
      state: 'WA',
      postcode: '6000',
      country: 'Australia',
      phone: '+61 445 123 789'
    },
    timeline: [
      { status: 'Pending', note: 'Awaiting EFT transfer confirmation from bank', timestamp: '2026-10-02T19:40:00.000Z', author: 'System' }
    ]
  },
  {
    id: 1003,
    orderNumber: '#ORD-2026-1003',
    createdAt: '2026-10-01T14:10:00.000Z',
    customerId: 7,
    customerName: 'Sophie Nguyen',
    customerEmail: 'sophie.nguyen@designco.com.au',
    customerPhone: '+61 478 901 234',
    orderStatus: 'Delivered',
    paymentStatus: 'Paid',
    paymentMethod: 'Apple Pay',
    transactionId: 'APL-102938102',
    trackingNumber: 'AUS-771029381',
    shippingMethod: 'Express Courier',
    shippingFee: 0.00,
    discount: 10.00,
    discountCode: 'WELCOME10',
    subtotal: 110.00,
    tax: 9.09,
    total: 100.00,
    items: [
      {
        productId: 21,
        productName: 'Classic Cotton Crewneck',
        sku: 'SKU-BG-0021',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Off-White',
        variantSize: 'S',
        unitPrice: 28.00,
        quantity: 3,
        lineTotal: 84.00
      },
      {
        productId: 24,
        productName: 'Cotton Lounge Shorts',
        sku: 'SKU-BG-0024',
        image: 'assets/images/products/trousers-khaki.png',
        variantColor: 'Black',
        variantSize: 'S',
        unitPrice: 26.00,
        quantity: 1,
        lineTotal: 26.00
      }
    ],
    itemCount: 4,
    shippingAddress: {
      recipientName: 'Sophie Nguyen',
      street: '55 Elizabeth Street',
      address2: '',
      city: 'Hobart',
      state: 'TAS',
      postcode: '7000',
      country: 'Australia',
      phone: '+61 478 901 234'
    },
    billingAddress: {
      recipientName: 'Sophie Nguyen',
      street: '55 Elizabeth Street',
      address2: '',
      city: 'Hobart',
      state: 'TAS',
      postcode: '7000',
      country: 'Australia',
      phone: '+61 478 901 234'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed via Apple Pay', timestamp: '2026-10-01T14:10:00.000Z', author: 'System' },
      { status: 'Confirmed', note: 'Payment verified', timestamp: '2026-10-01T14:11:00.000Z', author: 'System' },
      { status: 'Processing', note: 'Packed', timestamp: '2026-10-01T16:00:00.000Z', author: 'Tahmeed M.' },
      { status: 'Shipped', note: 'Express tracking: AUS-771029381', timestamp: '2026-10-01T18:00:00.000Z', author: 'Tahmeed M.' },
      { status: 'Delivered', note: 'Delivered to Tasmania recipient', timestamp: '2026-10-03T11:20:00.000Z', author: 'AU Post' }
    ]
  },
  {
    id: 1002,
    orderNumber: '#ORD-2026-1002',
    createdAt: '2026-09-29T09:30:00.000Z',
    customerId: 8,
    customerName: 'Harrison Forde',
    customerEmail: 'harrison.f@gmail.com',
    customerPhone: '+61 489 012 345',
    orderStatus: 'Cancelled',
    paymentStatus: 'Refunded',
    paymentMethod: 'Credit Card (Stripe)',
    transactionId: 'ch_3M92837492833',
    trackingNumber: '',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 10.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 44.00,
    tax: 4.91,
    total: 54.00,
    items: [
      {
        productId: 25,
        productName: 'Organic Cotton Ribbed Tank',
        sku: 'SKU-BG-0025',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Black',
        variantSize: 'M',
        unitPrice: 22.00,
        quantity: 2,
        lineTotal: 44.00
      }
    ],
    itemCount: 2,
    shippingAddress: {
      recipientName: 'Harrison Forde',
      street: '10 Constitution Ave',
      address2: '',
      city: 'Canberra',
      state: 'ACT',
      postcode: '2601',
      country: 'Australia',
      phone: '+61 489 012 345'
    },
    billingAddress: {
      recipientName: 'Harrison Forde',
      street: '10 Constitution Ave',
      address2: '',
      city: 'Canberra',
      state: 'ACT',
      postcode: '2601',
      country: 'Australia',
      phone: '+61 489 012 345'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed online', timestamp: '2026-09-29T09:30:00.000Z', author: 'System' },
      { status: 'Cancelled', note: 'Cancelled by admin per customer email request. Full refund issued.', timestamp: '2026-09-29T11:00:00.000Z', author: 'Tahmeed M.' }
    ]
  },
  {
    id: 1001,
    orderNumber: '#ORD-2026-1001',
    createdAt: '2026-09-25T15:50:00.000Z',
    customerId: 1,
    customerName: 'Sarah Jenkins',
    customerEmail: 'sarah.j@example.com.au',
    customerPhone: '+61 412 345 678',
    orderStatus: 'Refunded',
    paymentStatus: 'Refunded',
    paymentMethod: 'Credit Card (Stripe)',
    transactionId: 'ch_3M92837492834',
    trackingNumber: 'AUS-102938475',
    shippingMethod: 'Standard Shipping (AU Post)',
    shippingFee: 10.00,
    discount: 0.00,
    discountCode: '',
    subtotal: 32.00,
    tax: 3.82,
    total: 42.00,
    items: [
      {
        productId: 20,
        productName: 'Textured Cotton Henley',
        sku: 'SKU-BG-0020',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Sand',
        variantSize: 'L',
        unitPrice: 32.00,
        quantity: 1,
        lineTotal: 32.00
      }
    ],
    itemCount: 1,
    shippingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    billingAddress: {
      recipientName: 'Sarah Jenkins',
      street: '42 George Street',
      address2: 'Suite 4B',
      city: 'Sydney',
      state: 'NSW',
      postcode: '2000',
      country: 'Australia',
      phone: '+61 412 345 678'
    },
    timeline: [
      { status: 'Pending', note: 'Order placed', timestamp: '2026-09-25T15:50:00.000Z', author: 'System' },
      { status: 'Delivered', note: 'Delivered to Sydney', timestamp: '2026-09-28T10:00:00.000Z', author: 'AU Post' },
      { status: 'Refunded', note: 'Item returned due to wrong size request. Refunded A$42.00 via Stripe.', timestamp: '2026-09-30T14:15:00.000Z', author: 'Tahmeed M.' }
    ]
  }
];

// 7. INITIAL ABANDONED CARTS DATA
const INITIAL_ABANDONED_CARTS = [
  {
    id: 1,
    cartRef: '#CART-9842',
    createdAt: '2026-10-05T15:30:00.000Z',
    lastActivity: '2026-10-05T15:42:00.000Z',
    customerId: 6,
    customerName: 'Jack MacIntyre',
    customerEmail: 'jack.mac@sydneytech.edu.au',
    customerPhone: '+61 467 890 123',
    location: 'Sydney, NSW',
    cartStatus: 'Abandoned',
    abandonedStep: 'Shipping Step',
    recoveryToken: 'rec_tok_9842',
    subtotal: 74.00,
    shippingFee: 10.00,
    estimatedTax: 6.73,
    totalValue: 84.00,
    itemCount: 2,
    items: [
      {
        productId: 26,
        productName: 'Heritage Cotton Kurta',
        sku: 'SKU-BG-0026',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'White',
        variantSize: 'L',
        price: 48.00,
        quantity: 1,
        lineTotal: 48.00
      },
      {
        productId: 24,
        productName: 'Cotton Lounge Shorts',
        sku: 'SKU-BG-0024',
        image: 'assets/images/products/trousers-khaki.png',
        variantColor: 'Khaki',
        variantSize: 'L',
        price: 26.00,
        quantity: 1,
        lineTotal: 26.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-10-05T15:30:00.000Z' },
      { event: 'Reached Shipping Address Step', timestamp: '2026-10-05T15:42:00.000Z' }
    ]
  },
  {
    id: 2,
    cartRef: '#CART-9841',
    createdAt: '2026-10-04T16:10:00.000Z',
    lastActivity: '2026-10-04T16:25:00.000Z',
    customerId: 3,
    customerName: 'Emma Watson-Smith',
    customerEmail: 'emma.ws@outlook.com.au',
    customerPhone: '+61 434 567 890',
    location: 'Brisbane, QLD',
    cartStatus: 'Email Sent',
    abandonedStep: 'Payment Info Page',
    recoveryToken: 'rec_tok_9841',
    subtotal: 106.00,
    shippingFee: 10.00,
    estimatedTax: 9.64,
    totalValue: 116.00,
    itemCount: 3,
    items: [
      {
        productId: 23,
        productName: 'Washed Cotton Casual Shirt',
        sku: 'SKU-BG-0023',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'Navy',
        variantSize: 'M',
        price: 39.00,
        quantity: 2,
        lineTotal: 78.00
      },
      {
        productId: 21,
        productName: 'Classic Cotton Crewneck',
        sku: 'SKU-BG-0021',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Black',
        variantSize: 'M',
        price: 28.00,
        quantity: 1,
        lineTotal: 28.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-10-04T16:10:00.000Z' },
      { event: 'Reached Payment Page', timestamp: '2026-10-04T16:25:00.000Z' },
      { event: 'Recovery Email Sent by Admin', timestamp: '2026-10-05T09:00:00.000Z' }
    ]
  },
  {
    id: 3,
    cartRef: '#CART-9840',
    createdAt: '2026-10-04T11:00:00.000Z',
    lastActivity: '2026-10-04T11:15:00.000Z',
    customerId: null,
    customerName: 'Amanda Cross (Guest)',
    customerEmail: 'amanda.cross@gmail.com',
    customerPhone: '+61 491 234 567',
    location: 'Melbourne, VIC',
    cartStatus: 'Abandoned',
    abandonedStep: 'Cart Review',
    recoveryToken: 'rec_tok_9840',
    subtotal: 140.00,
    shippingFee: 10.00,
    estimatedTax: 12.72,
    totalValue: 150.00,
    itemCount: 5,
    items: [
      {
        productId: 20,
        productName: 'Textured Cotton Henley',
        sku: 'SKU-BG-0020',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'Olive',
        variantSize: 'XL',
        price: 32.00,
        quantity: 3,
        lineTotal: 96.00
      },
      {
        productId: 25,
        productName: 'Organic Cotton Ribbed Tank',
        sku: 'SKU-BG-0025',
        image: 'assets/images/products/tshirt-olive.png',
        variantColor: 'White',
        variantSize: 'L',
        price: 22.00,
        quantity: 2,
        lineTotal: 44.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-10-04T11:00:00.000Z' },
      { event: 'Abandoned on Cart Review', timestamp: '2026-10-04T11:15:00.000Z' }
    ]
  },
  {
    id: 4,
    cartRef: '#CART-9839',
    createdAt: '2026-10-02T10:00:00.000Z',
    lastActivity: '2026-10-02T10:30:00.000Z',
    customerId: 2,
    customerName: 'Liam Hemsworth',
    customerEmail: 'liam.h@melbourne.vic.gov.au',
    customerPhone: '+61 423 789 012',
    location: 'Melbourne, VIC',
    cartStatus: 'Recovered',
    abandonedStep: 'Payment Info Page',
    recoveryToken: 'rec_tok_9839',
    subtotal: 36.00,
    shippingFee: 10.00,
    estimatedTax: 3.27,
    totalValue: 46.00,
    itemCount: 1,
    items: [
      {
        productId: 22,
        productName: 'Lightweight Cotton Summer Shirt',
        sku: 'SKU-BG-0022',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'Blue',
        variantSize: 'L',
        price: 36.00,
        quantity: 1,
        lineTotal: 36.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-10-02T10:00:00.000Z' },
      { event: 'Email Link Clicked & Converted', timestamp: '2026-10-03T16:20:00.000Z' }
    ]
  },
  {
    id: 5,
    cartRef: '#CART-9838',
    createdAt: '2026-09-30T09:00:00.000Z',
    lastActivity: '2026-09-30T09:20:00.000Z',
    customerId: null,
    customerName: 'David Wilson (Guest)',
    customerEmail: 'david.wilson@hotmail.com',
    customerPhone: '+61 498 765 432',
    location: 'Perth, WA',
    cartStatus: 'Expired',
    abandonedStep: 'Shipping Step',
    recoveryToken: 'rec_tok_9838',
    subtotal: 48.00,
    shippingFee: 10.00,
    estimatedTax: 4.36,
    totalValue: 58.00,
    itemCount: 1,
    items: [
      {
        productId: 26,
        productName: 'Heritage Cotton Kurta',
        sku: 'SKU-BG-0026',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'White',
        variantSize: 'XL',
        price: 48.00,
        quantity: 1,
        lineTotal: 48.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-09-30T09:00:00.000Z' },
      { event: 'Expired after 5 days inactivity', timestamp: '2026-10-05T09:00:00.000Z' }
    ]
  },
  {
    id: 6,
    cartRef: '#CART-9837',
    createdAt: '2026-09-28T14:00:00.000Z',
    lastActivity: '2026-09-28T14:15:00.000Z',
    customerId: 7,
    customerName: 'Sophie Nguyen',
    customerEmail: 'sophie.nguyen@designco.com.au',
    customerPhone: '+61 478 901 234',
    location: 'Hobart, TAS',
    cartStatus: 'Email Sent',
    abandonedStep: 'Cart Review',
    recoveryToken: 'rec_tok_9837',
    subtotal: 65.00,
    shippingFee: 10.00,
    estimatedTax: 5.91,
    totalValue: 75.00,
    itemCount: 2,
    items: [
      {
        productId: 23,
        productName: 'Washed Cotton Casual Shirt',
        sku: 'SKU-BG-0023',
        image: 'assets/images/products/shirt-white.png',
        variantColor: 'Olive',
        variantSize: 'S',
        price: 39.00,
        quantity: 1,
        lineTotal: 39.00
      },
      {
        productId: 24,
        productName: 'Cotton Lounge Shorts',
        sku: 'SKU-BG-0024',
        image: 'assets/images/products/trousers-khaki.png',
        variantColor: 'Olive',
        variantSize: 'S',
        price: 26.00,
        quantity: 1,
        lineTotal: 26.00
      }
    ],
    recoveryLog: [
      { event: 'Cart Created', timestamp: '2026-09-28T14:00:00.000Z' },
      { event: 'Reminder Email Dispatched', timestamp: '2026-09-29T10:00:00.000Z' }
    ]
  }
];

// STORE MANAGERS FOR SALES MODULES
window.BongoCustomers = {
  STORAGE_KEY: 'bongo_customers_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_CUSTOMERS);
    return INITIAL_CUSTOMERS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    return this.getAll().find(c => c.id === parseInt(id));
  },
  getByEmail: function(email) {
    return this.getAll().find(c => c.email.toLowerCase() === (email || '').toLowerCase());
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, c) => c.id > max ? c.id : max, 0) + 1;
    const initials = (data.name || 'C').split(' ').map(n => n[0]).join('').toUpperCase();
    const newCust = {
      id: newId,
      name: data.name || 'New Customer',
      email: data.email || '',
      phone: data.phone || '',
      avatar: initials || 'CU',
      joinedDate: new Date().toISOString(),
      isDisabled: Boolean(data.isDisabled),
      shippingAddress: data.shippingAddress || { recipientName: data.name, street: '', address2: '', city: '', state: '', postcode: '', country: 'Australia', phone: data.phone },
      billingAddress: data.billingAddress || { recipientName: data.name, street: '', address2: '', city: '', state: '', postcode: '', country: 'Australia', phone: data.phone },
      notes: data.notes || '',
      wishlist: data.wishlist || []
    };
    list.unshift(newCust);
    this.saveAll(list);
    return newCust;
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(c => c.id === parseInt(id));
    if (idx === -1) return null;
    const updated = { ...list[idx], ...data, updatedAt: new Date().toISOString() };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(c => c.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  toggleStatus: function(id) {
    const item = this.getById(id);
    if (!item) return null;
    return this.update(id, { isDisabled: !item.isDisabled });
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_CUSTOMERS);
    return INITIAL_CUSTOMERS;
  }
};

window.BongoOrders = {
  STORAGE_KEY: 'bongo_orders_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_ORDERS);
    return INITIAL_ORDERS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    if (!id) return null;
    const cleanIdStr = id.toString().replace('#', '').replace('ORD-2026-', '').replace('ORD-', '');
    const cleanIdNum = parseInt(cleanIdStr);
    return this.getAll().find(o => o.id === cleanIdNum || o.id === parseInt(id) || o.orderNumber.replace('#', '') === id.toString().replace('#', ''));
  },
  getByCustomerId: function(customerId) {
    return this.getAll().filter(o => o.customerId === parseInt(customerId));
  },
  updateStatus: function(id, newStatus, note, author = 'Admin') {
    const order = this.getById(id);
    if (!order) return null;
    const timeline = order.timeline || [];
    timeline.push({
      status: newStatus,
      note: note || `Order status updated to ${newStatus}`,
      timestamp: new Date().toISOString(),
      author: author
    });
    return this.update(order.id, { orderStatus: newStatus, timeline: timeline });
  },
  updatePaymentStatus: function(id, newPaymentStatus, note, author = 'Admin') {
    const order = this.getById(id);
    if (!order) return null;
    const timeline = order.timeline || [];
    timeline.push({
      status: order.orderStatus,
      paymentStatus: newPaymentStatus,
      note: note || `Payment status updated to ${newPaymentStatus}`,
      timestamp: new Date().toISOString(),
      author: author
    });
    return this.update(order.id, { paymentStatus: newPaymentStatus, timeline: timeline });
  },
  cancelOrder: function(id, reason, author = 'Admin') {
    const order = this.getById(id);
    if (!order) return null;
    const timeline = order.timeline || [];
    timeline.push({
      status: 'Cancelled',
      note: `Order cancelled by ${author}. Reason: ${reason || 'Not specified'}`,
      timestamp: new Date().toISOString(),
      author: author
    });
    const updateData = { orderStatus: 'Cancelled', timeline: timeline };
    if (order.paymentStatus === 'Paid') {
      updateData.paymentStatus = 'Refunded';
    }
    return this.update(order.id, updateData);
  },
  refundOrder: function(id, refundAmount, reason, author = 'Admin') {
    const order = this.getById(id);
    if (!order) return null;
    const timeline = order.timeline || [];
    timeline.push({
      status: 'Refunded',
      paymentStatus: 'Refunded',
      note: `Refund of A$${parseFloat(refundAmount || order.total).toFixed(2)} processed. Reason: ${reason || 'Customer request'}`,
      timestamp: new Date().toISOString(),
      author: author
    });
    return this.update(order.id, { orderStatus: 'Refunded', paymentStatus: 'Refunded', timeline: timeline });
  },
  addTimelineNote: function(id, note, author = 'Admin') {
    const order = this.getById(id);
    if (!order) return null;
    const timeline = order.timeline || [];
    timeline.push({
      status: order.orderStatus,
      note: note,
      timestamp: new Date().toISOString(),
      author: author
    });
    return this.update(order.id, { timeline: timeline });
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(o => o.id === parseInt(id));
    if (idx === -1) return null;
    const updated = { ...list[idx], ...data, updatedAt: new Date().toISOString() };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(o => o.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_ORDERS);
    return INITIAL_ORDERS;
  }
};

window.BongoAbandonedCarts = {
  STORAGE_KEY: 'bongo_abandoned_carts_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_ABANDONED_CARTS);
    return INITIAL_ABANDONED_CARTS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    if (!id) return null;
    const cleanIdStr = id.toString().replace('#', '').replace('CART-', '');
    const cleanIdNum = parseInt(cleanIdStr);
    return this.getAll().find(c => c.id === cleanIdNum || c.id === parseInt(id) || c.cartRef.replace('#', '') === id.toString().replace('#', ''));
  },
  getByCustomerId: function(customerId) {
    return this.getAll().find(c => c.customerId === parseInt(customerId));
  },
  sendReminder: function(id, author = 'Admin') {
    const cart = this.getById(id);
    if (!cart) return null;
    const log = cart.recoveryLog || [];
    log.push({ event: `Recovery Email Sent by ${author}`, timestamp: new Date().toISOString() });
    return this.update(cart.id, { cartStatus: 'Email Sent', recoveryLog: log });
  },
  markAsRecovered: function(id) {
    const cart = this.getById(id);
    if (!cart) return null;
    const log = cart.recoveryLog || [];
    log.push({ event: 'Cart Marked as Recovered by Admin', timestamp: new Date().toISOString() });
    return this.update(cart.id, { cartStatus: 'Recovered', recoveryLog: log });
  },
  update: function(id, data) {
    const list = this.getAll();
    const idx = list.findIndex(c => c.id === parseInt(id));
    if (idx === -1) return null;
    const updated = { ...list[idx], ...data, updatedAt: new Date().toISOString() };
    list[idx] = updated;
    this.saveAll(list);
    return updated;
  },
  delete: function(id) {
    const list = this.getAll().filter(c => c.id !== parseInt(id));
    this.saveAll(list);
    return true;
  },
  resetToDefault: function() {
    this.saveAll(INITIAL_ABANDONED_CARTS);
    return INITIAL_ABANDONED_CARTS;
  }
};

// 6. BULK ORDERS REQUEST STORE
const INITIAL_BULK_REQUESTS = [
  {
    id: 101,
    requestNumber: 'BULK-1001',
    customerName: 'Sarah Jenkins',
    contactInfo: 'sarah.j@eventcraft.com.au',
    phone: '0412 345 678',
    postcode: '2000',
    suburb: 'Sydney NSW',
    targetDate: '2026-11-15',
    notes: 'Need custom gift packaging and ribbon wrapping for annual corporate gala.',
    status: 'Pending',
    adminNotes: 'Initial inquiry received. Preparing volume quote for linen apparel.',
    createdAt: '2026-10-05T14:30:00Z',
    items: [
      {
        id: 1,
        productId: 1,
        name: 'Pure Flax Linen Shirt',
        category: 'fashion',
        image: 'assets/images/products/tshirt-olive.png',
        isCustom: false,
        variants: [
          { color: 'Crisp White', size: 'L', qty: 30 },
          { color: 'Olive Green', size: 'M', qty: 20 },
          { color: 'Olive Green', size: 'L', qty: 25 }
        ]
      },
      {
        id: 2,
        productId: 9,
        name: 'Handwoven Golden Jute Round Tote',
        category: 'jute',
        image: 'assets/images/lifestyle/jute-showcase.jpg',
        isCustom: false,
        variants: [
          { color: 'Natural Golden', size: 'Standard', qty: 75 }
        ]
      }
    ],
    totalUnits: 150
  },
  {
    id: 102,
    requestNumber: 'BULK-1002',
    customerName: 'Marcus Vance',
    contactInfo: 'm.vance@vancedesign.com.au',
    phone: '0499 111 222',
    postcode: '3000',
    suburb: 'Melbourne VIC',
    targetDate: '2026-12-01',
    notes: 'Corporate holiday gift boxes for tier 1 clients.',
    status: 'Quoted',
    adminNotes: 'Discount quote sent via email (15% bulk rate applied).',
    createdAt: '2026-10-04T09:15:00Z',
    items: [
      {
        id: 1,
        productId: 7,
        name: 'Nakshi Kantha Embroidered Cushion Cover',
        category: 'home-decor',
        image: 'assets/images/products/nakshi-kantha.jpg',
        isCustom: false,
        variants: [
          { color: 'Terracotta Red', size: '45x45 cm', qty: 40 }
        ]
      },
      {
        id: 2,
        productId: null,
        name: 'Custom Engraved Brass Coasters (Unlisted Item)',
        category: 'handicrafts',
        image: 'assets/images/products/artisan-brass.jpg',
        isCustom: true,
        customSpecs: 'Solid brass coasters with laser-etched corporate logo, set of 4 in velvet pouch',
        variants: [
          { color: 'Antique Brass', size: '10cm Round', qty: 40 }
        ]
      }
    ],
    totalUnits: 80
  }
];

window.BongoBulkRequests = {
  STORAGE_KEY: 'bongo_bulk_requests_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_BULK_REQUESTS);
    return INITIAL_BULK_REQUESTS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    if (!id) return null;
    const cleanId = id.toString().replace('BULK-', '');
    return this.getAll().find(r => r.id === parseInt(cleanId) || r.requestNumber === id || r.requestNumber === `BULK-${cleanId}`);
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, r) => r.id > max ? r.id : max, 100) + 1;
    const reqNum = `BULK-${1000 + newId}`;
    
    let totalUnits = 0;
    if (Array.isArray(data.items)) {
      data.items.forEach(item => {
        if (Array.isArray(item.variants)) {
          item.variants.forEach(v => {
            totalUnits += (parseInt(v.qty) || 0);
          });
        }
      });
    }

    const newReq = {
      id: newId,
      requestNumber: reqNum,
      customerName: data.customerName || 'Valued Customer',
      contactInfo: data.contactInfo || '',
      phone: data.phone || '',
      postcode: data.postcode || '',
      suburb: data.suburb || '',
      targetDate: data.targetDate || '',
      notes: data.notes || '',
      status: 'Pending',
      adminNotes: '',
      createdAt: new Date().toISOString(),
      items: data.items || [],
      totalUnits: totalUnits
    };

    list.unshift(newReq);
    this.saveAll(list);
    return newReq;
  },
  updateStatus: function(id, status, adminNotes = null) {
    const list = this.getAll();
    const cleanId = id.toString().replace('BULK-', '');
    const req = list.find(r => r.id === parseInt(cleanId) || r.requestNumber === id);
    if (!req) return null;
    if (status) req.status = status;
    if (adminNotes !== null) req.adminNotes = adminNotes;
    req.updatedAt = new Date().toISOString();
    this.saveAll(list);
    return req;
  }
};

const INITIAL_SOURCING_REQUESTS = [
  {
    id: 201,
    requestNumber: 'SRC-1001',
    customerName: 'Anika Rahman',
    contactInfo: 'anika.r@gmail.com.au',
    phone: '0411 222 333',
    postcode: '2000',
    suburb: 'Sydney NSW',
    itemName: 'Handwoven Royal Blue Jamdani Saree with Fine Zari Motif',
    productLink: 'https://instagram.com/p/sample-jamdani',
    colorSize: 'Royal Blue & Gold Zari · Standard 5.5m',
    qty: 2,
    details: 'Need authentic hand-loomed Jamdani for a family wedding in late November. Premium cotton silk mix preferred.',
    images: ['assets/images/products/shirt-white.png'],
    status: 'Pending',
    adminNotes: 'Inquiry received. Checking with artisan weaver guild in Narayanganj.',
    estimatedPrice: 'A$185.00',
    createdAt: '2026-10-06T10:15:00Z'
  },
  {
    id: 202,
    requestNumber: 'SRC-1002',
    customerName: 'David Miller',
    contactInfo: 'david.miller@designstudio.com.au',
    phone: '0488 444 555',
    postcode: '3000',
    suburb: 'Melbourne VIC',
    itemName: 'Vintage Brass Hammered Floor Planter & Brass Pitcher Set',
    productLink: '',
    colorSize: 'Antique Brass Finish · Large 40cm height',
    qty: 5,
    details: 'Custom decor for boutique hotel lobby in Melbourne. Must be solid brass with lacquered protective coat.',
    images: ['assets/images/products/artisan-brass.jpg'],
    status: 'Quoted',
    adminNotes: 'Sourced from master metalwork workshop. Quote sent: A$140/piece including freight.',
    estimatedPrice: 'A$700.00',
    createdAt: '2026-10-04T16:30:00Z'
  }
];

window.BongoSourcingRequests = {
  STORAGE_KEY: 'bongo_sourcing_requests_v1',
  getAll: function() {
    try {
      const stored = localStorage.getItem(this.STORAGE_KEY);
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch(e) {}
    this.saveAll(INITIAL_SOURCING_REQUESTS);
    return INITIAL_SOURCING_REQUESTS;
  },
  saveAll: function(items) {
    try { localStorage.setItem(this.STORAGE_KEY, JSON.stringify(items)); } catch(e) {}
  },
  getById: function(id) {
    if (!id) return null;
    const cleanId = id.toString().replace('SRC-', '');
    return this.getAll().find(r => r.id === parseInt(cleanId) || r.requestNumber === id || r.requestNumber === `SRC-${cleanId}`);
  },
  create: function(data) {
    const list = this.getAll();
    const newId = list.reduce((max, r) => r.id > max ? r.id : max, 200) + 1;
    const reqNum = `SRC-${1000 + newId}`;

    const newReq = {
      id: newId,
      requestNumber: reqNum,
      customerName: data.customerName || 'Valued Customer',
      contactInfo: data.contactInfo || '',
      phone: data.phone || '',
      postcode: data.postcode || '',
      suburb: data.suburb || '',
      itemName: data.itemName || 'Custom Product Sourcing',
      productLink: data.productLink || '',
      colorSize: data.colorSize || '',
      qty: parseInt(data.qty) || 1,
      details: data.details || '',
      images: data.images || [],
      status: 'Pending',
      adminNotes: '',
      estimatedPrice: 'TBD',
      createdAt: new Date().toISOString()
    };

    list.unshift(newReq);
    this.saveAll(list);
    return newReq;
  },
  updateStatus: function(id, status, adminNotes = null, estimatedPrice = null) {
    const list = this.getAll();
    const cleanId = id.toString().replace('SRC-', '');
    const req = list.find(r => r.id === parseInt(cleanId) || r.requestNumber === id);
    if (!req) return null;
    if (status) req.status = status;
    if (adminNotes !== null) req.adminNotes = adminNotes;
    if (estimatedPrice !== null) req.estimatedPrice = estimatedPrice;
    req.updatedAt = new Date().toISOString();
    this.saveAll(list);
    return req;
  }
};


const FOOTER_LINKS = {
  shop: [
    { label: 'All Products', href: '#products-section' },
    { label: 'New Arrivals', href: '#products-section' },
    { label: 'Fashion', href: '#products-section' },
    { label: 'Home & Living', href: '#home-living-section' },
    { label: 'Jute', href: '#jute-section' },
    { label: 'Handcrafted', href: '#products-section' },
    { label: 'Gifts', href: '#gifting-section' },
    { label: 'Pre-order', href: '#preorder-section' },
  ],
  help: [
    { label: 'My Account', href: 'account.html' },
    { label: 'Shipping & Delivery', href: '#' },
    { label: 'Returns & Exchanges', href: '#' },
    { label: 'Size Guide', href: '#' },
    { label: 'FAQ', href: '#' },
    { label: 'Request Product Sourcing', href: 'request.html' },
    { label: 'Bulk & Corporate Orders', href: 'bulk-order.html' },
    { label: 'Contact Us', href: 'request.html' },
  ],
  about: [
    { label: 'Our Story', href: 'index.html#bangladesh-made-section' },
    { label: 'Artisan Heritage', href: 'index.html#bangladesh-made-section' },
    { label: 'Custom Sourcing', href: 'request.html' },
    { label: 'Bulk Orders', href: 'bulk-order.html' },
  ],
};