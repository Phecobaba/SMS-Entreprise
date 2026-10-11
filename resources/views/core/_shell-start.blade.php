@auth
@php
    $oakNavItems = app(\App\Modules\Core\Services\AppNavigation::class)->items(auth()->user());
    $oakProfile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $oakIsStudent = collect($oakNavItems)->contains('label', 'Exams');
    $oakIsParent = ! $oakIsStudent && app(\App\Modules\Assignments\Services\ParentAccountDirectory::class)->existsForUser(auth()->id());
    $oakPortalContext = $oakIsParent ? app(\App\Modules\Portals\Services\PortalContext::class)->for(request()) : null;
    $oakStudentQuery = $oakPortalContext['student_id'] ?? null;
    $oakDenseNav = ! $oakIsStudent && count($oakNavItems) > 8;
    $oakUnread = app(\App\Modules\Notifications\Services\InAppNotifications::class)->unreadCount(auth()->user());
    $oakMobileItems = array_slice($oakNavItems, 0, $oakIsStudent || count($oakNavItems) >= 5 ? 4 : 5);
    $oakCurrent = request()->routeIs('cbt.mine') ? 'Exams' : (request()->routeIs('dashboard') ? 'Dashboard' : null);
    $oakHeaderTitle = $pageTitle ?? 'Hi, '.auth()->user()->name;
    $oakHeaderContext = $pageContext ?? (($overview['selected']['class'] ?? null) ?: null);
@endphp
<div class="oak-app-shell {{ $oakDenseNav ? 'oak-app-shell-dense' : '' }}">
    @persist('oak-header')
    <header class="oak-header">
        <div class="oak-header-top">
            <a class="oak-brand" href="{{ route('dashboard', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate>
                @if($oakProfile['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $oakProfile['logo_url'] }}" alt=""></span>@else<span class="oak-mark" aria-hidden="true">O</span>@endif
                <span class="oak-school-name">Oakfield</span>
            </a>
            <a class="oak-bell" href="{{ route('notifications.index', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate aria-label="Notifications{{ $oakUnread ? ', '.$oakUnread.' unread' : '' }}"><x-icon name="bell" />@if($oakUnread)<b aria-hidden="true">{{ $oakUnread > 99 ? '99+' : $oakUnread }}</b>@endif</a>
        </div>
        <div class="oak-header-intro"><strong>{{ $oakHeaderTitle }}</strong>@if($oakHeaderContext)<span>{{ $oakHeaderContext }}</span>@endif</div>
    </header>
    @endpersist
    @persist('oak-sidebar')
    <aside class="oak-sidebar" aria-label="Main navigation">
        <a class="oak-brand oak-sidebar-brand" href="{{ route('dashboard', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate>
            @if($oakProfile['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $oakProfile['logo_url'] }}" alt=""></span>@else<span class="oak-mark" aria-hidden="true">O</span>@endif
            <span class="oak-school-name">Oakfield</span>
        </a>
        <nav class="oak-sidebar-links">
            @foreach($oakNavItems as $item)
                @php
                    $group = match($item['label']) {
                        'Academics', 'Admissions', 'Results', 'CBT' => 'Teaching',
                        'Attendance', 'Alerts', 'Assignments', 'E-Notes' => 'Daily ops',
                        'Payments', 'Staff HR', 'School Profile' => 'Administration',
                        default => null,
                    };
                @endphp
                @if($oakDenseNav && $group && $group !== ($oakLastGroup ?? null))<span class="oak-nav-group">{{ $group }}</span>@endif
                @php($oakLastGroup = $group)
                <a href="{{ $item['url'] }}" wire:navigate @if(! str_contains($item['url'], '#')) wire:current="oak-active" @endif @class(['oak-active' => $oakCurrent === $item['label']]) @if($oakCurrent === $item['label']) aria-current="page" @endif><span class="oak-nav-icon">@include('core._nav-icon', ['label' => $item['label']])</span><span>{{ $item['label'] }}</span></a>
            @endforeach
        </nav>
        <div class="oak-sidebar-foot">
            @if($oakIsStudent || $oakIsParent)<a href="{{ route('profile.mine', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate><x-icon name="user" /><span>My profile</span></a>@endif
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" /><span>Sign out</span></button></form>
        </div>
    </aside>
    @endpersist
    @persist('oak-mobile-tabs')
    <nav class="oak-mobile-tabs" aria-label="Mobile navigation">
        @foreach($oakMobileItems as $item)
            <a href="{{ $item['url'] }}" wire:navigate @if(! str_contains($item['url'], '#')) wire:current="oak-active" @endif @if($oakCurrent === $item['label']) aria-current="page" @endif><span class="oak-tab-icon">@include('core._nav-icon', ['label' => $item['label']])</span><span>{{ match($item['label']) {'Dashboard' => 'Home', 'Assignments' => 'Work', default => $item['label']} }}</span></a>
        @endforeach
        @if($oakIsStudent || count($oakNavItems) >= 5)
            <button type="button" data-open-sheet="oak-more-sheet"><span class="oak-tab-icon"><x-icon name="dots" /></span><span>More</span></button>
        @endif
    </nav>
    @endpersist
    <div class="oak-content">
@endauth
