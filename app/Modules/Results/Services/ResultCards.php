<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\ClassArmDirectory;
use App\Modules\Academics\Services\LearnerTerminology;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Services\ResultPaymentAccess;
use App\Modules\Results\Models\CommentRange;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultCardCommentAudit;
use App\Modules\Results\Models\ResultTemplate;
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
        throw new AuthorizationException('Only this arm’s form teacher may view its compiled report cards.');
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
        $resolver = app(ResultTemplateResolver::class);
        $templates = $results->mapWithKeys(fn (Result $result): array => [$result->id => $resolver->storedFor($result)]);
        $cardTemplate = $templates->first();
        $showSubjectRemarks = $templates->contains(fn (ResultTemplate $template): bool => (bool) $template->show_subject_remarks);
        $subjectRemarks = $results->mapWithKeys(function (Result $result) use ($resolver): array {
            $template = $resolver->storedFor($result);
            if (! $template->show_subject_remarks) {
                return [$result->id => null];
            }
            $band = app(GradeScale::class)->bandFor($template->grading_scheme_id, (float) $result->percentage);

            return [$result->id => $band?->remark];
        });
        $subjectLabels = app(ResultAcademicCatalog::class)->labels($results->pluck('subject_id')->all(), [$card->term_id]);
        $section = app(SectionAccess::class)->sectionForArm($card->class_arm_id);
        $defaults = TermDefaultComment::where('term_id', $card->term_id)
            ->where(function ($query) use ($section): void {
                $query->where('section_id', $section?->id)->orWhereNull('section_id');
            })->orderByRaw('section_id is null')->get()->unique('comment_type')->pluck('comment_text', 'comment_type');
        $average = $results->isEmpty() ? null : round((float) $results->avg('percentage'), 2);
        $overallTotal = $results->isEmpty() ? null : round((float) $results->sum('total_score'), 2);
        $principalComment = $this->effectiveComment($card->principal_comment, $section, 'Principal', $average, $defaults);
        $proprietorComment = $this->effectiveComment($card->proprietor_comment, $section, 'Proprietor', $average, $defaults);
        $proprietorTitle = SchoolSetting::query()->value('proprietor_comment_title') ?: "Proprietor's comment";

        $student = Student::with('classArm.schoolClass.level.section')->find($card->student_id);

        return [
            'card' => $card,
            'results' => $results,
            'showSubjectRemarks' => $showSubjectRemarks,
            'subjectRemarks' => $subjectRemarks,
            'studentName' => app(StudentDirectory::class)->names([$card->student_id])[$card->student_id] ?? 'Student #'.$card->student_id,
            'registrationNumber' => $student?->registration_number,
            'learnerTerm' => app(LearnerTerminology::class)->word($student),
            'learnersTerm' => app(LearnerTerminology::class)->word($student, true),
            'subjects' => $subjectLabels['subjects'],
            'term' => $subjectLabels['terms'][$card->term_id] ?? 'Term #'.$card->term_id,
            'principalComment' => $principalComment,
            'proprietorComment' => $proprietorComment,
            'proprietorCommentTitle' => $proprietorTitle,
            'overallTotal' => $overallTotal,
            'average' => $average,
            'showOverallTotal' => (bool) ($cardTemplate?->show_overall_total ?? true),
            'showAverage' => (bool) ($cardTemplate?->show_average ?? true),
            'showPosition' => (bool) ($cardTemplate?->show_position ?? true),
            'sectionId' => $section?->id,
            'headCommentLabel' => $section?->head_title ?: 'Principal',
            'canSetDefault' => $section !== null || ! Section::exists(),
        ];
    }

    public function setCommentMode(Section $section, string $type, string $mode, User $actor): void
    {
        $this->authorizeCommentConfiguration($section, $type, $actor);
        if (! in_array($mode, ['single', 'range'], true)) {
            throw ValidationException::withMessages(['mode' => 'Choose single comment or average ranges.']);
        }
        $column = $type === 'Principal' ? 'principal_comment_mode' : 'proprietor_comment_mode';
        $before = $section->$column;
        $section->update([$column => $mode]);
        app(AuditRecorder::class)->record($actor, 'report_card_comment_mode_updated', $section, [
            'comment_type' => $type, 'before' => $before, 'after' => $mode,
        ]);
    }

    public function saveCommentRange(Section $section, string $type, int $min, int $max, string $text, User $actor, ?CommentRange $range = null): CommentRange
    {
        $this->authorizeCommentConfiguration($section, $type, $actor);
        if ($min < 0 || $max > 100 || $min > $max) {
            throw ValidationException::withMessages(['min_average' => 'Use whole-number averages from 0 to 100, with From not above To.']);
        }
        if ($range && (int) $range->section_id !== (int) $section->id) {
            throw new AuthorizationException('That comment range belongs to another Section.');
        }
        $overlap = CommentRange::where('section_id', $section->id)->where('comment_type', $type)
            ->when($range, fn ($query) => $query->whereKeyNot($range->id))
            ->where('min_average', '<=', $max)->where('max_average', '>=', $min)->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['min_average' => 'This average range overlaps an existing range.']);
        }
        $before = $range?->only(['min_average', 'max_average', 'comment_text']);
        $range ??= new CommentRange;
        $range->fill(['section_id' => $section->id, 'comment_type' => $type, 'min_average' => $min, 'max_average' => $max, 'comment_text' => trim($text)]);
        $range->save();
        app(AuditRecorder::class)->record($actor, 'report_card_comment_range_saved', $range, [
            'comment_type' => $type, 'section_id' => $section->id, 'before' => $before,
            'after' => $range->only(['min_average', 'max_average', 'comment_text']),
        ]);

        return $range;
    }

    public function deleteCommentRange(CommentRange $range, User $actor): void
    {
        $section = Section::findOrFail($range->section_id);
        $this->authorizeCommentConfiguration($section, $range->comment_type, $actor);
        app(AuditRecorder::class)->record($actor, 'report_card_comment_range_deleted', $range, [
            'comment_type' => $range->comment_type, 'section_id' => $section->id,
            'before' => $range->only(['min_average', 'max_average', 'comment_text']),
        ]);
        $range->delete();
    }

    public function setProprietorTitle(string $title, User $actor): void
    {
        Gate::forUser($actor)->authorize('manage_result_settings');
        $settings = SchoolSetting::query()->firstOrCreate([]);
        $before = $settings->proprietor_comment_title;
        $settings->update(['proprietor_comment_title' => trim($title)]);
        app(AuditRecorder::class)->record($actor, 'proprietor_comment_title_updated', $settings, [
            'before' => $before, 'after' => trim($title),
        ]);
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

    private function authorizeCommentConfiguration(Section $section, string $type, User $actor): void
    {
        if (! in_array($type, ['Principal', 'Proprietor'], true)) {
            throw ValidationException::withMessages(['comment_type' => 'Choose Principal or Proprietor.']);
        }
        Gate::forUser($actor)->authorize('manage_result_configuration');
        app(ResultConfigurationAccess::class)->assertSection($actor, $section->id);
        if ($type === 'Proprietor') {
            Gate::forUser($actor)->authorize('add_proprietor_comment');
        }
    }

    private function effectiveComment(?string $personalized, ?Section $section, string $type, ?float $average, $defaults): string
    {
        if ($personalized !== null && trim($personalized) !== '') {
            return $personalized;
        }
        $mode = $type === 'Principal' ? $section?->principal_comment_mode : $section?->proprietor_comment_mode;
        if ($mode === 'range') {
            if (! $section || $average === null) {
                return '';
            }
            $rounded = (int) round($average, 0, PHP_ROUND_HALF_UP);
            $range = CommentRange::where('section_id', $section->id)->where('comment_type', $type)
                ->where('min_average', '<=', $rounded)->where('max_average', '>=', $rounded)
                ->orderBy('min_average')->first();

            return $range?->comment_text ?? '';
        }

        return (string) ($defaults[$type] ?? '');
    }

    private function audit(?ResultCard $card, int $termId, ?int $sectionId, User $actor, string $type, string $scope, ?string $text): void
    {
        ResultCardCommentAudit::create([
            'result_card_id' => $card?->id, 'term_id' => $termId, 'section_id' => $sectionId, 'user_id' => $actor->id,
            'comment_type' => $type, 'scope' => $scope, 'comment_text' => $text, 'created_at' => now(),
        ]);
    }
}
