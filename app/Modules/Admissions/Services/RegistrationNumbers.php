<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Models\Section;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\RegistrationNumberSequence;
use App\Modules\Admissions\Models\Student;
use App\Modules\Admissions\Models\StudentRegistrationNumberHistory;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationNumbers
{
    public const KEEP_DIGITS_CHANGE_PREFIX = 'keep_digits_change_prefix';

    public const NEW_PREFIX_SEQUENCE = 'new_prefix_sequence';

    public const KEEP_WHOLE_NUMBER = 'keep_whole_number';

    public function settings(): SchoolSetting
    {
        return SchoolSetting::firstOrCreate(
            ['school_id' => app(TenantContext::class)->id()],
            ['registration_prefix' => 'REG', 'registration_number_length' => 4,
                'registration_include_year' => false, 'registration_move_policy' => self::KEEP_DIGITS_CHANGE_PREFIX],
        );
    }

    public function prefixForArm(int $armId): string
    {
        $arm = ClassArm::with('schoolClass.level.section')->findOrFail($armId);
        $level = $arm->schoolClass?->level;

        return $this->normalizePrefix($level?->registration_prefix
            ?: $level?->section?->registration_prefix
            ?: $this->settings()->registration_prefix);
    }

    public function preview(int $armId): string
    {
        $prefix = $this->prefixForArm($armId);
        $settings = $this->settings();
        $key = $this->sequenceKey($prefix, $settings->registration_move_policy);
        $number = (int) (RegistrationNumberSequence::where('sequence_key', $key)->value('next_number') ?: 1);

        return $this->format($prefix, $number, $settings);
    }

    public function assignAutomatic(Student $student, User $actor): Student
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($student, $actor): Student {
                    $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
                    if ($student->registration_number) {
                        return $student;
                    }
                    $prefix = $this->prefixForArm($student->class_arm_id);
                    $settings = $this->settings();
                    $key = $this->sequenceKey($prefix, $settings->registration_move_policy);
                    RegistrationNumberSequence::firstOrCreate(['sequence_key' => $key], ['next_number' => 1]);
                    $sequence = RegistrationNumberSequence::where('sequence_key', $key)->lockForUpdate()->firstOrFail();
                    $value = (int) $sequence->next_number;
                    $number = $this->format($prefix, $value, $settings);
                    while (Student::where('registration_number', $number)->exists()
                        || StudentRegistrationNumberHistory::where('new_number', $number)->exists()) {
                        $value++;
                        $number = $this->format($prefix, $value, $settings);
                    }
                    $this->reserveAndApply($student, $number, $actor, $value);
                    $sequence->update(['next_number' => $value + 1]);

                    return $student->refresh();
                });
            } catch (QueryException $exception) {
                if ($attempt === 2 || ! $this->isUniqueFailure($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages(['registration_number' => 'A number could not be reserved. Please try again.']);
    }

    public function change(Student $student, string $number, User $actor): Student
    {
        $this->authorizeChange($student, $actor);
        $number = $this->normalizeNumber($number);

        return DB::transaction(function () use ($student, $number, $actor): Student {
            $student = Student::whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($student->registration_number === $number) {
                return $student;
            }
            $this->assertAvailable($number, $student->id);
            $this->reserveAndApply($student, $number, $actor, null);

            return $student->refresh();
        });
    }

    public function authorizeChange(Student $student, User $actor): void
    {
        abort_unless($actor->can('change_registration_numbers'), 403);
        if ($student->status === Student::STATUS_AWAITING) {
            abort_unless($actor->can('manage_registration_numbers'), 403);

            return;
        }
        if (! $actor->can('view_all_sections')) {
            app(SectionAccess::class)->assertClassArm($actor, $student->class_arm_id);
        }
    }

    public function saveSettings(array $data, User $actor): void
    {
        abort_unless($actor->can('manage_registration_numbers'), 403);
        $settings = $this->settings();
        $policy = (string) $data['registration_move_policy'];
        $hasNumbers = Student::whereNotNull('registration_number')->exists();
        if ($hasNumbers && $policy !== $settings->registration_move_policy) {
            throw ValidationException::withMessages(['registration_move_policy' => 'This choice is locked because pupils already have registration numbers.']);
        }

        DB::transaction(function () use ($data, $settings, $policy, $hasNumbers, $actor): void {
            $settings->update([
                'registration_prefix' => $this->normalizePrefix($data['registration_prefix']),
                'registration_number_length' => (int) $data['registration_number_length'],
                'registration_include_year' => (bool) ($data['registration_include_year'] ?? false),
                'registration_move_policy' => $policy,
                'registration_move_policy_locked_at' => $hasNumbers ? ($settings->registration_move_policy_locked_at ?: now()) : null,
            ]);
            foreach ($data['section_prefixes'] ?? [] as $id => $prefix) {
                Section::findOrFail((int) $id)->update(['registration_prefix' => $this->optionalPrefix($prefix)]);
            }
            foreach ($data['level_prefixes'] ?? [] as $id => $prefix) {
                AcademicLevel::findOrFail((int) $id)->update(['registration_prefix' => $this->optionalPrefix($prefix)]);
            }
            foreach ($data['next_numbers'] ?? [] as $key => $next) {
                $this->setNext((string) $key, (int) $next);
            }
            app(AuditRecorder::class)->record($actor, 'registration_number_settings_changed', $settings, [
                'move_policy' => $policy,
            ]);
        });
    }

    public function assertAvailable(string $number, ?int $exceptStudentId = null): void
    {
        $number = $this->normalizeNumber($number);
        $current = Student::where('registration_number', $number)
            ->when($exceptStudentId, fn ($query) => $query->whereKeyNot($exceptStudentId))->exists();
        $history = StudentRegistrationNumberHistory::where('new_number', $number)->exists();
        if ($current || $history) {
            throw ValidationException::withMessages(['registration_number' => 'That registration number has already been issued.']);
        }
    }

    private function reserveAndApply(Student $student, string $number, User $actor, ?int $sequenceValue): void
    {
        $this->assertAvailable($number, $student->id);
        StudentRegistrationNumberHistory::create([
            'student_id' => $student->id,
            'old_number' => $student->registration_number,
            'new_number' => $number,
            'sequence_value' => $sequenceValue,
            'changed_by' => $actor->id,
            'changed_at' => now(),
        ]);
        $old = $student->registration_number;
        $student->update(['registration_number' => $number]);
        $settings = $this->settings();
        if (! $settings->registration_move_policy_locked_at) {
            $settings->update(['registration_move_policy_locked_at' => now()]);
        }
        app(AuditRecorder::class)->record($actor, 'student_registration_number_changed', $student, [
            'old_number' => $old,
            'new_number' => $number,
        ]);
    }

    private function setNext(string $key, int $next): void
    {
        if ($key !== 'school' && preg_match('/^prefix:[A-Z0-9]{2,6}$/', $key) !== 1) {
            throw ValidationException::withMessages(['next_numbers' => 'Choose a valid school or prefix sequence.']);
        }
        if ($next < 1) {
            throw ValidationException::withMessages(['next_numbers' => 'The next number must be at least 1.']);
        }
        $prefix = str_starts_with($key, 'prefix:') ? substr($key, 7) : null;
        $issued = StudentRegistrationNumberHistory::whereNotNull('sequence_value')
            ->when($prefix, fn ($query) => $query->where('new_number', 'like', $prefix.'-%'))
            ->max('sequence_value') ?: 0;
        if ($next <= $issued) {
            throw ValidationException::withMessages(['next_numbers' => 'The next number cannot be below the highest number already issued.']);
        }
        RegistrationNumberSequence::updateOrCreate(['sequence_key' => $key], ['next_number' => $next]);
    }

    private function format(string $prefix, int $number, SchoolSetting $settings): string
    {
        $parts = [$prefix];
        if ($settings->registration_include_year) {
            $parts[] = now('Africa/Lagos')->format('Y');
        }
        $parts[] = str_pad((string) $number, (int) $settings->registration_number_length, '0', STR_PAD_LEFT);

        return implode('-', $parts);
    }

    private function sequenceKey(string $prefix, string $policy): string
    {
        return $policy === self::NEW_PREFIX_SEQUENCE ? 'prefix:'.$prefix : 'school';
    }

    private function normalizePrefix(?string $prefix): string
    {
        $prefix = strtoupper(trim((string) $prefix));
        if (! preg_match('/^[A-Z0-9]{2,6}$/', $prefix)) {
            throw ValidationException::withMessages(['registration_prefix' => 'Use 2 to 6 letters or numbers, with no spaces or symbols.']);
        }

        return $prefix;
    }

    private function optionalPrefix(?string $prefix): ?string
    {
        return trim((string) $prefix) === '' ? null : $this->normalizePrefix($prefix);
    }

    private function normalizeNumber(string $number): string
    {
        $number = strtoupper(trim($number));
        if (strlen($number) > 32 || ! preg_match('/^[A-Z0-9-]+$/', $number)) {
            throw ValidationException::withMessages(['registration_number' => 'Use letters, numbers and dashes only.']);
        }

        return $number;
    }

    private function isUniqueFailure(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique');
    }
}
