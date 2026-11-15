@php
    $publicProfile = $publicProfile ?? app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $publicSettings = $publicSettings ?? \App\Modules\Core\Models\SchoolSetting::first();
    $publicOpen = (bool) ($publicSettings?->admissions_open ?? true);
    $baseUrl = rtrim((string) config('app.url'), '/');
    $canonical = $baseUrl.request()->getPathInfo();
    $pageTitle = $title ?? ($publicProfile['name'].' | Early Years, Primary and Secondary school in Enugu');
    $pageDescription = $description ?? ($publicProfile['name'].' in Enugu: a caring, well-equipped school from Early Years to Secondary.');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="theme-color" content="#0A3836">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ $canonical }}">
    @if(!empty($homeMeta))
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:image" content="{{ $baseUrl.config('landing.og_image') }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    <link rel="icon" href="{{ config('landing.favicon') }}" type="image/png">
    <script>document.documentElement.classList.add('js')</script>
    @vite('resources/css/landing.css')
    @stack('head')
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
@include('public._header')
@yield('content')
@include('public._footer')
@stack('scripts')
</body>
</html>
