<!DOCTYPE html>
<html lang="en-AU">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title', config('storefront.brand'))</title>
  <meta name="description" content="@yield('description')">
  <meta name="author" content="{{ config('storefront.brand') }}">
  <meta property="og:title" content="@yield('title', config('storefront.brand'))">
  <meta property="og:description" content="@yield('description')">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="en_AU">
  @php($cdn = \App\Support\Img::origin($boot['hero'][0]['image'] ?? ($boot['products'][0]['image'] ?? null)))
  @if ($cdn)<link rel="preconnect" href="{{ $cdn }}">@endif
  @stack('preload')
  <link rel="icon" type="image/png" href="{{ \App\Support\Assets::url('assets/logos/logo.png') }}">
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
  <script>window.__PAGE__ = {{ Illuminate\Support\Js::from($page ?? new stdClass) }};</script>
  <script src="{{ \App\Support\Assets::url('assets/js/data.js') }}"></script>
  <script src="{{ \App\Support\Assets::url('assets/js/app.js') }}"></script>
  @stack('scripts')
</body>
</html>
