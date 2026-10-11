<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Core\Models\User;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Results\Models\GradeBand;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultCardCommentAudit;
use App\Modules\Results\Models\TermDefaultComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResultCards
{
    public function authorizeView(ResultCard $card, User $actor): void
    {
        if (Gate::forUser($actor)->allows('manage_result_settings')
            || Gate::forUser($actor)->allows('approve_results')
            || Gate::forUser($actor)->allows('add_principal_comment')
            || Gate::forUser($actor)->allows('add_proprietor_comment')) {
            app(SectionAccess::class)->assertClassArm($actor, $card->class_arm_id);

            return;
        }
        if (Gate::forUser($actor)->allows('add_class_teacher_comment', $card)) {
            return;
        }
        Gate::forUser($actor)->authorize('enter_results');
        $classId = app(ResultAcademicCatalog::class)->placement($card->class_arm_id)['class_id'];
        app(ResultTeachingAssignments::class)->assertMayEnterClass($actor, $classId);
    }

    public function compile(Result $result): ResultCard
    {
        if ($result->status !== 'Approved') {
            throw ValidationException::withMessages(['result' => 'Only approved scores can be compiled.']);
        }

        $card = ResultCard::firstOrCreate(
            ['student_id' => $result->student_id, 'term_id' => $result->term_id],
            ['class_arm_id' => $result->class_arm_id, 'compiled_at' => now()],
        );
        if (! $card->wasRecentlyCreated) {
            $card->update(['compiled_at' => now()]);
        }

        return $card;
    }

    public function setDefault(int $termId, ?int $sectionId, string $type, string $text, User $actor): void
    {
        $this->authorizeComment($type, $actor);
        if ($sectionId !== null) {
            app(SectionAccess::class)->assertSection($actor, $sectionId);
        } elseif (Section::exists()) {
            throw ValidationException::withMessages(['section_id' => 'Choose the section for this default comment.']);
        }
        app(ResultAcademicCatalog::class)->assertTerm($termId);
        DB::transaction(function () use ($termId, $sectionId, $type, $text, $actor): void {
            TermDefaultComment::updateOrCreate(
                ['term_id' => $termId, 'section_id' => $sectionId, 'comment_type' => $type],
                ['comment_text' => $text],
            );
            $this->audit(null, $termId, $sectionId, $actor, $type, 'Default', $text);
        });
    }

    public function personalize(ResultCard $card, string $type, ?string $text, User $actor): void
    {
        $this->authorizeComment($type, $actor);
        $column = $type === 'Principal' ? 'principal_comment' : 'proprietor_comment';
        DB::transaction(function () use ($card, $column, $text, $actor, $type): void {
            $card->update([$column => $text === null || trim($text) === '' ? null : trim($text)]);
            $sectionId = app(SectionAccess::class)->sectionForArm($card->class_arm_id)?->id;
            $this->audit($card, $card->term_id, $sectionId, $actor, $type, 'Personalized', $card->$column);
        });
    }

    public function teacherComment(ResultCard $card, ?string $text, User $actor): void
    {
        Gate::forUser($actor)->authorize('add_class_teacher_comment', $card);
        DB::transaction(function () use ($card, $text, $actor): void {
            $card->update(['class_teacher_comment' => $text === null || trim($text) === '' ? null : trim($text)]);
            $sectionId = app(SectionAccess::class)->sectionForArm($card->class_arm_id)?->id;
            $this->audit($card, $card->term_id, $sectionId, $actor, 'Class Teacher', 'Personalized', $card->class_teacher_comment);
        });
    }

    public function detail(ResultCard $card): array
    {
        $results = Result::with(['items.component', 'template'])
            ->where('student_id', $card->student_id)->where('term_id', $card->term_id)
            ->where('status', 'Approved')->orderBy('subject_id')->get();
        $showSubjectRemarks = $results->contains(fn (Result $result): bool => (bool) $result->template?->show_subject_remarks);
        $schemeIds = $results->filter(fn (Result $result): bool => (bool) $result->template?->show_subject_remarks)
            ->pluck('template.grading_scheme_id')->unique()->values()->all();
        $bands = GradeBand::whereIn('grading_scheme_id', $schemeIds)->get();
        $subjectRemarks = $results->mapWithKeys(function (Result $result) use ($bands): array {
            if (! $result->template?->show_subject_remarks) {
                return [$result->id => null];
            }
            $band = $bands->first(fn (GradeBand $band): bool => $band->grading_scheme_id === $result->template->grading_scheme_id
                && $band->grade === $result->grade
                && (float) $band->min_score <= (float) $result->percentage
                && (float) $band->max_score >= (float) $result->percentage);

            return [$result->id => $band?->remark];
        });
        $subjectLabels = app(ResultAcademicCatalog::class)->labels($results->pluck('subject_id')->all(), [$card->term_id]);
        $section = app(SectionAccess::class)->sectionForArm($card->class_arm_id);
        $defaults = TermDefaultComment::where('term_id', $card->term_id)
            ->where(function ($query) use ($section): void {
                $query->where('section_id', $section?->id)->orWhereNull('section_id');
            })->orderByRaw('section_id is null')->get()->unique('comment_type')->pluck('comment_text', 'comment_type');

        return [
            'card' => $card,
            'results' => $results,
            'showSubjectRemarks' => $showSubjectRemarks,
            'subjectRemarks' => $subjectRemarks,
            'studentName' => app(StudentDirectory::class)->names([$card->student_id])[$card->student_id] ?? 'Student #'.$card->student_id,
            'subjects' => $subjectLabels['subjects'],
            'term' => $subjectLabels['terms'][$card->term_id] ?? 'Term #'.$card->term_id,
            'principalComment' => $card->principal_comment ?? ($defaults['Principal'] ?? ''),
            'proprietorComment' => $card->proprietor_comment ?? ($defaults['Proprietor'] ?? ''),
            'sectionId' => $section?->id,
            'headCommentLabel' => $section?->head_title ?: 'Principal',
            'canSetDefault' => $section !== null || ! Section::exists(),
        ];
    }

    public function broadsheet(int $armId, int $termId): array
    {
        $results = Result::where('class_arm_id', $armId)->where('term_id', $termId)
            ->where('status', 'Approved')->get();
        $groups = $results->groupBy('student_id');
        $names = app(StudentDirectory::class)->names($groups->keys()->all());
        $rows = $groups->map(function ($items, $studentId) use ($names): array {
            return [
                'student_id' => $studentId,
                'name' => $names[$studentId] ?? 'Student #'.$studentId,
                'subjects' => $items->count(),
                'average' => round($items->avg('percentage'), 2),
            ];
        })->sortByDesc('average')->values()->all();
        $rank = 0;
        $prior = null;
        foreach ($rows as $index => &$row) {
            if ($prior !== $row['average']) {
                $rank = $index + 1;
            }
            $row['position'] = $rank;
            $prior = $row['average'];
        }

        return $rows;
    }

    public function portalSummary(int $studentId, User $viewer): array
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);
        $cards = ResultCard::where('student_id', $studentId)->latest('compiled_at')->limit(5)->get();
        $terms = app(ResultAcademicCatalog::class)->labels([], $cards->pluck('term_id')->all())['terms'];

        return $cards->map(function (ResultCard $card) use ($viewer, $terms): array {
            try {
                app(ResultPaymentAccess::class)->authorize($card, $viewer);
                $available = true;
            } catch (AuthorizationException) {
                $available = false;
            }

            return [
                'id' => $card->id,
                'term' => $terms[$card->term_id] ?? 'Term #'.$card->term_id,
                'compiled_at' => $card->compiled_at,
                'available' => $available,
            ];
        })->all();
    }

    public function portalList(int $studentId, User $viewer)
    {
        app(ResultPaymentAccess::class)->authorizeOwnership($studentId, $viewer);
        $page = ResultCard::where('student_id', $studentId)
            ->latest('compiled_at')->paginate(20)->withQueryString();
        $termNames = app(ResultAcademicCatalog::class)->labels([], $page->pluck('term_id')->all())['terms'];
        $classes = app(ClassArmDirectory::class)->labels($page->pluck('class_arm_id')->all());
        $unpaid = app(ResultPaymentAccess::class)->unpaidForTerms($studentId, $page->pluck('term_id')->all());

        return $page->through(function (ResultCard $card) use ($viewer, $termNames, $classes, $unpaid): array {
            try {
                app(ResultPaymentAccess::class)->authorize($card, $viewer);
                $available = true;
            } catch (AuthorizationException) {
                $available = false;
            }

            return [
                'id' => $card->id,
                'term' => $termNames[$card->term_id] ?? 'Term #'.$card->term_id,
                'class' => $classes[$card->class_arm_id] ?? 'Class not assigned',
                'compiled_at' => $card->compiled_at,
                'available' => $available,
                'invoice' => $available ? null : $unpaid[$card->term_id] ?? null,
            ];
        });
    }

    private function authorizeComment(string $type, User $actor): void
    {
        if (! in_array($type, ['Principal', 'Proprietor'], true)) {
            throw ValidationException::withMessages(['comment_type' => 'Choose Principal or Proprietor.']);
        }
        Gate::forUser($actor)->authorize($type === 'Principal' ? 'add_principal_comment' : 'add_proprietor_comment');
    }

    private function audit(?ResultCard $card, int $termId, ?int $sectionId, User $actor, string $type, string $scope, ?string $text): void
    {
        ResultCardCommentAudit::create([
            'result_card_id' => $card?->id, 'term_id' => $termId, 'section_id' => $sectionId, 'user_id' => $actor->id,
            'comment_type' => $type, 'scope' => $scope, 'comment_text' => $text, 'created_at' => now(),
        ]);
    }
}
