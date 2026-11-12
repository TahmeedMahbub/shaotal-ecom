<footer class="site-footer">
    <div class="container">
      {{-- Server-rendered copy of renderFooter() in app.js (FOOTER_LINKS in data.js) so category links are crawlable
           without JavaScript; the script re-renders it with the social icons. Keep the links in sync. --}}
      @php
        $footerCols = [
          'Shop' => array_merge([['All Products', '/products'], ['New Arrivals', '/products?sort=newest']],
            array_map(fn ($c) => [$c['name'], \App\Support\Seo::listingUrl($c['slug'])], $boot['categories'] ?? []),
            [['Pre-order', '/products?category=preorder']]),
          'Customer Care' => [['My Account', '/account'], ['Request Product Sourcing', '/request'], ['Bulk & Corporate Orders', '/bulk-order'], ['Contact Us', '/request']],
          'About' => [['Our Story', '/#bangladesh-made-section'], ['Artisan Heritage', '/#bangladesh-made-section'], ['Custom Sourcing', '/request'], ['Bulk Orders', '/bulk-order']],
        ];
      @endphp
      <div class="footer-grid" id="footer-grid">
        <div class="footer-brand">
          <a href="/" class="footer-logo-link" aria-label="{{ config('storefront.brand') }}">
            <img src="/assets/logos/logo.png" alt="{{ config('storefront.brand') }}" class="footer-logo" loading="lazy">
          </a>
          <p>An Australian ecommerce brand offering thoughtfully curated lifestyle pieces — from fashion and handcrafted decor to natural jute items and gifts.</p>
          <div class="footer-social"></div>
        </div>
        @foreach ($footerCols as $heading => $links)
          <div class="footer-col">
            <h4>{{ $heading }}</h4>
            <ul>@foreach ($links as [$label, $href])<li><a href="{{ $href }}">{{ $label }}</a></li>@endforeach</ul>
          </div>
        @endforeach
      </div>
      <div class="footer-bottom">
        <div class="footer-bottom-left">
          &copy; {{ date('Y') }} {{ config('storefront.brand') }}. Curated artisan goods for Australia.
        </div>
        <div class="footer-payment">
          <span class="footer-payment-icon">VISA</span>
          <span class="footer-payment-icon">Mastercard</span>
          <span class="footer-payment-icon">AMEX</span>
          <span class="footer-payment-icon">Apple Pay</span>
          <span class="footer-payment-icon">Google Pay</span>
        </div>
      </div>
    </div>
  </footer>
<nav class="bottom-nav" id="bottom-nav">
    <div class="bottom-nav-inner" id="bottom-nav-inner">
      
    </div>
  </nav>
