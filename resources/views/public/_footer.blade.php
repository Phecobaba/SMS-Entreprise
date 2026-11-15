<footer class="ftr"><div class="wrap">
    <img class="logo" src="{{ config('landing.logo') }}" alt="{{ $publicProfile['name'] }} emblem" width="48" height="48">
    @if($publicProfile['motto'])<p class="motto">{{ $publicProfile['motto'] }}</p>@endif
    @if($publicProfile['name'] || $publicProfile['address'] || $publicProfile['contact_phone'] || $publicProfile['contact_email'])
        <p>{{ collect([$publicProfile['name'], $publicProfile['address'], $publicProfile['contact_phone'], $publicProfile['contact_email']])->filter()->join(' · ') }}</p>
    @endif
    <p><a class="tl" href="{{ route('privacy') }}">Privacy</a></p>
</div></footer>
