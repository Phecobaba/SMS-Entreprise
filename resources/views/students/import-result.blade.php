<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Students imported</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body>
@include('core._shell-start')<main class="ad-page st-page si-page"><header class="ad-page-head"><div><p class="ad-eyebrow">Import complete</p><h1>{{ count($credentials) }} {{ Str::plural('student', count($credentials)) }} created</h1><p>The import report remains available. These temporary passwords do not.</p></div></header>
@if($credentials)
    @php
        $studentSlips = collect($credentials)->map(fn ($credential) => [
            'type' => 'student', 'learner_term' => $credential['learner_term'] ?? 'Student', 'school_name' => $credential['school_name'],
            'name' => $credential['student_name'], 'class' => $credential['class'], 'arm' => $credential['arm'],
            'registration_number' => $credential['registration_number'], 'username' => $credential['student_username'],
            'temporary_password' => $credential['student_password'], 'sign_in_address' => $credential['sign_in_address'],
        ]);
        $parentSlips = collect($credentials)->filter(fn ($credential) => filled($credential['parent_password']))->map(fn ($credential) => [
            'type' => 'parent', 'school_name' => $credential['school_name'], 'name' => $credential['parent_name'],
            'children' => $credential['parent_children'], 'username' => $credential['parent_username'],
            'temporary_password' => $credential['parent_password'], 'sign_in_address' => $credential['sign_in_address'],
        ]);
        $credentialSlips = $studentSlips->concat($parentSlips);
    @endphp
    <div class="oak-notice oak-notice-danger ac-no-print"><b>Save these sign-in details now.</b> They disappear when you leave this page and are never stored in the import report.</div>
    <div class="ad-actions ac-no-print"><button class="oak-btn oak-btn-secondary" type="button" data-copy-credentials>Copy credentials</button><span role="status" aria-live="polite" data-copy-status></span><button class="oak-btn oak-btn-secondary" type="button" onclick="window.print()">Print / Save as PDF</button><a class="oak-btn oak-btn-primary" href="{{ route('students.index') }}" data-finish>Finish</a></div>
    @include('components.credential-slips', ['credentialSlips' => $credentialSlips])
@else<section class="ad-card"><h2>Import already confirmed</h2><p>No students were created by this repeat request. Credentials were shown only on the first response.</p><a class="oak-btn oak-btn-primary" href="{{ route('students.index') }}">Return to Students</a></section>@endif</main>
@include('core._shell-end') @livewireScripts
@if($credentials)<script>history.replaceState(null,'',@json(route('students.imports.show',$import)));const clear=()=>document.querySelectorAll('[data-secret]').forEach(x=>x.textContent='');document.querySelector('[data-finish]').addEventListener('click',clear);window.addEventListener('pagehide',clear)</script>@endif
</body></html>
