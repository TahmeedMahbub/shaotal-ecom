{{-- Server-rendered twin of createProductCardHTML() in public/assets/js/app.js (keep the markup in sync). The page script
     re-renders the grid with the same markup on load; the name link makes the product crawlable without JavaScript. --}}
@php
  $badges = ['new' => 'NEW', 'launch' => 'LAUNCH OFFER', 'lowstock' => 'LOW STOCK', 'preorder' => 'PRE-ORDER'];
  $cur = config('storefront.currency_symbol');
  $href = \App\Support\Seo::productUrl($product['slug']);
  $src = \App\Support\Img::url($product['image'], 480) ?: \App\Support\Img::PLACEHOLDER;
  $srcset = \App\Support\Img::srcset($product['image'], [320, 480, 640, 800]);
  $eager = $eager ?? false;
@endphp
<div class="product-card fade-up visible" data-product-id="{{ $product['id'] }}">
  <div class="product-card-image">
    <img src="{{ $src }}" @if ($srcset) srcset="{{ $srcset }}" sizes="(max-width: 640px) 50vw, (max-width: 1100px) 40vw, 260px" @endif width="400" height="450" alt="{{ $product['name'] }}" @if ($eager) loading="eager" fetchpriority="high" @else loading="lazy" @endif decoding="async">
    <div class="product-card-badge">@if ($product['soldOut'])<span class="badge badge-limited">Sold Out</span>@else{!! $badges[$product['badge']] ?? '' !!}@endif</div>
    <button class="product-card-wishlist" data-wishlist-id="{{ $product['id'] }}" aria-label="Add to wishlist"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"/></svg></button>
  </div>
  <div class="product-card-info">
    <div class="product-card-category">{{ $product['categoryName'] ?? $product['category'] }}</div>
    <div class="product-card-name"><a href="{{ $href }}">{{ $product['name'] }}</a></div>
    {{-- desc is allowlist-sanitised HTML (App\Support\SafeHtml) --}}
    <div class="product-card-desc">{!! $product['desc'] !!}</div>
    <div class="product-card-footer">
      <div class="product-card-price">
        @if ($product['originalPrice'] && $product['originalPrice'] > $product['price'])<span class="original">{{ $cur }}{{ number_format($product['originalPrice'], 2) }}</span>@else<span class="original">&nbsp;</span>@endif
        <span class="current">{{ $cur }}{{ number_format($product['price'], 2) }}</span>
      </div>
      <a class="product-card-btn" href="{{ $href }}" aria-label="View details for {{ $product['name'] }}">
        <span class="btn-text-desktop">View Details</span>
        <span class="btn-text-mobile">View</span>
      </a>
    </div>
  </div>
</div>
