@php($credentialSlips = collect($credentialSlips ?? []))
<section class="credential-sheet" id="credential-panel" data-credential-sheet>
    @foreach($credentialSlips->chunk(8) as $page)
        <div class="credential-page">
            @foreach($page as $slip)
                <article class="credential-slip" data-credential-slip>
                    <header>
                        <strong>{{ $slip['school_name'] }}</strong>
                        <span>{{ $slip['type'] === 'parent' ? 'Parent sign-in' : ($slip['type'] === 'staff' ? 'Staff sign-in' : ($slip['learner_term'] ?? 'Student').' sign-in') }}</span>
                    </header>
                    <dl>
                        <div><dt>{{ $slip['type'] === 'student' ? ($slip['learner_term'] ?? 'Student').' name' : 'Name' }}</dt><dd>{{ $slip['name'] }}</dd></div>
                        @if($slip['type'] === 'student')
                            <div><dt>Class and arm</dt><dd>{{ $slip['class'] }} {{ $slip['arm'] }}</dd></div>
                            @if($slip['registration_number'])<div><dt>Registration number</dt><dd>{{ $slip['registration_number'] }}</dd></div>@endif
                        @elseif($slip['type'] === 'parent')
                            <div><dt>{{ Str::plural('Child', count($slip['children'])) }}</dt><dd>{{ implode(', ', $slip['children']) }}</dd></div>
                        @elseif(filled($slip['role'] ?? null))
                            <div><dt>Role</dt><dd>{{ $slip['role'] }}</dd></div>
                        @endif
                        <div><dt>Username</dt><dd data-credential-username>{{ $slip['username'] }}</dd></div>
                        <div><dt>Temporary password</dt><dd>@if($slip['type'] === 'staff')<code id="temporary-password" data-credential-password>{{ $slip['temporary_password'] }}</code>@else<code data-secret data-credential-password>{{ $slip['temporary_password'] }}</code>@endif</dd></div>
                        <div><dt>Sign in</dt><dd>{{ $slip['sign_in_address'] }}</dd></div>
                    </dl>
                    <p>You will be asked to choose a new password the first time you sign in.</p>
                </article>
            @endforeach
        </div>
    @endforeach
</section>
