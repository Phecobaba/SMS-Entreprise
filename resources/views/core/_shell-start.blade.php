@auth
@php
    $oakNavigation = app(\App\Modules\Core\Services\AppNavigation::class);
    $oakNavItems = $oakNavigation->items(auth()->user());
    $oakProfile = app(\App\Modules\Core\Services\CurrentSchoolProfile::class)->landingPage();
    $oakStaffKind = $oakNavigation->staffKind(auth()->user());
    $oakPortalContext = $oakStaffKind ? null : app(\App\Modules\Portals\Services\PortalContext::class)->for(request());
    $oakIsStudent = ($oakPortalContext['kind'] ?? null) === 'student';
    $oakIsParent = ($oakPortalContext['kind'] ?? null) === 'parent';
    $oakStudentQuery = $oakPortalContext['student_id'] ?? null;
    $oakDenseNav = $oakStaffKind === 'admin';
    $oakShowGroups = in_array($oakStaffKind, ['admin', 'head'], true);
    $oakUnread = app(\App\Modules\Notifications\Services\InAppNotifications::class)->unreadCount(auth()->user());
    $oakResultAttention = app(\App\Modules\Results\Services\ResultAttention::class)->for(auth()->user());
    $oakBellCount = $oakUnread + ($oakResultAttention['count'] ?? 0);
    $oakIsStaff = ! $oakIsStudent && ! $oakIsParent;
    $oakMobileLabels = $oakNavigation->mobileLabels(auth()->user());
    $oakMobileItems = $oakMobileLabels !== []
        ? collect($oakMobileLabels)->map(fn ($label) => collect($oakNavItems)->firstWhere('label', $label))->filter()->values()->all()
        : array_slice($oakNavItems, 0, $oakIsStudent || count($oakNavItems) >= 5 ? 4 : 5);
    $oakCurrent = request()->routeIs('cbt.mine') ? 'Exams' : (request()->routeIs('dashboard') ? 'Dashboard' : null);
    $oakHeaderTitle = $pageTitle ?? 'Hi, '.auth()->user()->name;
    $oakHeaderContext = $pageContext ?? (($overview['selected']['class'] ?? null) ?: null);
    request()->attributes->set('oakShell', compact('oakNavItems', 'oakPortalContext', 'oakIsStudent', 'oakIsParent', 'oakStudentQuery', 'oakUnread', 'oakBellCount', 'oakIsStaff', 'oakMobileItems', 'oakHeaderContext'));
@endphp
<div class="oak-app-shell {{ $oakDenseNav ? 'oak-app-shell-dense' : '' }}" @if($oakDenseNav) data-dense @endif>
    @persist('oak-header')
    <header class="oak-header">
        <div class="oak-header-top">
            <a class="oak-brand" href="{{ route('dashboard', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate>
                @if($oakProfile['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $oakProfile['logo_url'] }}" alt=""></span>@else<span class="oak-mark" aria-hidden="true">O</span>@endif
                <span class="oak-school-name oak-header-school-name">{{ $oakProfile['name'] ?? 'School' }}</span>
            </a>
            <a class="oak-bell" href="{{ route('notifications.index', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate aria-label="Items waiting{{ $oakBellCount ? ', '.$oakBellCount : '' }}"><x-icon name="bell" />@if($oakBellCount)<b aria-hidden="true">{{ $oakBellCount > 99 ? '99+' : $oakBellCount }}</b>@endif</a>
        </div>
        <div class="oak-header-intro"><strong>{{ $oakHeaderTitle }}</strong>@if($oakHeaderContext)<span>{{ $oakHeaderContext }}</span>@endif</div>
    </header>
    @endpersist
    <aside class="oak-sidebar" aria-label="Main navigation">
        <a class="oak-brand oak-sidebar-brand" href="{{ route('dashboard', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate>
            @if($oakProfile['logo_url'] ?? null)<span class="oak-logo-crop"><img class="oak-logo-mark" src="{{ $oakProfile['logo_url'] }}" alt=""></span>@else<span class="oak-mark" aria-hidden="true">O</span>@endif
            <span class="oak-school-name">{{ $oakProfile['name'] ?? 'School' }}</span>
        </a>
        <nav class="oak-sidebar-links">
            @foreach($oakNavItems as $item)
                @php($group = $item['group'] ?? null)
                @if($oakShowGroups && $group && $group !== ($oakLastGroup ?? null))<span class="oak-nav-group">{{ $group }}</span>@endif
                @php($oakLastGroup = $group)
                @if(!empty($item['children']))
                    <details class="oak-nav-parent" @if(collect($item['children'])->contains(fn($child) => request()->url() === strtok($child['url'], '?'))) open @endif>
                        <summary><span class="oak-nav-icon">@include('core._nav-icon', ['label' => $item['label']])</span><span>{{ $item['label'] }}</span><x-icon name="chevron-right" /></summary>
                        <a class="oak-nav-child" href="{{ $item['url'] }}" wire:navigate wire:current="oak-active">Overview</a>
                        @foreach($item['children'] as $child)<a class="oak-nav-child" href="{{ $child['url'] }}" wire:navigate wire:current="oak-active">{{ $child['label'] }}</a>@endforeach
                    </details>
                @else
                    <a href="{{ $item['url'] }}" wire:navigate @if(! str_contains($item['url'], '#')) wire:current="oak-active" @endif @class(['oak-active' => $oakCurrent === $item['label']]) @if($oakCurrent === $item['label']) aria-current="page" @endif><span class="oak-nav-icon">@include('core._nav-icon', ['label' => $item['label']])</span><span>{{ $item['label'] }}</span></a>
                @endif
            @endforeach
        </nav>
        <div class="oak-sidebar-foot">
            @if($oakIsStaff)<a href="{{ route('account.show') }}" wire:navigate><x-icon name="user" /><span>Account</span></a>@endif
            @if($oakIsStudent || $oakIsParent)<a href="{{ route('profile.mine', $oakIsParent && $oakStudentQuery ? ['student' => $oakStudentQuery] : []) }}" wire:navigate><x-icon name="user" /><span>My profile</span></a>@endif
            <form method="post" action="{{ route('logout') }}">@csrf<button type="submit"><x-icon name="logout" /><span>Sign out</span></button></form>
        </div>
    </aside>
    @persist('oak-mobile-tabs')
    <nav class="oak-mobile-tabs" aria-label="Mobile navigation">
        @foreach($oakMobileItems as $item)
            <a href="{{ $item['url'] }}" wire:navigate @if(! str_contains($item['url'], '#')) wire:current="oak-active" @endif @if($oakCurrent === $item['label']) aria-current="page" @endif><span class="oak-tab-icon">@include('core._nav-icon', ['label' => $item['label']])</span><span>{{ match($item['label']) {'Dashboard' => 'Home', 'Assignments' => 'Work', 'Classes' => 'Academics', default => $item['label']} }}</span></a>
        @endforeach
        @if($oakIsStaff || $oakIsStudent || count($oakNavItems) >= 5)
            <button type="button" data-open-sheet="oak-more-sheet"><span class="oak-tab-icon"><x-icon name="dots" /></span><span>More</span></button>
        @endif
    </nav>
    @endpersist
    <div class="oak-content">
        <div class="oak-result-attention-mobile"><x-result-attention :attention="$oakResultAttention" /></div>
@endauth
