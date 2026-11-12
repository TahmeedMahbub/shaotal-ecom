<!DOCTYPE html>
<html lang="en-AU">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  {{-- Sections are set with the two-argument @section form, which escapes once; they are printed raw here. --}}
  @php($docTitle = trim($__env->yieldContent('title')) ?: config('storefront.brand'))
  @php($docTitle = str_contains($docTitle, config('storefront.brand')) ? $docTitle : $docTitle.' | '.config('storefront.brand'))
  @php($metaDesc = trim($__env->yieldContent('description')) ?: e(config('storefront.tagline')))
  {{-- Page SEO comes from the controller (robots, canonical, ogImage, jsonLd view data) or a page @section. --}}
  @php($metaRobots = isset($robots) && $robots ? e($robots) : (trim($__env->yieldContent('robots')) ?: 'index, follow'))
  @php($canonicalUrl = str_starts_with($metaRobots, 'noindex') ? '' : (isset($canonical) && $canonical ? e($canonical) : trim($__env->yieldContent('canonical'))))
  @php($ogImageUrl = isset($ogImage) && $ogImage ? e($ogImage) : e(\App\Support\Seo::abs('/assets/logos/logo.png')))
  <title>{!! $docTitle !!}</title>
  <meta name="description" content="{!! $metaDesc !!}">
  <meta name="robots" content="{!! $metaRobots !!}">
  @if ($canonicalUrl !== '')<link rel="canonical" href="{!! $canonicalUrl !!}">@endif
  <meta name="author" content="{{ config('storefront.brand') }}">
  <meta property="og:site_name" content="{{ config('storefront.brand') }}">
  <meta property="og:title" content="{!! $docTitle !!}">
  <meta property="og:description" content="{!! $metaDesc !!}">
  <meta property="og:type" content="{{ isset($product) ? 'product' : 'website' }}">
  @if ($canonicalUrl !== '')<meta property="og:url" content="{!! $canonicalUrl !!}">@endif
  <meta property="og:image" content="{!! $ogImageUrl !!}">
  <meta property="og:locale" content="en_AU">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{!! $docTitle !!}">
  <meta name="twitter:description" content="{!! $metaDesc !!}">
  <meta name="twitter:image" content="{!! $ogImageUrl !!}">
  @foreach ($jsonLd ?? [] as $ld)
  <script type="application/ld+json">{!! \App\Support\Seo::jsonLd($ld) !!}</script>
  @endforeach
  @php($cdn = \App\Support\Img::origin($boot['hero'][0]['image'] ?? ($boot['products'][0]['image'] ?? null)))
  @if ($cdn)<link rel="preconnect" href="{{ $cdn }}">@endif
  @stack('preload')
  <link rel="icon" type="image/x-icon" href="{{ \App\Support\Assets::url('assets/logos/favicon/favicon.ico') }}">
  <link rel="shortcut icon" href="{{ \App\Support\Assets::url('assets/logos/favicon/favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ \App\Support\Assets::url('assets/logos/favicon/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ \App\Support\Assets::url('assets/logos/favicon/favicon-16x16.png') }}">
  <link rel="apple-touch-icon" sizes="180x180" href="{{ \App\Support\Assets::url('assets/logos/favicon/apple-touch-icon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/variables.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/base.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/components.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/sections.css') }}">
  @stack('head')
</head>
<body>
  @include('partials.header')

  @yield('content')

  @unless (View::hasSection('minimal'))
    @include('partials.footer')
  @endunless

  @include('partials.overlays')

  <script type="application/json" id="boot-data">{!! json_encode($boot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) !!}</script>
  <script type="application/json" id="page-data">{!! json_encode($page ?? new stdClass, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  <script src="{{ \App\Support\Assets::url('assets/js/data.js') }}"></script>
  <script src="{{ \App\Support\Assets::url('assets/js/api-client.js') }}"></script>
  <script src="{{ \App\Support\Assets::url('assets/js/app.js') }}"></script>
  @stack('scripts')
</body>
</html>
