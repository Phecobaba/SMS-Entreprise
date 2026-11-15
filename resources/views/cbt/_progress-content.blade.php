<section class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach(['not_started' => 'Not started', 'writing' => 'Writing', 'submitted' => 'Submitted', 'absent' => 'Absent'] as $key => $label)
        <div class="rounded border p-4"><span>{{ $label }}</span><strong class="block text-2xl">{{ $counts[$key] }}</strong></div>
    @endforeach
</section>
<section class="mt-6 grid gap-3">
@forelse($pupils as $pupil)
    @php($attempt=$pupil->cbt_attempt) @php($grant=$pupil->cbt_grant)
    <article class="grid gap-3 rounded border p-4 lg:grid-cols-[2fr_1fr_1fr_1.2fr_1fr_2fr] lg:items-start">
        <div><strong>{{ $pupil->user?->name }}</strong>@if($pupil->cbt_attempt_count > 1)<br><small>{{ $pupil->cbt_attempt_count }} attempts</small>@endif</div>
        <div><span class="text-xs text-slate-500 lg:hidden">Class </span>{{ $pupil->classArm?->schoolClass?->name }}</div>
        <div><span class="text-xs text-slate-500 lg:hidden">Arm </span>{{ $pupil->classArm?->name }}</div>
        <div><span class="text-xs text-slate-500 lg:hidden">Status </span>{{ $pupil->cbt_status }}@if($pupil->cbt_remaining !== null)<br><small>{{ gmdate('i:s', $pupil->cbt_remaining) }} remaining</small>@elseif($attempt?->submitted_at)<br><small>{{ $attempt->submitted_at->timezone('Africa/Lagos')->format('d M, H:i') }}</small>@endif</div>
        <div>@if($attempt?->status === 'Submitted'){{ $attempt->raw_score }} / {{ $attempt->max_score }}@else<span class="text-slate-500">—</span>@endif</div>
        <div>
            @if($grant)<p class="text-sm"><strong>Another attempt available</strong><br>Until {{ $grant->opens_until->timezone('Africa/Lagos')->format('d M Y, H:i') }}</p><form class="mt-2" method="post" action="{{ route('cbt.staff.resits.destroy', [$record, $grant]) }}" onsubmit="return confirm('Withdraw this unused attempt grant?')">@csrf @method('DELETE')<button class="inline-flex min-h-11 items-center rounded border px-3" type="submit">Withdraw</button></form>
            @elseif(in_array($record->status, ['Published','Closed'], true))<details><summary class="inline-flex min-h-11 cursor-pointer items-center rounded border px-3">Give another attempt</summary><form class="mt-3 grid gap-3" method="post" action="{{ route('cbt.staff.resits.store', [$record, $pupil]) }}" onsubmit="return confirm('Give this pupil one new attempt with this end time?')">@csrf<label class="grid gap-1 text-sm">Reason<input class="min-h-11 rounded border px-3" name="reason" maxlength="500" required></label><label class="grid gap-1 text-sm">New end time<input class="min-h-11 rounded border px-3" type="datetime-local" name="opens_until" min="{{ now('Africa/Lagos')->addMinute()->format('Y-m-d\TH:i') }}" required></label><button class="inline-flex min-h-11 items-center justify-center rounded bg-blue-700 px-3 text-white" type="submit">Confirm grant</button></form></details>@endif
        </div>
    </article>
@empty<p class="rounded border border-dashed p-5 text-slate-600">No pupils match this status.</p>@endforelse
</section>
<div class="mt-4">{{ $pupils->links() }}</div>
