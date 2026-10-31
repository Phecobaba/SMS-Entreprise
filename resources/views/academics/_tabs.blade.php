<nav class="ad-tabs" aria-label="Academic configuration">
    @can('manage_academics')<a href="{{ route('academics.index') }}" wire:navigate @if(request()->routeIs('academics.index')) aria-current="page" @endif>Sections & levels</a>@endcan
    <a href="{{ route('academics.levels.index') }}" wire:navigate @if(request()->routeIs('academics.levels.*')) aria-current="page" @endif>Levels</a>
    @can('manage_academics')<a href="{{ route('academics.departments.index') }}" wire:navigate @if(request()->routeIs('academics.departments.*')) aria-current="page" @endif>Departments</a>@endcan
    <a href="{{ route('academics.classes.index') }}" wire:navigate @if(request()->routeIs('academics.classes.*')) aria-current="page" @endif>Classes</a>
    <a href="{{ route('academics.bulk-subjects.index') }}" wire:navigate @if(request()->routeIs('academics.bulk-subjects.*')) aria-current="page" @endif>Bulk subjects</a>
    <a href="{{ route('academics.teacher-assignments.index') }}" wire:navigate @if(request()->routeIs('academics.teacher-assignments.*')) aria-current="page" @endif>Teacher assignments</a>
    @can('manage_academics')<a href="{{ route('academics.sessions.index') }}" wire:navigate @if(request()->routeIs('academics.sessions.*') || request()->routeIs('academics.terms.*')) aria-current="page" @endif>Sessions & terms</a>@endcan
    <a href="{{ route('academics.setup') }}" wire:navigate @if(request()->routeIs('academics.setup')) aria-current="page" @endif>Subjects</a>
</nav>
