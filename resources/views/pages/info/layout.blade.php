@extends('layouts.store')
@section('title', $pageTitle)
@section('description', $pageDescription)
@push('head')
<link rel="stylesheet" href="{{ \App\Support\Assets::url('assets/css/info-pages.css') }}">
@endpush
@section('content')
  <section class="account-page-header">
    <div class="container">
      <nav class="breadcrumb" aria-label="Breadcrumb" style="margin-bottom: var(--space-xs);">
        <a href="/">Home</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">{{ $heading }}</span>
      </nav>
    </div>
  </section>
  <main class="container">
    <article class="info-page">
      <h1>{{ $heading }}</h1>
      @if (! $approved)
        {{-- Shown until the owner (and for terms/privacy a legal adviser) approves this page: config_data approved_info_pages --}}
        <div class="info-draft-notice" role="note">
          <strong>Being finalised.</strong>
          <span>This page is a draft and has not yet been reviewed{{ in_array($slug, ['terms', 'privacy'], true) ? ' by a legal adviser' : '' }}. It describes how the store currently works; it is not a final policy.</span>
        </div>
      @endif
      @yield('info')
      <nav class="info-links" aria-label="Customer information">
        @foreach (\App\Http\Controllers\PageController::INFO_PAGES as $s => $meta)
          @if ($s !== $slug)<a href="/{{ $s }}">{{ $meta[0] }}</a>@endif
        @endforeach
      </nav>
    </article>
  </main>
@endsection
