@php($oakPublicSchool = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage())
<header class="oak-public-header">
    <a href="{{ route('home') }}" wire:navigate class="oak-brand">@if($oakPublicSchool['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $oakPublicSchool['logo_url'] }}" alt=""></span>@else<span class="oak-mark" aria-hidden="true">O</span>@endif<span class="oak-school-name">{{ $oakPublicSchool['name'] ?? 'Oakfield International Schools' }}</span></a>
</header>
