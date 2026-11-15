<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Services\ApplicationAssessment;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Admissions\Services\ExamSessionWorkflow;
use App\Modules\CBT\Models\ExamSubmission;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ApplicationAdminController extends Controller
{
    public function index(Request $request, ApplicationWorkflow $applications, ExamSessionWorkflow $sessions): View
    {
        $search = trim((string) $request->query('q'));
        $tab = in_array($request->query('tab'), ['all', 'paid', 'unpaid', 'awaiting_date', 'expired'], true)
            ? (string) $request->query('tab') : 'all';
        $scoped = $sessions->scopeApplications(Application::query(), $request->user());
        $counts = [];
        foreach (['all', 'paid', 'unpaid', 'awaiting_date', 'expired'] as $key) {
            $counts[$key] = $this->applyTab(clone $scoped, $key)->count();
        }
        $rows = $this->applyTab(clone $scoped, $tab)->with(['level.section', 'attempts', 'examSession'])
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search): void {
                $needle = mb_strtolower($search);
                $q->whereRaw('LOWER(child_first_name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(child_surname) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(guardian_name) LIKE ?', ["%{$needle}%"])
                    ->orWhereRaw('LOWER(username) LIKE ?', ["%{$needle}%"]);
            }))
            ->latest()->paginate(20)->withQueryString();
        $states = $rows->getCollection()->mapWithKeys(fn (Application $row): array => [$row->id => $applications->status($row)])->all();

        return view('applications.admin-index', [
            'rows' => $rows,
            'search' => $search,
            'tab' => $tab,
            'counts' => $counts,
            'states' => $states,
            'sessionOptions' => $request->user()->can('manage_exam_sessions') ? $sessions->sessionOptions($request->user()) : collect(),
            'canManageApplications' => $request->user()->can('manage_admissions'),
            'canManageSessions' => $request->user()->can('manage_exam_sessions'),
        ]);
    }

    public function show(Application $application, Request $request, ExamSessionWorkflow $sessions): View
    {
        $sessions->assertApplication($request->user(), $application);
        $application->load(['level.section', 'attempts', 'examSession', 'candidate.user']);
        $score = $application->candidate
            ? ExamSubmission::with('exam')->where('candidate_id', $application->candidate->id)
                ->where('status', 'Submitted')->latest('submitted_at')->first()
            : null;

        return view('applications.admin-show', [
            'application' => $application,
            'sessionOptions' => $request->user()->can('manage_exam_sessions') ? $sessions->sessionOptions($request->user()) : collect(),
            'canManageApplications' => $request->user()->can('manage_admissions'),
            'canManageSessions' => $request->user()->can('manage_exam_sessions'),
            'canRecordOutcome' => $request->user()->can('manage_admissions') || $request->user()->can('manage_exam_sessions'),
            'examScore' => $score,
        ]);
    }

    public function outcome(Application $application, Request $request, ApplicationAssessment $assessments): RedirectResponse
    {
        $data = $request->validate([
            'assessment_outcome' => ['required', Rule::in(['Recommended', 'Not recommended', 'Undecided'])],
            'assessment_note' => ['nullable', 'string', 'max:500'],
        ]);
        $assessments->record($application, $request->user(), $data['assessment_outcome'], $data['assessment_note'] ?? null);

        return redirect()->route('admissions.applications.show', $application)->with('status', 'Assessment outcome saved.');
    }

    public function settings(): View
    {
        return view('applications.settings', [
            'settings' => SchoolSetting::firstOrCreate([]),
            'levels' => AcademicLevel::with('section')->whereNotNull('section_id')->orderBy('name')->limit(100)->get(),
        ]);
    }

    public function updateInPersonLevels(Request $request): RedirectResponse
    {
        $data = $request->validate(['levels' => ['sometimes', 'array'], 'levels.*' => ['integer', 'distinct']]);
        $ids = collect($data['levels'] ?? [])->map(fn ($id): int => (int) $id)->unique();
        abort_unless(AcademicLevel::whereIn('id', $ids)->count() === $ids->count(), 404);
        AcademicLevel::query()->update(['assessed_in_person' => false]);
        AcademicLevel::whereIn('id', $ids)->update(['assessed_in_person' => true]);
        app(AuditRecorder::class)->record($request->user(), 'application_in_person_levels_updated', null, [
            'level_count' => $ids->count(),
        ], 'Admissions', 'In-person assessment Levels updated.');

        return redirect()->route('admissions.applications.settings')->with('status', 'In-person Levels saved.');
    }

    public function websiteSettings(): View
    {
        return view('applications.website-settings', ['settings' => SchoolSetting::firstOrCreate([])]);
    }

    public function updateWebsiteSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string', 'max:40'],
            'opening_hours' => ['nullable', 'string', 'max:200'],
            'admissions_key_dates' => ['nullable', 'string', 'max:300'],
            'admissions_reopen_text' => ['nullable', 'string', 'max:300'],
            'privacy_contact_email' => ['nullable', 'email', 'max:255'],
            'privacy_last_updated_at' => ['nullable', 'date'],
            'non_admitted_retention_text' => ['nullable', 'string', 'max:300'],
        ]);
        SchoolSetting::firstOrCreate([])->update($data);
        app(AuditRecorder::class)->record($request->user(), 'website_settings_updated', null, [], 'Core', 'Landing-page website settings updated.');

        return redirect()->route('website-settings.edit')->with('status', 'Website settings saved.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'admissions_open' => ['required', 'boolean'], 'application_fee_enabled' => ['required', 'boolean'],
            'application_fee_naira' => ['required_if:application_fee_enabled,1', 'nullable', 'regex:/^\s*(?:\d{1,3}(?:,\d{3})*|\d+)(?:\.\d{1,2})?\s*$/'],
            'admissions_privacy_text' => ['required', 'string', 'max:10000'],
        ]);
        $kobo = $this->nairaToKobo($data['application_fee_naira'] ?? '0');
        SchoolSetting::firstOrCreate([])->update([
            'admissions_open' => (bool) $data['admissions_open'],
            'application_fee_enabled' => (bool) $data['application_fee_enabled'],
            'application_fee_kobo' => $kobo,
            'admissions_privacy_text' => $data['admissions_privacy_text'],
            'admissions_form_target' => (bool) $data['admissions_open'] ? '/apply' : null,
        ]);
        app(AuditRecorder::class)->record($request->user(), 'application_settings_updated', null, [], 'Admissions', 'Public application settings updated.');

        return redirect()->route('admissions.applications.settings')->with('status', 'Application settings saved.');
    }

    public function markPaid(Application $application, Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:500']]);
        $workflow->markPaid($application, $request->user(), $data['reason']);

        return back()->with('status', 'Application marked Registered.');
    }

    public function resend(Application $application, Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->resend($application, $request->user());

        return back()->with('status', 'Confirmation email requested.');
    }

    public function destroy(Application $application, Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $workflow->delete($application, $request->user());

        return redirect()->route('admissions.applications.index')->with('status', 'Application deleted.');
    }

    private function nairaToKobo(string $amount): int
    {
        $value = str_replace([',', ' '], '', trim($amount));
        [$naira, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ((int) $naira * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'paid' => $query->where('status', ApplicationWorkflow::REGISTERED),
            'unpaid' => $query->where('status', ApplicationWorkflow::AWAITING)
                ->where(fn (Builder $status) => $status->whereDoesntHave('attempts')
                    ->orWhereHas('attempts', fn (Builder $attempts) => $attempts->where('status', 'Pending'))),
            'awaiting_date' => $query->where('status', ApplicationWorkflow::REGISTERED)->whereNull('exam_session_id'),
            'expired' => $query->where(fn (Builder $status) => $status
                ->where('status', ApplicationWorkflow::EXPIRED)
                ->orWhere(fn (Builder $expired) => $expired
                    ->where('status', '!=', ApplicationWorkflow::REGISTERED)
                    ->where('expires_at', '<=', now())
                    ->whereDoesntHave('attempts', fn (Builder $attempts) => $attempts->where('status', 'Pending')))),
            default => $query,
        };
    }
}
