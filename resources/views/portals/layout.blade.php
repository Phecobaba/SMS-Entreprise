<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title') · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head><body>
@include('core._shell-start')
<main class="oak-dashboard oak-portal-page">
    @include('portals._ward')
    @if(($context['kind'] ?? null) === 'parent' && ! $context['student_id'])
        @include('portals._empty')
    @else
        @yield('content')
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
