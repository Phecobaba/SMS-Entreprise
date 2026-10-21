<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Attendance alerts</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>@include('core._shell-start')
<main class="oak-dashboard" style="max-width:960px">
<div class="oak-dashboard-heading"><div><h1>Attendance alerts</h1><p>Class rolls that were not fully marked by their deadline</p></div></div>
@if($locked ?? false)
<section class="oak-locked"><x-icon name="lock" /><h3>You haven't been assigned to a section yet</h3><p>Ask the Admin to assign your account to a Section. Attendance alerts stay hidden until then.</p></section>
@else
<nav class="ad-tabs" aria-label="Attendance"><a href="{{ route('attendance.alerts') }}" aria-current="page" wire:navigate>Alerts</a>@can('manage_attendance')<a href="{{ route('attendance.settings') }}" wire:navigate>Sessions &amp; times</a>@endcan</nav>
@if(session('status'))<div class="oak-notice oak-notice-success" role="status"><div><strong>Done.</strong> {{ session('status') }}</div></div>@endif
@if($errors->any())<div class="oak-notice oak-notice-danger" role="alert"><div>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
<div class="ad-card">
<div class="oak-table-scroll"><table class="oak-table ad-queue"><thead><tr><th>Class and session</th><th>Date</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
@forelse($alerts as $alert)
@php($arm = $arms[$alert->class_arm_id] ?? null)
@php($sectionId = $arm?->schoolClass?->level?->section_id)
@php($preview = $alert->session && $sectionId ? ($notHeldPreviews[$sectionId.'|'.$alert->date->format('Y-m-d').'|'.$alert->session] ?? null) : null)
<tr><td><a class="oak-link" wire:navigate href="{{ route('attendance.day', ['arm' => $alert->class_arm_id, 'date' => $alert->date->format('Y-m-d'), 'session' => $alert->session ?? 'Morning']) }}">{{ $arm?->schoolClass?->name ?? 'Class' }} {{ $arm?->name ?? $alert->class_arm_id }}</a> · {{ $alert->session ?? 'Legacy whole day' }}</td><td>{{ $alert->date->format('D, j M Y') }}</td><td>
@can('manage_attendance')@if($preview)<details class="ad-confirm"><summary class="oak-btn oak-btn-secondary">Session not held?</summary><div class="ad-confirm-panel"><strong>Mark {{ $alert->session }} as not held?</strong>@include('attendance._not-held-impact', ['preview' => ['selection' => $alert->session, 'open_alerts' => $preview['open_alerts'], 'sessions' => [$alert->session => $preview]]])<form method="post" action="{{ route('attendance.not-held.store', $sectionId) }}">@csrf<input type="hidden" name="date" value="{{ $alert->date->format('Y-m-d') }}"><input type="hidden" name="session" value="{{ $alert->session }}"><button class="oak-btn oak-btn-primary">Confirm not held</button></form></div></details>@endif@endcan
</td></tr>
@empty<tr><td colspan="3"><div class="oak-empty"><strong>All rolls were marked on time</strong>When a class roll is not fully marked by its deadline, it appears here.</div></td></tr>@endforelse
</tbody></table></div>
<p class="ad-meta">An alert closes when the session roll is fully marked or the Section marks that session not held.</p>
</div>
@if($alerts->hasPages()){{ $alerts->links() }}@endif
@endif
</main>@include('core._shell-end')@livewireScripts</body></html>
