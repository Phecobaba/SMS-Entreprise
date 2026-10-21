@if($notifications->isNotEmpty())
@php($oakTickerUrl = route('notifications.index', ($overview['kind'] ?? null) === 'parent' ? ['student' => $overview['selected']['id']] : []))
<section class="oak-ticker" role="region" aria-label="Latest notifications" data-ticker>
    <div class="oak-ticker-label"><x-icon name="bell" /><span>Latest</span></div>
    <div class="oak-ticker-view">
        <div class="oak-ticker-track">
            @foreach($notifications as $notification)<a href="{{ $notification->url ?: $oakTickerUrl }}" wire:navigate><i aria-hidden="true"></i><span><b>{{ $notification->title }}</b></span><span>{{ $notification->body }}</span></a>@endforeach
            <span aria-hidden="true" class="oak-ticker-copy">@foreach($notifications as $notification)<a tabindex="-1" href="{{ $notification->url ?: $oakTickerUrl }}"><i aria-hidden="true"></i><span><b>{{ $notification->title }}</b></span><span>{{ $notification->body }}</span></a>@endforeach</span>
        </div>
        <a class="oak-ticker-static" href="{{ $notifications->first()->url ?: $oakTickerUrl }}" wire:navigate data-ticker-static><b>{{ $notifications->first()->title }}</b> · {{ $notifications->first()->body }}</a>
    </div>
    <button class="oak-ticker-pause" type="button" aria-pressed="false" aria-label="Pause moving notifications" data-ticker-pause><x-icon name="player-pause" /></button>
</section>
@endif
