<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Applications</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 p-5">
@include('core._shell-start')
<main class="mx-auto max-w-6xl rounded bg-white p-4 shadow sm:p-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Applications</h1>
        <div class="flex flex-wrap gap-2">
            @if($canManageSessions)<a class="inline-flex min-h-11 items-center rounded border px-4" href="{{ route('admissions.sessions.index') }}">Exam sessions</a>@endif
            @if($canManageApplications)<a class="inline-flex min-h-11 items-center rounded border px-4" href="{{ route('admissions.applications.settings') }}">Admission settings</a>@endif
        </div>
    </div>
    @if(session('status'))<p class="mt-3 rounded bg-green-50 p-3">{{ session('status') }}</p>@endif
    @if($errors->any())<div class="mt-3 rounded bg-red-50 p-3">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <nav class="mt-5 flex max-w-full gap-2 overflow-x-auto pb-1" aria-label="Application filters">
        @foreach(['all'=>'All','paid'=>'Paid','unpaid'=>'Unpaid','awaiting_date'=>'Awaiting date','expired'=>'Expired'] as $key=>$label)
            <a class="inline-flex min-h-11 shrink-0 items-center rounded-full border px-4 {{ $tab===$key ? 'bg-slate-900 text-white' : 'bg-white' }}" href="{{ route('admissions.applications.index', array_filter(['tab'=>$key,'q'=>$search])) }}">{{ $label }} <span class="ml-2">{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>
    <form class="mt-4 flex flex-wrap gap-2"><input type="hidden" name="tab" value="{{ $tab }}"><input class="min-h-11 min-w-0 flex-1 rounded border px-3" name="q" value="{{ $search }}" placeholder="Child, guardian or username"><button class="min-h-11 rounded bg-blue-700 px-4 text-white">Search</button></form>
    @if($tab==='awaiting_date' && $canManageSessions)
    <form class="mt-4" method="post" action="{{ route('admissions.applications.assign-session') }}">@csrf
        <div class="flex flex-wrap items-end gap-2 rounded bg-slate-50 p-3"><label class="min-w-56 flex-1">Exam session<select class="mt-1 min-h-11 w-full rounded border px-3" name="exam_session_id" required><option value="">Choose a session</option>@foreach($sessionOptions as $session)<option value="{{ $session->id }}">{{ $session->name }} · {{ $session->starts_at->timezone('Africa/Lagos')->format('d M Y, g:i a') }}{{ $session->section ? ' · '.$session->section->name : ' · School-wide' }}</option>@endforeach</select></label><button class="min-h-11 rounded bg-blue-700 px-4 text-white">Assign selected</button></div>
    @endif
    <div class="mt-4 overflow-x-auto"><table class="w-full min-w-[720px] text-left"><thead><tr>
        @if($tab==='awaiting_date' && $canManageSessions)<th><label class="inline-flex min-h-11 min-w-11 items-center"><input type="checkbox" onclick="document.querySelectorAll('[data-application-check]').forEach(el => el.checked = this.checked)"><span class="sr-only">Select all on this page</span></label></th>@endif
        <th>Child</th><th>Class</th><th>Username</th><th>Status</th><th>Outcome</th><th>Exam session</th><th>Date</th></tr></thead><tbody>
        @forelse($rows as $row)<tr class="border-t">
            @if($tab==='awaiting_date' && $canManageSessions)<td><label class="inline-flex min-h-11 min-w-11 items-center"><input data-application-check type="checkbox" name="applications[]" value="{{ $row->id }}"><span class="sr-only">Select {{ $row->child_first_name }} {{ $row->child_surname }}</span></label></td>@endif
            <td><a class="inline-flex min-h-11 items-center text-blue-700 underline" href="{{ route('admissions.applications.show',$row) }}">{{ $row->child_first_name }} {{ $row->child_surname }}</a></td><td>{{ $row->level?->name }} · {{ $row->level?->section?->name }}</td><td>{{ $row->username }}</td><td>{{ $states[$row->id] }}</td><td>{{ $row->assessment_outcome ?? 'Undecided' }}</td><td>{{ $row->examSession?->name ?? 'Awaiting date' }}</td><td>{{ $row->created_at->format('d M Y') }}</td>
        </tr>@empty<tr><td colspan="8" class="py-5">No applications found.</td></tr>@endforelse
    </tbody></table></div>
    @if($tab==='awaiting_date' && $canManageSessions)</form>@endif
    <div class="mt-4">{{ $rows->links() }}</div>
</main>
@include('core._shell-end')
</body></html>
