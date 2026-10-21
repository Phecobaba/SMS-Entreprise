<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Classes · Oakfield</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>
@php $pageTitle = 'Classes'; @endphp
@include('core._shell-start')
<main class="oak-dashboard">
    <div class="oak-dashboard-heading"><div><h1>Academic structure</h1><p>{{ $classes->total() }} {{ Str::plural('class', $classes->total()) }} in this scope</p></div><span class="oak-role-pill">{{ $canViewAll ? 'Admin' : ($sections->first()?->head_title ?? 'Head') }}</span></div>
    @if($canViewAll)@include('academics._tabs')@endif
    @if(session('status'))<div class="oak-notice oak-notice-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$hasSection)
        <div class="oak-locked"><x-icon name="lock" /><h3>You haven't been assigned to a section yet</h3><p>A head manages only their own section's classes. Ask the Admin to assign you in Academic structure.</p></div>
    @else
        @if($canViewAll && $sections->count() > 1)
            <div class="ad-scope"><nav class="ad-switch" aria-label="Section scope"><a class="ad-seg" href="{{ route('academics.classes.index', ['section' => 'all']) }}" aria-current="{{ $selectedSection === null ? 'true' : 'false' }}" wire:navigate>All sections</a>@foreach($sections as $section)<a class="ad-seg" href="{{ route('academics.classes.index', ['section' => $section->id]) }}" aria-current="{{ $selectedSection === $section->id ? 'true' : 'false' }}" wire:navigate>{{ $section->name }}</a>@endforeach</nav><span class="oak-muted">Every figure and list below follows the section you pick.</span></div>
        @elseif(!$canViewAll)<div class="ad-section-badge"><x-icon name="school" />{{ $sections->first()?->name }} only</div>@endif

        @php $unarranged = $sections->filter(fn($section) => !($arrangedBySection[$section->id] ?? false)); @endphp
        @if($unarranged->isNotEmpty())
            <div class="oak-notice oak-notice-warning"><x-icon name="alert-triangle" /><div><b>Your classes have not been arranged yet.</b> The order shown is a best guess. Promotion stays blocked until you arrange them from first to last.</div></div>
            @if($canEdit)<div class="ad-actions ad-notice-action"><a class="oak-btn oak-btn-primary" href="{{ route('academics.classes.arrange') }}" wire:navigate>Arrange your classes</a></div>@endif
        @endif

        <form method="get" action="{{ route('academics.classes.index') }}" class="ad-form-grid ad-filter-row">@if($selectedSection)<input type="hidden" name="section" value="{{ $selectedSection }}">@endif<div class="oak-field"><label for="level-filter">Level</label><select id="level-filter" name="level"><option value="">All levels</option>@foreach($levels as $level)<option value="{{ $level->id }}" @selected(request('level') == $level->id)>{{ $level->name }}</option>@endforeach</select></div>@if($departments->isNotEmpty())<div class="oak-field"><label for="department-filter">Department</label><select id="department-filter" name="department"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected(request('department') == $department->id)>{{ $department->name }}</option>@endforeach</select></div>@endif<div class="ad-actions"><button class="oak-btn oak-btn-secondary">Apply filters</button></div></form>

        @if($canEdit)<div class="ad-bar"><button type="button" class="oak-btn oak-btn-primary" data-open-sheet="add-class-sheet">{{ $classes->total() ? 'Add a class' : 'Add the first class' }}</button></div>@endif
        <p class="ad-meta ad-order-help">Classes are listed in order. Use Move up and Move down to change the order. Promotion follows this order after arrangement is saved.</p>

        <section class="ad-card oak-section">
        @forelse($classes->groupBy(fn($class) => ($class->level?->section?->name ?? 'Unassigned').'|'.($class->level?->name ?? 'Level')) as $group => $rows)
            @php [$sectionName,$levelName] = explode('|',$group,2); @endphp
            <h2 class="ad-group-title">{{ $sectionName }} <small>{{ $levelName }}</small></h2>
            @foreach($rows as $class)
                @php
                    $levelClasses = $comesAfterByLevel->get($class->academic_level_id, collect());
                    $sameStep = $class->position === null ? collect([$class]) : $levelClasses->where('position', $class->position)->values();
                    $leader = $sameStep->sortBy('id')->first();
                    $isLeader = $leader?->id === $class->id;
                    $steps = $levelClasses->whereNotNull('position')->groupBy('position')->sortKeys()->keys()->values();
                    $stepIndex = $class->position === null ? false : $steps->search($class->position);
                @endphp
                <div class="ad-class-row">
                    <div><h4>{{ $class->name }} @if($departments->isNotEmpty() && $class->department)<span class="ad-dept">{{ $class->department->name }}</span>@endif</h4>@if(!$isLeader && $leader)<small class="ad-meta">Same step as {{ $leader->name }}. Moves with it.</small>@endif</div>
                    <div class="ad-chips">@forelse($class->arms as $arm)@if($canEdit)<button type="button" class="ad-tag ad-arm-chip" data-open-sheet="edit-arm-{{ $arm->id }}" aria-label="Edit {{ $class->name }} {{ $arm->name }}">{{ $arm->name }}</button>@else<span class="ad-tag">{{ $arm->name }}</span>@endif @empty<span class="ad-meta">No arms</span>@endforelse</div>
                    <div class="ad-meta">{{ $class->students_count }} students · {{ $class->class_subjects_count }} subjects</div>
                    <div class="ad-actions">
                        @if($canEdit && $isLeader && $class->position !== null)
                            <form method="post" action="{{ route('academics.classes.move', $class) }}">@csrf<input type="hidden" name="direction" value="up"><button class="ad-iconbtn" aria-label="Move {{ $class->name }} up" @disabled($stepIndex === 0)><x-icon name="arrow-up" /></button></form>
                            <form method="post" action="{{ route('academics.classes.move', $class) }}">@csrf<input type="hidden" name="direction" value="down"><button class="ad-iconbtn" aria-label="Move {{ $class->name }} down" @disabled($stepIndex === $steps->count() - 1)><x-icon name="arrow-down" /></button></form>
                        @endif
                        @if($canEdit)<button type="button" class="ad-iconbtn" aria-label="Edit {{ $class->name }}" data-open-sheet="edit-class-{{ $class->id }}"><x-icon name="pencil" /></button><button type="button" class="ad-iconbtn" aria-label="Add an arm to {{ $class->name }}" data-open-sheet="add-arm-{{ $class->id }}"><x-icon name="plus" /></button><button type="button" class="ad-iconbtn" aria-label="Delete {{ $class->name }}" data-open-sheet="delete-class-{{ $class->id }}"><x-icon name="trash" /></button>@endif
                    </div>
                </div>

                @if($canEdit)
                    <x-oak.sheet id="edit-class-{{ $class->id }}" label="Edit {{ $class->name }}"><div class="oak-sheet"><h2>Edit {{ $class->name }}</h2><form method="post" action="{{ route('academics.classes.update', $class) }}" class="ad-form-grid" x-data="{ level: '{{ $class->academic_level_id }}', after: '{{ $sameStep->count() > 1 && !$isLeader ? $leader->id : '' }}' }">@csrf @method('PUT')
                        <div class="oak-field"><label>Level</label><select name="academic_level_id" required x-model="level">@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></div>
                        <div class="oak-field"><label>Class name</label><input name="name" required maxlength="100" value="{{ $class->name }}"></div>
                        @if($departments->isNotEmpty())<div class="oak-field"><label>Department</label><select name="department_id"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($class->department_id === $department->id)>{{ $department->name }}</option>@endforeach</select></div>@endif
                        <div class="oak-field"><label>Comes after</label><select name="comes_after_id" x-model="after"><option value="">First class in this level</option>@foreach($comesAfterByLevel as $levelId => $options)@foreach($options->where('id','!=',$class->id) as $option)<option value="{{ $option->id }}" x-show="level === '{{ $levelId }}'" :disabled="level !== '{{ $levelId }}'">{{ $option->name }}</option>@endforeach @endforeach</select></div>
                        @if($departments->isNotEmpty())<fieldset class="oak-field ad-wide" x-show="after"><legend>Where does it go?</legend><label class="ad-choice"><input type="radio" name="placement" value="new" checked> New step after the selected class</label><label class="ad-choice"><input type="radio" name="placement" value="same"> Same step as the selected class</label></fieldset>@else<input type="hidden" name="placement" value="new">@endif
                        <p class="ad-meta ad-wide">Future results and fees will follow the new level. Past results are unchanged.</p>
                        <div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Save class</button></div>
                    </form></div></x-oak.sheet>
                    <x-oak.sheet id="add-arm-{{ $class->id }}" label="Add an arm to {{ $class->name }}"><div class="oak-sheet"><h2>Add an arm to {{ $class->name }}</h2><form method="post" action="{{ route('academics.arms.store') }}" class="ad-form-grid">@csrf<input type="hidden" name="class_id" value="{{ $class->id }}"><div class="oak-field ad-wide"><label>Arm name</label><input name="name" required maxlength="100" placeholder="e.g. Gold"></div><div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add arm</button></div></form></div></x-oak.sheet>
                    @foreach($class->arms as $arm)<x-oak.sheet id="edit-arm-{{ $arm->id }}" label="Edit {{ $class->name }} {{ $arm->name }}"><div class="oak-sheet"><h2>Edit {{ $class->name }} {{ $arm->name }}</h2><form method="post" action="{{ route('academics.arms.update',$arm) }}" class="ad-form-grid">@csrf @method('PUT')<div class="oak-field ad-wide"><label>Arm name</label><input name="name" required maxlength="100" value="{{ $arm->name }}"></div><div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Save arm</button></div></form><hr><p>If this arm has students, results, attendance, invoices or assignments, deletion will be blocked and the counts will be shown.</p><form method="post" action="{{ route('academics.arms.destroy',$arm) }}" class="oak-sheet-actions">@csrf @method('DELETE')<button class="oak-btn oak-btn-danger">Delete this arm</button></form></div></x-oak.sheet>@endforeach
                    <x-oak.sheet id="delete-class-{{ $class->id }}" label="Delete {{ $class->name }}"><div class="oak-sheet"><h2>Delete {{ $class->name }}?</h2><p>This removes the empty class and its empty setup records. Students, results, attendance and invoices are never deleted; if any exist, this action will be blocked.</p><form method="post" action="{{ route('academics.classes.destroy', $class) }}" class="oak-sheet-actions">@csrf @method('DELETE')<button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-danger">Delete class</button></form></div></x-oak.sheet>
                @endif
            @endforeach
        @empty
            <div class="oak-empty"><strong>No classes yet</strong>@if($canEdit)Add the first class. Its position is set automatically at this section's boundary.@elseThe Admin has not added classes to your section yet.@endif</div>
        @endforelse
        @if($classes->hasPages())<div class="oak-section">{{ $classes->links() }}</div>@endif
        </section>

        @if($canEdit)
            <x-oak.sheet id="add-class-sheet" labelledby="add-class-title"><div class="oak-sheet"><h2 id="add-class-title">{{ $classes->total() ? 'Add a class' : 'Add the first class' }}</h2><form method="post" action="{{ route('academics.classes.store') }}" class="ad-form-grid" x-data="{ level: '{{ $levels->first()?->id }}', after: '' }">@csrf
                <div class="oak-field"><label>Level</label><select name="academic_level_id" required x-model="level"><option value="">Choose level</option>@foreach($levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></div>
                <div class="oak-field"><label>Class name</label><input name="name" required maxlength="100"></div>
                @if($departments->isNotEmpty())<div class="oak-field"><label>Department</label><select name="department_id"><option value="">No department</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></div>@endif
                <div class="oak-field"><label>Comes after</label><select name="comes_after_id" x-model="after"><option value="">First class in this level</option>@foreach($comesAfterByLevel as $levelId => $options)@foreach($options as $option)<option value="{{ $option->id }}" x-show="level === '{{ $levelId }}'" :disabled="level !== '{{ $levelId }}'">{{ $option->name }}</option>@endforeach @endforeach</select></div>
                @if($departments->isNotEmpty())<fieldset class="oak-field ad-wide" x-show="after"><legend>Where does it go?</legend><label class="ad-choice"><input type="radio" name="placement" value="new" checked> New step after the selected class</label><label class="ad-choice"><input type="radio" name="placement" value="same"> Same step as the selected class</label></fieldset>@else<input type="hidden" name="placement" value="new">@endif
                <div class="oak-sheet-actions ad-wide"><button type="button" class="oak-btn oak-btn-quiet" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Add class</button></div>
            </form></div></x-oak.sheet>
        @endif
    @endif
</main>
@include('core._shell-end')
@livewireScripts
</body></html>
