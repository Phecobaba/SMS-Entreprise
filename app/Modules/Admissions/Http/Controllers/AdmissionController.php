<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\AdmissionChecklistItem;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Services\AdmissionPipeline;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdmissionController extends Controller
{
    public function registration(): View
    {
        return view('admissions.register');
    }

    public function register(Request $request, AdmissionPipeline $pipeline, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->where('school_id', $context->id())],
            'phone' => ['required', 'string', 'max:40'],
            'applied_level_id' => ['nullable', 'integer', Rule::exists('academic_levels', 'id')->where('school_id', $context->id())],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $candidate = $pipeline->register($data);
        Auth::loginUsingId($candidate->user_id);
        $request->session()->regenerate();

        return redirect()->route('admissions.mine')->with('status', 'Registration received. Payment confirmation is pending.');
    }

    public function mine(PaymentLedger $payments): View
    {
        $candidate = Candidate::with(['history', 'checklist.item', 'offer'])->where('user_id', Auth::id())->firstOrFail();

        return view('admissions.mine', ['candidate' => $candidate, 'invoices' => $payments->candidateInvoices($candidate->id)]);
    }

    public function respondOwn(Request $request, AdmissionPipeline $pipeline): RedirectResponse
    {
        $data = $request->validate(['response' => ['required', Rule::in(['Accepted', 'Declined'])]]);
        $candidate = Candidate::where('user_id', Auth::id())->firstOrFail();
        $pipeline->respond($candidate, $data['response'], $request->user());

        return back()->with('status', 'Your response was recorded.');
    }

    public function index(Request $request, SectionAccess $sections): View
    {
        $candidates = $sections->scopeCandidates(Candidate::with(['offer', 'appliedLevel.section']), $request->user());

        return view('admissions.index', [
            'candidates' => $candidates->latest()->paginate(20),
            'items' => AdmissionChecklistItem::orderBy('label')->paginate(20, ['*'], 'items_page'),
        ]);
    }

    public function show(int $candidate, Request $request, SectionAccess $sections): View
    {
        $sections->assertCandidate($request->user(), $candidate);
        $record = Candidate::with(['history', 'checklist.item', 'offer', 'student', 'appliedLevel.section'])->findOrFail($candidate);
        $sectionId = $record->appliedLevel?->section_id;
        $arms = ClassArm::with('schoolClass')->whereHas(
            'schoolClass.level',
            fn ($level) => $level->where('section_id', $sectionId),
        )->orderBy('id')->get();

        return view('admissions.show', [
            'candidate' => $record,
            'arms' => $arms,
        ]);
    }

    public function examTaken(int $candidate, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $pipeline->recordExamTaken(Candidate::findOrFail($candidate), $request->user());

        return back()->with('status', 'Exam attendance recorded.');
    }

    public function examOutcome(int $candidate, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $data = $request->validate(['outcome' => ['required', Rule::in(['Passed', 'Failed'])]]);
        $pipeline->recordExamOutcome(Candidate::findOrFail($candidate), $request->user(), $data['outcome']);

        return back()->with('status', 'Manual exam outcome recorded.');
    }

    public function addChecklistItem(AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        $data = $request->validate(['label' => ['required', 'string', 'max:255', Rule::unique('admission_checklist_items')->where('school_id', app(TenantContext::class)->id())]]);
        $pipeline->addChecklistItem($data['label']);

        return back()->with('status', 'Checklist item added to active candidates.');
    }

    public function checklist(int $candidate, int $item, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $data = $request->validate(['is_complete' => ['required', 'boolean']]);
        $pipeline->setChecklist(Candidate::findOrFail($candidate), AdmissionChecklistItem::findOrFail($item), $request->user(), (bool) $data['is_complete']);

        return back()->with('status', 'Checklist updated.');
    }

    public function offer(int $candidate, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $data = $request->validate(['response_deadline' => ['required', 'date', 'after_or_equal:today']]);
        $pipeline->extendOffer(Candidate::findOrFail($candidate), $request->user(), $data['response_deadline']);

        return back()->with('status', 'Offer extended.');
    }

    public function lapse(int $candidate, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $pipeline->lapse(Candidate::findOrFail($candidate));

        return back()->with('status', 'Expired offer marked lapsed.');
    }

    public function convert(int $candidate, AdmissionPipeline $pipeline, Request $request): RedirectResponse
    {
        app(SectionAccess::class)->assertCandidate($request->user(), $candidate);
        $data = $request->validate([
            'class_arm_id' => ['required', 'integer'],
            'override' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $pipeline->convert(Candidate::findOrFail($candidate), $request->user(), (int) $data['class_arm_id'], (bool) ($data['override'] ?? false), $data['override_reason'] ?? null);

        return back()->with('status', 'Candidate converted to student.');
    }
}
