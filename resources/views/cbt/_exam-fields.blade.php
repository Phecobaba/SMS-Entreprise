<label>Type
    <select required name="exam_type_id" class="mt-1 min-h-11 w-full rounded border p-2">
        @foreach($types as $type)<option value="{{ $type->id }}" @selected((int) old('exam_type_id', $exam?->exam_type_id) === $type->id)>{{ $type->name }}</option>@endforeach
    </select>
</label>
<label>Title<input required name="title" maxlength="160" value="{{ old('title', $exam?->title) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label>Duration, minutes<input required type="number" min="1" max="240" name="duration" value="{{ old('duration', $exam?->duration ?? 45) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label>Mark for a single-answer upload<input required type="number" min="1" max="65535" step="1" name="default_question_marks" value="{{ old('default_question_marks', $exam?->default_question_marks ?? 1) }}" class="mt-1 min-h-11 w-full rounded border p-2"><span class="mt-1 block text-sm text-slate-600">Used when Correct Answer contains one letter without an override.</span></label>
<label>Start at, school time (WAT; optional)<input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', $exam?->scheduled_at?->timezone('Africa/Lagos')->format('Y-m-d\TH:i')) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label>Closes at, school time (WAT; optional)<input type="datetime-local" name="closes_at" value="{{ old('closes_at', $exam?->closes_at?->timezone('Africa/Lagos')->format('Y-m-d\TH:i')) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<label>Closing option
    <select name="closes_automatically" class="mt-1 min-h-11 w-full rounded border p-2">
        <option value="1" @selected((string) old('closes_automatically', (int) ($exam?->closes_automatically ?? true)) === '1')>Close automatically at the end time</option>
        <option value="0" @selected((string) old('closes_automatically', (int) ($exam?->closes_automatically ?? true)) === '0')>Stay open until a Head or Admin ends it</option>
    </select>
</label>
<div class="contents" x-data="{ classId: String(@js(old('class_id', $exam?->class_id) ?: '')), subjectId: String(@js(old('subject_id', $exam?->subject_id) ?: '')), map: @js($subjectsByClass), get subjects() { return this.map[this.classId] || [] }, changedClass() { this.subjectId = ''; } }">
    <label>Class (student exams)
        <select name="class_id" x-model="classId" @change="changedClass" class="mt-1 min-h-11 w-full rounded border p-2"><option value="">None</option>
            @foreach($classes as $class)<option value="{{ $class->id }}">{{ $class->name }} · {{ $class->level?->section?->name }}</option>@endforeach
        </select>
    </label>
    <label>Subject (student exams)
        <select name="subject_id" x-model="subjectId" :disabled="!classId || subjects.length === 0" class="mt-1 min-h-11 w-full rounded border p-2"><option value="">Choose subject</option><template x-for="subject in subjects" :key="subject.id"><option :value="String(subject.id)" x-text="subject.name"></option></template></select>
        <span class="mt-1 block text-sm text-slate-600" x-show="classId && subjects.length === 0">No subjects are assigned to this class.</span>
        @if($unassignedExamSubject ?? false)<span class="mt-1 block text-sm text-amber-700">This exam's subject is no longer assigned to its class. It remains available for this existing exam.</span>@endif
    </label>
</div>
<label>Term for Results (optional)
    <select name="term_id" class="mt-1 min-h-11 w-full rounded border p-2"><option value="">None</option>
        @foreach($terms as $term)<option value="{{ $term->id }}" @selected((int) old('term_id', $exam?->term_id) === $term->id)>{{ $term->name }} · {{ $term->session?->name }}</option>@endforeach
    </select>
</label>
<label>Results component (optional)
    <select name="assessment_component_id" class="mt-1 min-h-11 w-full rounded border p-2"><option value="">None</option>
        @foreach($components as $component)<option value="{{ $component->id }}" @selected((int) old('assessment_component_id', $exam?->assessment_component_id) === $component->id)>{{ $component->name }} / {{ $component->max_score }}</option>@endforeach
    </select>
</label>
@if($canManageEntrance)<label>Entrance pass mark (%)<input type="number" step="0.01" min="0" max="100" name="pass_mark" value="{{ old('pass_mark', $exam?->pass_mark) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<fieldset class="rounded border p-3 sm:col-span-2"><legend class="px-1 font-semibold">Entrance exam Levels</legend><p class="text-sm text-slate-600">Used only when Type is Entrance. Leave every Level unticked to make the exam visible to all candidates.</p><div class="mt-2 grid gap-1 sm:grid-cols-2">@php($selectedLevels=collect(old('level_ids', $exam?->levels?->pluck('id')->all() ?? []))->map(fn($id)=>(int)$id))@foreach($entranceLevels as $level)<label class="flex min-h-11 items-center gap-3"><input type="checkbox" name="level_ids[]" value="{{ $level->id }}" @checked($selectedLevels->contains($level->id))><span>{{ $level->name }} · {{ $level->section?->name }}</span></label>@endforeach</div></fieldset>@endif
<label>Result release
    <select name="result_release_type" class="mt-1 min-h-11 w-full rounded border p-2">
        @foreach(['Immediate','Scheduled','Manual'] as $release)<option @selected(old('result_release_type', $exam?->result_release_type ?? 'Immediate') === $release)>{{ $release }}</option>@endforeach
    </select>
</label>
<label>Scheduled release time, school time (WAT)<input type="datetime-local" name="result_release_at" value="{{ old('result_release_at', $exam?->result_release_at?->timezone('Africa/Lagos')->format('Y-m-d\TH:i')) }}" class="mt-1 min-h-11 w-full rounded border p-2"></label>
<div class="space-y-2 sm:col-span-2">
    @foreach([['show_score','Show score'],['show_correct_answers','Show correct answers'],['show_explanations','Show explanations']] as [$field,$label])
        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, (bool) ($exam?->$field ?? false)))> {{ $label }}</label>
    @endforeach
</div>
