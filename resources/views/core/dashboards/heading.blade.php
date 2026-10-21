@php
    $subtitle = match ($overview['kind']) {
        'admin' => ($overview['selected_section_id'] ? $overview['section']?->name : 'All sections').' · School overview',
        'bursar' => 'Fees and payments'.($overview['term'] ? ' · '.$overview['term'] : ''),
        'teacher' => $overview['context'] ? 'Today · '.$overview['context'] : 'Your teaching workspace',
        'parent', 'student' => $overview['selected']['name'].' · '.$overview['selected']['class']
            .($overview['kind'] === 'student' && ($overview['term_context'] ?? null) ? ' · '.$overview['term_context'] : ''),
        default => 'Welcome to your Oakfield workspace',
    };
    $roleLabel = match ($overview['kind']) {
        'admin' => 'Admin',
        'head' => $overview['section']->head_title,
        'head_locked' => 'Head',
        'bursar' => 'Bursar',
        'teacher' => 'Teacher',
        'parent', 'parent_empty' => 'Parent',
        'student' => 'Student',
        default => auth()->user()->roles->sortBy('name')->first()?->name ?? 'Member',
    };
@endphp
<div class="oak-dashboard-heading">
    <div>
        @if($overview['kind'] === 'head')
            <h1>{{ $overview['section']->name }}</h1>
            <p>{{ $overview['section']->head_title }} · {{ $greeting }}, {{ auth()->user()->name }}</p>
        @elseif($overview['kind'] === 'head_locked')
            <h1>Dashboard</h1><p>{{ $greeting }}, {{ auth()->user()->name }}</p>
        @else
            <h1>{{ $greeting }}, {{ $overview['kind'] === 'admin' ? 'Admin' : auth()->user()->name }}</h1>
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    <div class="oak-heading-tools">
        <a class="oak-bell" href="{{ route('notifications.index', $overview['kind'] === 'parent' ? ['student' => $overview['selected']['id']] : []) }}" wire:navigate aria-label="Notifications{{ $notifications->count() ? ', '.$notifications->count().' unread' : '' }}"><x-icon name="bell" />@if($notifications->count())<b aria-hidden="true">{{ $notifications->count() }}</b>@endif</a>
        <span class="oak-role-pill">{{ $roleLabel }}</span>
    </div>
</div>
