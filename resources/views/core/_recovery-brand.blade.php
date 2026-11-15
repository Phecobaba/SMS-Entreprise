<div class="lg-brand">
    @if($profile['logo_url'] ?? null)<img src="{{ $profile['logo_url'] }}" alt="{{ $profile['name'] ?? 'Oakfield' }}">@else<span class="oak-school-name">{{ $profile['name'] ?? 'School' }}</span>@endif
</div>
<div class="lg-top">
    <span class="oak-logo-crop">@if($profile['logo_url'] ?? null)<img class="oak-logo-mark" src="{{ $profile['logo_url'] }}" alt="">@endif</span>
    <span class="oak-school-name">{{ $profile['name'] ?? 'School' }}</span>
</div>
