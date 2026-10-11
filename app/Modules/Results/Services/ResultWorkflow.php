<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Subject;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Notifications\Services\InAppNotifications;
use App\Modules\Results\Models\AssessmentComponent;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultAuditLog;
use App\Modules\Results\Models\ResultItem;
use App\Modules\Results\Models\ResultTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResultWorkflow
{
    public function dashboardApprovals(User $actor, int $limit = 5): array
    {
        $query = Result::whereIn('status', ['Submitted', 'Rejected']);
        $results = app(SectionAccess::class)->scopeResults($query, $actor)->latest('updated_at')->limit($limit)->get();
        $arms = ClassArm::with('schoolClass')->whereIn('id', $results->pluck('class_arm_id'))->get()->keyBy('id');
        $subjects = Subject::whereIn('id', $results->pluck('subject_id'))->pluck('name', 'id');
        $teachers = User::whereIn('id', $results->pluck('submitted_by')->filter())->pluck('name', 'id');

        return $results->map(fn ($result) => [
            'url' => route('results.show', $result),
            'class' => trim(($arms->get($result->class_arm_id)?->schoolClass?->name ?? 'Class').' '.($arms->get($result->class_arm_id)?->name ?? '')),
            'subject' => $subjects[$result->subject_id] ?? 'Subject #'.$result->subject_id,
            'teacher' => $teachers[$result->submitted_by] ?? 'Unassigned',
            'status' => $result->status,
        ])->all();
    }

    public function dashboardTeacherPending(User $actor): int
    {
        return Result::where('submitted_by', $actor->id)->where('status', 'Submitted')->count();
    }

    public function recordComponentScore(int $studentId, int $subjectId, int $termId, int $componentId, float $score, User $actor): Result
    {
        Gate::forUser($actor)->authorize('manage_cbt');
        $component = AssessmentComponent::findOrFail($componentId);
        if ($score < 0 || $score > (float) $component->max_score) {
            throw ValidationException::withMessages(['score' => 'Converted CBT score is outside this component’s maximum.']);
        }

        return $this->saveDraft($studentId, $subjectId, $termId, [$componentId => $score], $actor);
    }

    public function templateForStudent(int $studentId): ResultTemplate
    {
        $armId = app(StudentDirectory::class)->classArmId($studentId);
        $placement = app(ResultAcademicCatalog::class)->placement($armId);

        return $this->templateFor($placement['class_id'], $placement['academic_level_id']);
    }

    public function saveDraft(int $studentId, int $subjectId, int $termId, array $scores, User $actor): Result
    {
        Gate::forUser($actor)->authorize('enter_results');

        return DB::transaction(function () use ($studentId, $subjectId, $termId, $scores, $actor): Result {
            $result = Result::where('student_id', $studentId)->where('subject_id', $subjectId)->where('term_id', $termId)->lockForUpdate()->first();
            if ($result && in_array($result->status, ['Submitted', 'Approved'], true)) {
                throw ValidationException::withMessages(['result' => 'A submitted or approved result cannot be edited. Ask an approver to reject a submitted result first.']);
            }

            $armId = $result?->class_arm_id ?? app(StudentDirectory::class)->classArmId($studentId);
            if (Gate::forUser($actor)->allows('manage_result_settings')) {
                app(SectionAccess::class)->assertClassArm($actor, $armId);
            }
            $academic = app(ResultAcademicCatalog::class)->resultContext($armId, $subjectId, $termId);
            app(ResultTeachingAssignments::class)->assertMayEnter($actor, $academic['class_id'], $subjectId);
            $template = $result
                ? $this->templateById($result->result_template_id)
                : $this->templateFor($academic['class_id'], $academic['academic_level_id']);
            $maxima = $this->maxima($template);
            if ($scores === []) {
                throw ValidationException::withMessages(['scores' => 'Enter at least one component score.']);
            }

            foreach ($scores as $componentId => $score) {
                $id = (int) $componentId;
                if (! isset($maxima[$id]) || ! is_numeric($score) || (float) $score < 0 || (float) $score > $maxima[$id]) {
                    throw ValidationException::withMessages(['scores' => 'Each score must be between zero and its component maximum.']);
                }
            }

            $new = $result === null;
            if ($new) {
                $result = Result::create(['student_id' => $studentId, 'class_arm_id' => $armId, 'subject_id' => $subjectId, 'term_id' => $termId, 'result_template_id' => $template->id, 'status' => 'Draft']);
            } elseif ($result->status === 'Rejected') {
                $result->update(['status' => 'Draft', 'rejection_reason' => null]);
            }

            foreach ($scores as $componentId => $score) {
                ResultItem::updateOrCreate(['result_id' => $result->id, 'assessment_component_id' => (int) $componentId], ['score' => round((float) $score, 2)]);
            }
            $total = (float) ResultItem::where('result_id', $result->id)->sum('score');
            $result->update(['total_score' => $total, 'percentage' => round($total / array_sum($maxima) * 100, 2), 'grade' => null, 'remark' => null]);
            $this->audit($result, $new ? 'Upload' : 'Edit', $actor, 'Draft scores saved');

            return $result;
        });
    }

    public function submit(Result $result, User $actor): Result
    {
        Gate::forUser($actor)->authorize('enter_results');

        return DB::transaction(function () use ($result, $actor): Result {
            $result = $this->locked($result);
            $academic = app(ResultAcademicCatalog::class)->placement($result->class_arm_id);
            app(ResultTeachingAssignments::class)->assertMayEnter($actor, $academic['class_id'], $result->subject_id);
            if ($result->status !== 'Draft' && $result->status !== 'Rejected') {
                throw ValidationException::withMessages(['result' => 'Only a draft or rejected result can be submitted.']);
            }
            $template = $this->templateById($result->result_template_id);
            $maxima = $this->maxima($template);
            $items = ResultItem::where('result_id', $result->id)->get();
            if ($items->count() !== count($maxima) || $items->pluck('assessment_component_id')->diff(array_keys($maxima))->isNotEmpty()) {
                throw ValidationException::withMessages(['scores' => 'Enter every component score before submitting.']);
            }
            $total = (float) $items->sum('score');
            $percentage = round($total / array_sum($maxima) * 100, 2);
            $band = GradeBand::where('grading_scheme_id', $template->grading_scheme_id)
                ->where('min_score', '<=', $percentage)->where('max_score', '>=', $percentage)->first();
            if (! $band) {
                throw ValidationException::withMessages(['grade' => 'The grading scheme has no band for this score. Complete the grade bands first.']);
            }

            $result->update(['status' => 'Submitted', 'submitted_by' => $actor->id, 'rejection_reason' => null, 'total_score' => $total, 'percentage' => $percentage, 'grade' => $band->grade, 'remark' => $band->remark]);
            $this->audit($result, 'Submit', $actor, 'Submitted for approval');

            return $result;
        });
    }

    public function approve(Result $result, User $actor): Result
    {
        Gate::forUser($actor)->authorize('approve_results');
        app(SectionAccess::class)->assertClassArm($actor, $result->class_arm_id);

        return DB::transaction(function () use ($result, $actor): Result {
            $result = $this->locked($result);
            if ($result->status !== 'Submitted') {
                throw ValidationException::withMessages(['result' => 'Only a submitted result can be approved.']);
            }
            $result->update(['status' => 'Approved', 'approved_by' => $actor->id, 'locked_at' => now()]);
            $this->audit($result, 'Approve', $actor, 'Approved and locked');
            app(ResultCards::class)->compile($result);
            app(InAppNotifications::class)->sendForStudent(
                $result->student_id,
                app(StudentDirectory::class)->userIds([$result->student_id])[0],
                app(StudentDirectory::class)->names([$result->student_id])[$result->student_id],
                'Result updated',
                'An approved subject result is now available on your portal, subject to the school payment rule.',
            );

            return $result;
        });
    }

    public function reject(Result $result, User $actor, string $reason): Result
    {
        Gate::forUser($actor)->authorize('approve_results');
        app(SectionAccess::class)->assertClassArm($actor, $result->class_arm_id);
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['rejection_reason' => 'Explain why this result is being rejected.']);
        }

        return DB::transaction(function () use ($result, $actor, $reason): Result {
            $result = $this->locked($result);
            if ($result->status !== 'Submitted') {
                throw ValidationException::withMessages(['result' => 'Only a submitted result can be rejected.']);
            }
            $result->update(['status' => 'Rejected', 'approved_by' => null, 'rejection_reason' => trim($reason)]);
            $this->audit($result, 'Reject', $actor, trim($reason));

            return $result;
        });
    }

    private function locked(Result $result): Result
    {
        return Result::whereKey($result->id)->lockForUpdate()->firstOrFail();
    }

    private function templateById(int $id): ResultTemplate
    {
        return ResultTemplate::with('components.component')->findOrFail($id);
    }

    private function templateFor(int $classId, int $levelId): ResultTemplate
    {
        $template = ResultTemplate::with('components.component')->where('scope_key', 'class:'.$classId)->first()
            ?? ResultTemplate::with('components.component')->where('scope_key', 'level:'.$levelId)->first();
        if (! $template) {
            throw ValidationException::withMessages(['result_template' => 'Configure a result template for this class or academic level first.']);
        }

        return $template;
    }

    private function maxima(ResultTemplate $template): array
    {
        $maxima = [];
        foreach ($template->components as $entry) {
            $maxima[$entry->assessment_component_id] = (float) $entry->component->max_score;
        }
        if ($maxima === [] || array_sum($maxima) <= 0) {
            throw ValidationException::withMessages(['result_template' => 'The result template needs at least one valid component.']);
        }

        return $maxima;
    }

    private function audit(Result $result, string $action, User $actor, string $note): void
    {
        ResultAuditLog::create(['result_id' => $result->id, 'action' => $action, 'user_id' => $actor->id, 'note' => $note, 'created_at' => now()]);
    }
}
