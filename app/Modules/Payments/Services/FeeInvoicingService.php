<?php

namespace App\Modules\Payments\Services;

use App\Modules\Academics\Models\AcademicTerm;
use App\Modules\Academics\Services\AcademicCalendar;
use App\Modules\Admissions\Models\Student;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Models\FeeScheduleLine;
use App\Modules\Payments\Models\Invoice;
use App\Modules\Payments\Models\InvoiceGenerationRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeInvoicingService
{
    private array $scheduleCache = [];

    public function scheduleCountForLevel(int $levelId): int
    {
        return FeeScheduleLine::where('academic_level_id', $levelId)->count();
    }

    public function usageForTerm(int $termId): array
    {
        return [
            'schedule_lines' => FeeScheduleLine::where('term_id', $termId)->count(),
            'invoices' => Invoice::where('term_id', $termId)->count(),
            'generation_runs' => InvoiceGenerationRun::where('term_id', $termId)->count(),
        ];
    }

    public function previewScheduleCopy(int $sourceTermId, int $targetTermId, string $scopeMode, ?string $scopeType = null, ?int $scopeId = null): array
    {
        $source = AcademicTerm::findOrFail($sourceTermId);
        $target = AcademicTerm::findOrFail($targetTermId);
        if (! $source->start_date->lt($target->start_date)) {
            throw ValidationException::withMessages(['source_term_id' => 'Choose a term earlier than the target term.']);
        }
        if (! in_array($scopeMode, ['all', 'one'], true)) {
            throw ValidationException::withMessages(['scope_mode' => 'Choose every scope or one scope.']);
        }

        $sourceLines = FeeScheduleLine::with('feeItem')->where('term_id', $sourceTermId);
        if ($scopeMode === 'one') {
            $column = $this->scopeColumn($scopeType);
            if (! $scopeId) {
                throw ValidationException::withMessages(['scope_id' => 'Choose the scope to copy.']);
            }
            $sourceLines->where('scope_type', $scopeType)->where($column, $scopeId);
        }

        $copyable = collect();
        $existing = 0;
        $missing = 0;
        foreach ($sourceLines->get() as $line) {
            if (! $this->scheduleScopeExists($line)) {
                $missing++;

                continue;
            }
            $duplicate = FeeScheduleLine::where('term_id', $targetTermId)->where('fee_item_id', $line->fee_item_id)
                ->where('academic_level_id', $line->academic_level_id)->where('class_id', $line->class_id)
                ->where('department_id', $line->department_id)->exists();
            if ($duplicate) {
                $existing++;

                continue;
            }
            $copyable->push($line);
        }

        return [
            'source_term_id' => $sourceTermId, 'target_term_id' => $targetTermId,
            'scope_mode' => $scopeMode, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
            'source_lines' => $sourceLines->count(), 'copy_count' => $copyable->count(),
            'existing_count' => $existing, 'missing_scope_count' => $missing, 'lines' => $copyable,
        ];
    }

    public function applyScheduleCopy(int $sourceTermId, int $targetTermId, string $scopeMode, ?string $scopeType, ?int $scopeId, User $actor): array
    {
        return DB::transaction(function () use ($sourceTermId, $targetTermId, $scopeMode, $scopeType, $scopeId, $actor): array {
            $plan = $this->previewScheduleCopy($sourceTermId, $targetTermId, $scopeMode, $scopeType, $scopeId);
            $copied = 0;
            foreach ($plan['lines'] as $line) {
                $attributes = [
                    'term_id' => $targetTermId, 'academic_level_id' => $line->academic_level_id,
                    'class_id' => $line->class_id, 'department_id' => $line->department_id,
                    'fee_item_id' => $line->fee_item_id,
                ];
                $created = FeeScheduleLine::firstOrCreate($attributes, [
                    'scope_type' => $line->scope_type, 'amount_kobo' => $line->amount_kobo,
                    'is_required' => $line->is_required,
                ]);
                $copied += $created->wasRecentlyCreated ? 1 : 0;
            }
            app(AuditRecorder::class)->record($actor, 'fee_schedule_copied', AcademicTerm::find($targetTermId), [
                'source_term_id' => $sourceTermId, 'target_term_id' => $targetTermId,
                'scope_mode' => $scopeMode, 'scope_type' => $scopeType, 'scope_id' => $scopeId,
                'copied_lines' => $copied, 'skipped_existing' => $plan['existing_count'],
                'skipped_missing_scope' => $plan['missing_scope_count'],
            ]);

            return [...$plan, 'copied' => $copied];
        });
    }

    public function linesForStudent(Student $student, int $termId): Collection
    {
        $student->loadMissing('classArm.schoolClass');
        $class = $student->classArm?->schoolClass;
        if (! $class) {
            return collect();
        }

        $lines = ($this->scheduleCache[$termId] ??= FeeScheduleLine::with('feeItem')->where('term_id', $termId)->get())
            ->filter(fn (FeeScheduleLine $line) => $line->class_id === $class->id
                || $line->academic_level_id === $class->academic_level_id
                || ($class->department_id && $line->department_id === $class->department_id));

        $weight = ['level' => 1, 'department' => 2, 'class' => 3];

        return $lines->sortByDesc(fn (FeeScheduleLine $line) => $weight[$line->scope_type] ?? 0)
            ->unique('fee_item_id')->values();
    }

    public function preview(int $termId, string $scopeType, ?int $scopeId): array
    {
        $students = $this->students($scopeType, $scopeId)->get();
        $existing = Invoice::where('kind', Invoice::KIND_TERM_FEE)->where('term_id', $termId)
            ->whereIn('student_id', $students->pluck('id'))->pluck('student_id')->mapWithKeys(fn ($id) => [(int) $id => true]);
        $eligible = collect();
        $noClass = 0;
        $noSchedule = 0;
        $already = 0;

        foreach ($students as $student) {
            if (isset($existing[$student->id])) {
                $already++;

                continue;
            }
            if (! $student->classArm) {
                $noClass++;

                continue;
            }
            $lines = $this->linesForStudent($student, $termId)->where('is_required', true)->values();
            if ($lines->isEmpty()) {
                $noSchedule++;

                continue;
            }
            $eligible->push(['student' => $student, 'lines' => $lines, 'total_kobo' => (int) $lines->sum('amount_kobo')]);
        }

        return [
            'term_id' => $termId,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'students_in_scope' => $students->count(),
            'will_create' => $eligible->count(),
            'already_invoiced' => $already,
            'no_class' => $noClass,
            'no_schedule' => $noSchedule,
            'skipped' => $already + $noClass + $noSchedule,
            'rows' => $eligible,
        ];
    }

    public function apply(int $termId, string $scopeType, ?int $scopeId, string $dueDate, User $actor): array
    {
        $plan = $this->preview($termId, $scopeType, $scopeId);
        $created = 0;

        $plan['rows']->chunk(100)->each(function (Collection $chunk) use ($termId, $dueDate, $actor, &$created): void {
            DB::transaction(function () use ($chunk, $termId, $dueDate, $actor, &$created): void {
                foreach ($chunk as $row) {
                    try {
                        $this->createInvoice($row['student'], $termId, $dueDate, $row['lines'], 'bulk', $actor);
                        $created++;
                    } catch (QueryException $exception) {
                        if (! Invoice::where('kind', Invoice::KIND_TERM_FEE)->where('term_id', $termId)
                            ->where('student_id', $row['student']->id)->exists()) {
                            throw $exception;
                        }
                    }
                }
            });
        });

        InvoiceGenerationRun::create([
            'term_id' => $termId, 'scope_type' => $scopeType, 'scope_id' => $scopeId, 'due_date' => $dueDate,
            'students_in_scope' => $plan['students_in_scope'], 'created_count' => $created,
            'skipped_count' => $plan['students_in_scope'] - $created, 'no_class_count' => $plan['no_class'],
            'no_schedule_count' => $plan['no_schedule'], 'created_by' => $actor->id,
        ]);

        return [...$plan, 'created' => $created, 'skipped' => $plan['students_in_scope'] - $created];
    }

    public function invoiceStudent(Student $student, int $termId, string $dueDate, array $extraIds, bool $extrasSeparate, User $actor, string $source = 'single'): array
    {
        if (Invoice::where('kind', Invoice::KIND_TERM_FEE)->where('term_id', $termId)->where('student_id', $student->id)->exists()) {
            throw ValidationException::withMessages(['student' => 'This student already has a term invoice.']);
        }
        $all = $this->linesForStudent($student, $termId);
        $required = $all->where('is_required', true)->values();
        $extras = $all->where('is_required', false)->whereIn('id', array_map('intval', $extraIds))->values();
        if ($required->isEmpty()) {
            throw ValidationException::withMessages(['student' => 'This student has no required fee schedule.']);
        }

        return DB::transaction(function () use ($student, $termId, $dueDate, $required, $extras, $extrasSeparate, $actor, $source): array {
            $termLines = $extrasSeparate ? $required : $required->concat($extras);
            $termInvoice = $this->createInvoice($student, $termId, $dueDate, $termLines, $source, $actor);
            $extraInvoice = null;
            if ($extrasSeparate && $extras->isNotEmpty()) {
                $extraInvoice = $this->createInvoice($student, $termId, $dueDate, $extras, $source, $actor, Invoice::KIND_ONE_OFF, null);
            }

            return ['term_invoice' => $termInvoice, 'extra_invoice' => $extraInvoice];
        });
    }

    public function automaticallyInvoice(Student $student, User $actor): ?Invoice
    {
        $term = app(AcademicCalendar::class)->currentTerm();
        if (! $term || Invoice::where('kind', Invoice::KIND_TERM_FEE)->where('term_id', $term->id)->where('student_id', $student->id)->exists()) {
            return null;
        }
        $lines = $this->linesForStudent($student, $term->id)->where('is_required', true)->values();
        if ($lines->isEmpty()) {
            return null;
        }

        return $this->createInvoice($student, $term->id, $term->end_date->toDateString(), $lines, 'automatic', $actor);
    }

    private function createInvoice(Student $student, int $termId, string $dueDate, Collection $lines, string $source, ?User $actor, string $kind = Invoice::KIND_TERM_FEE, ?int $termFeeStudentId = -1): Invoice
    {
        $termFeeStudentId = $kind === Invoice::KIND_TERM_FEE ? $student->id : null;
        $invoice = Invoice::create([
            'student_id' => $student->id, 'term_fee_student_id' => $termFeeStudentId, 'term_id' => $termId,
            'purpose' => $kind === Invoice::KIND_TERM_FEE ? 'Tuition' : 'Fee extra', 'kind' => $kind,
            'creation_source' => $source, 'amount_kobo' => (int) $lines->sum('amount_kobo'),
            'due_date' => $dueDate, 'status' => 'Unpaid', 'created_by' => $actor?->id ?? $student->user_id,
        ]);
        foreach ($lines as $line) {
            $invoice->lines()->create([
                'fee_item_id' => $line->fee_item_id, 'description' => $line->feeItem->name,
                'amount_kobo' => $line->amount_kobo, 'is_required' => $line->is_required,
            ]);
        }

        return $invoice;
    }

    private function students(string $scopeType, ?int $scopeId): Builder
    {
        $query = Student::active()->with(['user', 'classArm.schoolClass.level.section', 'classArm.schoolClass.department'])->orderBy('id');
        if ($scopeType === 'section') {
            $query->whereHas('classArm.schoolClass.level', fn (Builder $q) => $q->where('section_id', $scopeId));
        } elseif ($scopeType === 'level') {
            $query->whereHas('classArm.schoolClass', fn (Builder $q) => $q->where('academic_level_id', $scopeId));
        } elseif ($scopeType !== 'school') {
            throw ValidationException::withMessages(['scope_type' => 'Choose the whole school, a section or a level.']);
        }

        return $query;
    }

    private function scopeColumn(?string $scopeType): string
    {
        return match ($scopeType) {
            'level' => 'academic_level_id', 'class' => 'class_id', 'department' => 'department_id',
            default => throw ValidationException::withMessages(['scope_type' => 'Choose a level, class or department scope.']),
        };
    }

    private function scheduleScopeExists(FeeScheduleLine $line): bool
    {
        return match ($line->scope_type) {
            'level' => $line->level()->exists(),
            'class' => $line->schoolClass()->exists(),
            'department' => $line->department()->exists(),
            default => false,
        };
    }
}
