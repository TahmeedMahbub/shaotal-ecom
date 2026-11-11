{{-- Standalone error page shell: no database, session or catalog access, so it renders even when MySQL is down.
     Uses the storefront's own stylesheets and tokens. Never shows exception details (Laravel only adds those in debug). --}}
<!DOCTYPE html>
<html lang="en-AU">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>@yield('title') | {{ config('storefront.brand') }}</title>
  <link rel="icon" type="image/x-icon" href="{{ \App\Support\Assets::url('assets/logos/favicon/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/variables.css') }}">
  <link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/base.css') }}">
  <style>
    body { min-height: 100vh; display: flex; flex-direction: column; background: var(--color-surface); }
    .error-top { padding: var(--space-md) 0; border-bottom: 1px solid var(--color-grey-200); background: var(--color-surface-card); }
    .error-top img { height: 40px; width: auto; display: block; }
    .error-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: var(--space-xl) 16px; }
    .error-card { max-width: 560px; width: 100%; text-align: center; background: var(--color-surface-card); border: 1px solid var(--color-grey-200);
      border-radius: var(--radius-lg); padding: var(--space-xl) var(--space-lg); }
    .error-code { font-family: var(--font-heading); font-size: 3.5rem; font-weight: 800; line-height: 1; color: var(--color-brand); margin: 0 0 var(--space-sm); }
    .error-title { font-family: var(--font-heading); font-size: var(--fs-h2); color: var(--color-dark); margin: 0 0 var(--space-sm); }
    .error-text { font-size: var(--fs-body); color: var(--color-grey-700); margin: 0 0 var(--space-lg); line-height: 1.6; }
    .error-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }
    .error-help { margin-top: var(--space-md); font-size: var(--fs-small); color: var(--color-grey-500); }
    .error-help a { color: var(--color-brand); }
    @media (max-width: 480px) { .error-actions .btn { width: 100%; } .error-code { font-size: 2.75rem; } }
  </style>
</head>
<body>
  <header class="error-top">
    <div class="container">
      <a href="/" aria-label="{{ config('storefront.brand') }} home"><img src="{{ \App\Support\Assets::url('assets/logos/logo.png') }}" alt="{{ config('storefront.brand') }}" width="160" height="40"></a>
    </div>
  </header>
  <main class="error-main">
    <div class="error-card" role="alert">
      <p class="error-code">@yield('code')</p>
      <h1 class="error-title">@yield('title')</h1>
      <p class="error-text">@yield('message')</p>
      <div class="error-actions">
        @section('actions')
          <a href="/" class="btn btn-primary">Back to Home</a>
          <a href="/products" class="btn btn-outline">Browse Products</a>
        @show
      </div>
      <p class="error-help">Need help? <a href="/request">Contact us</a>.</p>
    </div>
  </main>
</body>
</html>
