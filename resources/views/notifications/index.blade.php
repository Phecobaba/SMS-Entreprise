<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Notifications · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@include('core._shell-start')
<main class="oak-dashboard oak-portal-page">
    @if(($context['kind'] ?? null) === 'parent')@include('portals._ward')@endif
    @if(($context['kind'] ?? null) === 'parent' && ! $context['student_id'])
        @include('portals._empty')
    @else
    <div class="oak-section-head"><div><h1>Notifications</h1><p class="oak-subpage-note">{{ $unreadCount }} unread</p></div>@if($unreadCount)<form method="post" action="{{ route('notifications.read-all', ($context['kind'] ?? null) === 'parent' && $context['student_id'] ? ['student' => $context['student_id']] : []) }}">@csrf<button class="oak-btn oak-btn-quiet">Mark all read</button></form>@endif</div>
    @if(session('status'))<p class="bg-green-50 p-3">{{ session('status') }}</p>@endif
    <section class="nt-list">
        @forelse($notifications as $notification)
            <article class="nt-row" @unless($notification->read_at) data-unread @endunless>
                <div><h3>@if($notification->url)<a href="{{ $notification->url }}" wire:navigate>{{ $notification->title }}</a>@else{{ $notification->title }}@endif @unless($notification->read_at)<span class="oak-status-pill oak-status-new">New</span>@endunless</h3><p>{{ $notification->body }}</p><time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time></div>
                @unless($notification->read_at)<form method="post" action="{{ route('notifications.read', ['notification' => $notification->id, 'student' => $context['student_id'] ?? null]) }}">@csrf<button class="oak-btn oak-btn-quiet">Mark read</button></form>@endunless
            </article>
        @empty<p class="oak-empty">You have no notifications yet.</p>@endforelse
    </section>
    @if($notifications->hasPages()){{ $notifications->links() }}@endif
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
