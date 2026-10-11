@auth
@php
    $oakNavItems = app(\App\Modules\Core\Services\AppNavigation::class)->items(auth()->user());
    $oakIsStudent = collect($oakNavItems)->contains('label', 'Exams');
    $oakIsParent = ! $oakIsStudent && app(\App\Modules\Assignments\Services\ParentAccountDirectory::class)->existsForUser(auth()->id());
    $oakPortalContext = $oakIsParent ? app(\App\Modules\Portals\Services\PortalContext::class)->for(request()) : null;
    $oakStudentQuery = $oakPortalContext['student_id'] ?? null;
    $oakUnread = app(\App\Modules\Notifications\Services\InAppNotifications::class)->unreadCount(auth()->user());
    $oakHeaderContext = $pageContext ?? (($overview['selected']['class'] ?? null) ?: null);
@endphp
    </div>
</div>
@if($oakIsStudent || count($oakNavItems) >= 5)
<div class="oak-scrim" id="oak-more-sheet" role="dialog" aria-modal="true" aria-labelledby="oak-more-title">
    <div class="oak-sheet">
        <h2 id="oak-more-title">More</h2>
        <p class="oak-sheet-sub">{{ auth()->user()->name }}@if($oakHeaderContext) · {{ $oakHeaderContext }}@endif</p>
        @foreach(array_slice($oakNavItems, 4) as $item)
            <a class="oak-list-row oak-more-row" href="{{ $item['url'] }}" wire:navigate><span><span class="oak-more-icon">@include('core._nav-icon', ['label' => $item['label']])</span>{{ $item['label'] }}</span><x-icon name="chevron-right" /></a>
        @endforeach
        @if($oakIsStudent || $oakIsParent)
            <a class="oak-list-row oak-more-row" href="{{ route('notifications.index', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate><span><span class="oak-more-icon"><x-icon name="bell" /></span>Notifications</span><span class="oak-more-tail">@if($oakUnread)<span class="oak-status-pill oak-status-new">{{ $oakUnread }} new</span>@endif<x-icon name="chevron-right" /></span></a>
            <a class="oak-list-row oak-more-row" href="{{ route('profile.mine', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate><span><span class="oak-more-icon"><x-icon name="user" /></span>My profile</span><x-icon name="chevron-right" /></a>
        @endif
        <form method="post" action="{{ route('logout') }}">@csrf<button class="oak-list-row oak-more-row oak-more-signout" type="submit"><span><span class="oak-more-icon"><x-icon name="logout" /></span>Sign out</span></button></form>
        <div class="oak-sheet-actions"><button class="oak-btn oak-btn-quiet" type="button" data-close-sheet>Close</button></div>
    </div>
</div>
@endif
@endauth
