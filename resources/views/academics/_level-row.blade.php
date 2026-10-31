<div class="ad-class-row" style="grid-template-columns:minmax(0,1fr) auto">
    <div><h4>{{ $level->name }}</h4><small class="ad-meta">@unless($canViewAll){{ $level->section?->name }} · @endunless{{ $level->classes_count }} {{ Str::plural('class', $level->classes_count) }}</small></div>
    <div class="ad-actions"><button class="ad-iconbtn" type="button" aria-label="Edit {{ $level->name }}" data-open-sheet="edit-level-{{ $level->id }}"><x-icon name="pencil" /></button><button class="ad-iconbtn" type="button" aria-label="Delete {{ $level->name }}" data-open-sheet="delete-level-{{ $level->id }}"><x-icon name="trash" /></button></div>
</div>
<x-oak.sheet id="edit-level-{{ $level->id }}" label="Edit {{ $level->name }}"><form method="post" action="{{ route('academics.levels.update',$level) }}" class="oak-sheet">@csrf @method('PUT')
    <h2>Edit level</h2><p class="oak-sheet-sub">Renaming changes its label without changing school records.</p>
    <div class="oak-field"><label>Level name</label><input name="name" required maxlength="100" value="{{ $level->name }}"></div>
    <div class="oak-field"><label>Section</label><p style="margin:0">{{ $level->section?->name }}</p><span class="oak-hint">A level's section can't be changed. If it is in the wrong section, delete it and create it again.</span></div>
    <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Cancel</button><button class="oak-btn oak-btn-primary">Save level</button></div>
</form></x-oak.sheet>
<x-oak.sheet id="delete-level-{{ $level->id }}" label="Delete {{ $level->name }}"><form method="post" action="{{ route('academics.levels.destroy',$level) }}" class="oak-sheet">@csrf @method('DELETE')
    <h2>Delete {{ $level->name }}?</h2><p class="oak-sheet-sub">{{ $level->name }} is in {{ $level->section?->name }}. It can be deleted only when it has no classes, fee schedule, result template or admission application. If it is in use, the server lists what must be retained.</p>
    <div class="oak-sheet-actions"><button type="button" class="oak-btn oak-btn-secondary" data-close-sheet>Keep it</button><button class="oak-btn oak-btn-danger">Delete {{ $level->name }}</button></div>
</form></x-oak.sheet>
