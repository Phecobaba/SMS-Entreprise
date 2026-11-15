<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Admissions\Models\Application;
use App\Modules\Admissions\Models\ApplicationPaymentAttempt;
use App\Modules\Admissions\Services\ApplicationWorkflow;
use App\Modules\Core\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicApplicationController extends Controller
{
    public function create(Request $request, ApplicationWorkflow $workflow): View|RedirectResponse
    {
        if ($workflow->byToken($request->cookie(config('admissions.cookie')))) {
            return redirect()->route('applications.status');
        }
        $settings = SchoolSetting::firstOrCreate([]);

        return view('applications.create', [
            'open' => (bool) ($settings->admissions_open ?? true),
            'feeKobo' => ($settings->application_fee_enabled ?? false) ? (int) $settings->application_fee_kobo : 0,
            'levels' => AcademicLevel::with('section')->whereNotNull('section_id')
                ->orderBy('section_id')->orderBy('position')->orderBy('name')->limit(100)->get()->groupBy('section.name'),
        ]);
    }

    public function store(Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'website' => ['nullable', 'max:0'],
            'child_first_name' => ['required', 'string', 'max:100'],
            'child_surname' => ['required', 'string', 'max:100'],
            'child_other_names' => ['nullable', 'string', 'max:150'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'gender' => ['required', Rule::in(['Female', 'Male'])],
            'applied_level_id' => ['required', 'integer', Rule::exists('academic_levels', 'id')->whereNotNull('section_id')],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_relationship' => ['required', 'string', 'max:60'],
            'guardian_phone' => ['required', 'string', 'max:40'],
            'guardian_email' => ['required', 'email', 'max:255'],
            'guardian_address' => ['required', 'string', 'max:1000'],
            'username' => ['required', 'string', 'max:30'],
            'consent' => ['accepted'],
        ]);
        $result = $workflow->submit($data);
        $application = $result['application'];
        Cookie::queue($this->accessCookie($result['token']));
        if ($result['resumed']) {
            return redirect()->route('applications.status')
                ->with('status', 'You already started this application. Continue.');
        }
        $workflow->deferStatusMail($application, $application->fee_amount_kobo > 0 ? 'submitted' : 'registered');
        try {
            $destination = $application->fee_amount_kobo > 0
                ? $workflow->continuePayment($application)
                : route('applications.status');
        } catch (ValidationException $exception) {
            return redirect()->route('applications.status')->withErrors($exception->errors())
                ->with('status', 'Your application was saved. You can continue payment below.');
        }

        return redirect()->away($destination);
    }

    public function status(Request $request, ApplicationWorkflow $workflow): View|RedirectResponse
    {
        $application = $workflow->byToken($request->cookie(config('admissions.cookie')));
        if (! $application) {
            return redirect()->route('applications.create');
        }

        return $this->statusView($application, $workflow, true);
    }

    public function signedStatus(string $application, ApplicationWorkflow $workflow): View
    {
        $record = Application::where('public_reference', $application)->firstOrFail();

        return $this->statusView($record, $workflow, false);
    }

    public function continue(Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $application = $this->owned($request, $workflow);

        return redirect()->away($workflow->continuePayment($application));
    }

    public function check(Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $application = $this->owned($request, $workflow);
        $workflow->checkAgain($application);

        return redirect()->route('applications.status')->with('status', 'Application status checked.');
    }

    public function return(Request $request, ApplicationWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:80']]);
        $workflow->verifyReference($data['reference']);
        $attempt = ApplicationPaymentAttempt::with('application')
            ->where('reference', $data['reference'])->firstOrFail();
        $owned = $workflow->byToken($request->cookie(config('admissions.cookie')));
        if ($owned?->is($attempt->application)) {
            return redirect()->route('applications.status');
        }

        return redirect()->away($workflow->statusUrl($attempt->application));
    }

    public function forget(): RedirectResponse
    {
        return redirect()->route('applications.create')->withCookie(Cookie::forget(config('admissions.cookie'), '/apply'));
    }

    public function privacy(): View
    {
        return view('applications.privacy', ['text' => SchoolSetting::first()?->admissions_privacy_text ?: config('admissions.privacy_placeholder')]);
    }

    private function owned(Request $request, ApplicationWorkflow $workflow): Application
    {
        $application = $workflow->byToken($request->cookie(config('admissions.cookie')));
        abort_unless($application, 404);

        return $application;
    }

    private function statusView(Application $application, ApplicationWorkflow $workflow, bool $cookieOwner): View
    {
        $state = $workflow->status($application);

        $application->loadMissing('examSession');

        return view('applications.status', compact('application', 'state', 'cookieOwner'));
    }

    private function accessCookie(string $token): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(config('admissions.cookie'), $token, (int) config('admissions.cookie_minutes'), '/apply', null, null, true, false, 'lax');
    }
}
