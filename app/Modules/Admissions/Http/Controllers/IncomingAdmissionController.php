<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admissions\Services\IncomingAdmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncomingAdmissionController extends Controller
{
    public function index(Request $request, IncomingAdmissionService $incoming): View
    {
        return view('academics.incoming', $this->pageData($request, $incoming));
    }

    public function review(Request $request, IncomingAdmissionService $incoming): View
    {
        $data = $this->validatedAction($request);
        $sectionId = (int) $data['section_id'];
        $allRemaining = (bool) ($data['all_remaining'] ?? false);
        $placements = $this->placements($data);
        $plan = $data['intent'] === 'accept'
            ? $incoming->previewAccept($request->user(), $sectionId, $placements)
            : $incoming->previewNotContinuing($request->user(), $sectionId, $data['students'] ?? [], $allRemaining);
        $request->merge(['section' => $sectionId]);

        return view('academics.incoming', [
            ...$this->pageData($request, $incoming),
            'plan' => $plan,
            'intent' => $data['intent'],
            'submittedPlacements' => $placements,
        ]);
    }

    public function accept(Request $request, IncomingAdmissionService $incoming): RedirectResponse
    {
        $data = $this->validatedAction($request, 'accept');
        $plan = $incoming->accept($request->user(), (int) $data['section_id'], $this->placements($data));

        return redirect()->route('academics.promotion.incoming.index', ['section' => $data['section_id']])
            ->with('incoming_report', [
                'intent' => 'accept', 'affected' => $plan['accepted'],
                'unchanged' => $plan['unchanged'], 'remaining' => $plan['remaining'],
            ]);
    }

    public function notContinuing(Request $request, IncomingAdmissionService $incoming): RedirectResponse
    {
        $data = $this->validatedAction($request, 'not_continuing');
        $plan = $incoming->markNotContinuing(
            $request->user(), (int) $data['section_id'], $data['students'] ?? [], (bool) ($data['all_remaining'] ?? false),
        );

        return redirect()->route('academics.promotion.incoming.index', ['section' => $data['section_id']])
            ->with('incoming_report', [
                'intent' => 'not_continuing', 'affected' => $plan['graduated'],
                'unchanged' => 0, 'remaining' => $plan['remaining'],
            ]);
    }

    private function pageData(Request $request, IncomingAdmissionService $incoming): array
    {
        $actor = $request->user();
        $sections = $incoming->sectionsFor($actor);
        if ($sections->isEmpty()) {
            abort(403, 'Your section does not receive incoming pupils from another section.');
        }
        $selectedSection = $actor->can('view_all_sections')
            ? ($request->integer('section') ?: $sections->first()->id)
            : $sections->first()->id;
        $page = $incoming->page($actor, $selectedSection);

        return $page + [
            'sections' => $sections,
            'selectedSection' => $selectedSection,
            'canViewAll' => $actor->can('view_all_sections'),
            'plan' => null,
            'intent' => null,
            'submittedPlacements' => [],
            'report' => $request->session()->get('incoming_report'),
        ];
    }

    private function validatedAction(Request $request, ?string $requiredIntent = null): array
    {
        $data = $request->validate([
            'section_id' => ['required', 'integer'],
            'intent' => ['required', 'in:accept,not_continuing'],
            'students' => ['nullable', 'array'],
            'students.*' => ['integer'],
            'class' => ['nullable', 'array'],
            'class.*' => ['nullable', 'integer'],
            'arm' => ['nullable', 'array'],
            'arm.*' => ['nullable', 'integer'],
            'all_remaining' => ['nullable', 'boolean'],
        ]);
        if ($requiredIntent !== null && $data['intent'] !== $requiredIntent) {
            abort(422);
        }

        return $data;
    }

    private function placements(array $data): array
    {
        return collect($data['students'] ?? [])->mapWithKeys(function ($studentId) use ($data): array {
            $id = (int) $studentId;

            return [$id => [
                'class_id' => $data['class'][$id] ?? null,
                'arm_id' => $data['arm'][$id] ?? null,
            ]];
        })->all();
    }
}
