@extends('portals.layout')
@section('title', 'My profile')
@section('content')
<div class="oak-section-head"><div><h1>My profile</h1><p class="oak-subpage-note">Your account details</p></div></div>
<div class="pf-list"><div><dt>Name</dt><dd>{{ auth()->user()->name }}</dd></div><div><dt>Sign-in identifier</dt><dd>{{ auth()->user()->username ?: auth()->user()->email ?: 'Ask the school office' }}</dd></div>
@if($context['kind'] === 'student')<div><dt>Class</dt><dd>{{ $class }}@if($section) · {{ $section }}@endif</dd></div><div><dt>Admission date</dt><dd>{{ $student->admission_date?->format('j M Y') ?? '—' }}</dd></div>@endif</div>
@if($context['kind'] === 'parent')<section class="oak-section"><h2>Your children</h2>@foreach($context['wards'] as $ward)<div class="oak-row"><div><h3>{{ $ward['name'] }}</h3><p>{{ $ward['class'] }}@if($ward['section']) · {{ $ward['section'] }}@endif</p></div></div>@endforeach</section>@endif
<p class="oak-subpage-note oak-section">{{ $context['kind'] === 'parent' ? 'To change your name, sign-in details, or linked children, ask the school office.' : 'To change your name or email, ask the school office.' }}</p>
@endsection
