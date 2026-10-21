<?php

namespace App\Modules\Admissions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Academics\Services\PromotionCatalog;
use App\Modules\Admissions\Services\IncomingAdmissionService;
use App\Modules\Admissions\Services\PromotionService;
use App\Modules\Admissions\Services\StudentDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    public function index(Request $request, PromotionCatalog $catalog, PromotionService $promotion): View
    {
        return view('academics.promotion', $this->pageData($request, $catalog, $promotion));
    }

    public function review(Request $request, PromotionCatalog $catalog, PromotionService $promotion): View
    {
        $data = $this->validatedPlan($request);
        $placements = $this->placements($data);
        $plan = $promotion->preview(
            $request->user(), (int) $data['source_class_id'],
            isset($data['source_arm_id']) ? (int) $data['source_arm_id'] : null,
            $placements,
        );
        $request->merge([
            'section' => $data['section_id'] ?? null,
            'source' => $data['source_class_id'],
            'arm' => $data['source_arm_id'] ?? null,
        ]);

        return view('academics.promotion', [
            ...$this->pageData($request, $catalog, $promotion),
            'plan' => $plan,
            'submittedPlacements' => $placements,
        ]);
    }

    public function apply(Request $request, PromotionService $promotion): RedirectResponse
    {
        $data = $this->validatedPlan($request);
        $plan = $promotion->apply(
            $request->user(), (int) $data['source_class_id'],
            isset($data['source_arm_id']) ? (int) $data['source_arm_id'] : null,
            $this->placements($data),
        );

        return redirect()->route('academics.promotion.index', [
            'section' => $data['section_id'] ?? null,
            'source' => $data['source_class_id'],
            'arm' => $data['source_arm_id'] ?? null,
        ])->with('promotion_report', [
            'moved' => $plan['moved'], 'stayed' => $plan['stayed'], 'skipped' => $plan['skipped'],
            'final_status' => $plan['final_status'],
        ]);
    }

    public function moveOne(Request $request, PromotionService $promotion): RedirectResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'destination_class_id' => ['required', 'integer'],
            'destination_arm_id' => ['required', 'integer'],
        ]);
        $promotion->moveOne(
            $request->user(), (int) $data['student_id'],
            (int) $data['destination_class_id'], (int) $data['destination_arm_id'],
        );

        return back()->with('status', 'Student moved. Past results and attendance were unchanged.');
    }

    private function pageData(Request $request, PromotionCatalog $catalog, PromotionService $promotion): array
    {
        $actor = $request->user();
        $canPromote = $actor->can('manage_promotions');
        $sections = $canPromote ? $catalog->sectionsFor($actor) : collect();
        $incomingSections = $canPromote ? app(IncomingAdmissionService::class)->sectionsFor($actor) : collect();
        $hasSection = $actor->can('view_all_sections') || $sections->isNotEmpty();
        $waiting = $canPromote ? $catalog->waitingForArrangement() : collect();
        $selectedSection = null;
        $classes = collect();
        $source = null;
        $students = null;
        $nextStep = collect();
        $suggestions = collect();

        if ($canPromote && $hasSection && $waiting->isEmpty()) {
            $selectedSection = $actor->can('view_all_sections')
                ? ($request->integer('section') ?: $sections->first()?->id)
                : $sections->first()?->id;
            if ($selectedSection) {
                $catalog->assertSection($actor, $selectedSection);
                $classes = $catalog->classesForSection($actor, $selectedSection);
                $source = $classes->firstWhere('id', $request->integer('source')) ?? $classes->first();
            }
            if ($source) {
                $armId = $request->integer('arm') ?: null;
                $armIds = $catalog->sourceArmIds($source, $armId);
                $students = app(StudentDirectory::class)->activeForArms($armIds, $armId);
                $nextStep = $catalog->nextStep($source);
                $suggestions = $students->getCollection()->mapWithKeys(fn ($student) => [
                    $student->id => $promotion->suggestion($student, $source),
                ]);
            }
        }

        return compact(
            'canPromote', 'sections', 'hasSection', 'waiting', 'selectedSection',
            'classes', 'source', 'students', 'nextStep', 'suggestions',
        ) + [
            'canViewAll' => $actor->can('view_all_sections'),
            'showIncomingTab' => $actor->can('view_all_sections') || $incomingSections->isNotEmpty(),
            'incomingSectionId' => $incomingSections->first()?->id,
            'report' => $request->session()->get('promotion_report'),
            'plan' => null,
            'submittedPlacements' => [],
        ];
    }

    private function validatedPlan(Request $request): array
    {
        return $request->validate([
            'section_id' => ['nullable', 'integer'],
            'source_class_id' => ['required', 'integer'],
            'source_arm_id' => ['nullable', 'integer'],
            'students' => ['required', 'array', 'min:1'],
            'students.*' => ['integer'],
            'class' => ['nullable', 'array'],
            'class.*' => ['nullable', 'integer'],
            'arm' => ['nullable', 'array'],
            'arm.*' => ['nullable', 'integer'],
            'stay' => ['nullable', 'array'],
            'stay.*' => [Rule::in(['1', 1, true])],
        ]);
    }

    private function placements(array $data): array
    {
        return collect($data['students'])->mapWithKeys(function ($studentId) use ($data): array {
            $id = (int) $studentId;

            return [$id => [
                'class_id' => $data['class'][$id] ?? null,
                'arm_id' => $data['arm'][$id] ?? null,
                'stay' => isset($data['stay'][$id]),
            ]];
        })->all();
    }
}
