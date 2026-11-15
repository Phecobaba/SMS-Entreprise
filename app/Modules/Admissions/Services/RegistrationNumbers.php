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

    public function __construct(private readonly RegistrationNumberBuilder $builder) {}

    public function settings(): SchoolSetting
    {
        return SchoolSetting::firstOrCreate(
            ['school_id' => app(TenantContext::class)->id()],
            ['registration_prefix' => 'REG', 'registration_number_length' => 4,
                'registration_include_year' => false, 'registration_move_policy' => self::KEEP_DIGITS_CHANGE_PREFIX,
                'registration_include_school' => true, 'registration_include_section' => false,
                'registration_include_level' => false, 'registration_separator' => '-',
                'registration_year_digits' => 4, 'registration_school_code' => 'REG',
                'registration_next_number' => 1],
        );
    }

    public function prefixForArm(int $armId): string
    {
        $arm = ClassArm::with('schoolClass.level.section')->findOrFail($armId);

        return $this->builder->prefixForArm($arm, $this->settings());
    }

    public function preview(int $armId): string
    {
        $arm = ClassArm::with('schoolClass.level.section')->findOrFail($armId);
        $settings = $this->settings();
        $prefix = $this->builder->prefixForArm($arm, $settings);
        $key = $this->sequenceKey($prefix, $settings->registration_move_policy);
        $number = (int) (RegistrationNumberSequence::where('sequence_key', $key)->value('next_number')
            ?: $settings->registration_next_number ?: 1);

        return $this->builder->buildForArm($arm, $number, $settings);
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
                    $arm = ClassArm::with('schoolClass.level.section')->findOrFail($student->class_arm_id);
                    $settings = $this->settings();
                    $prefix = $this->builder->prefixForArm($arm, $settings);
                    $key = $this->sequenceKey($prefix, $settings->registration_move_policy);
                    RegistrationNumberSequence::firstOrCreate(
                        ['sequence_key' => $key],
                        ['next_number' => (int) ($settings->registration_next_number ?: 1)],
                    );
                    $sequence = RegistrationNumberSequence::where('sequence_key', $key)->lockForUpdate()->firstOrFail();
                    $value = (int) $sequence->next_number;
                    $number = $this->builder->buildForArm($arm, $value, $settings);
                    while (Student::where('registration_number', $number)->exists()
                        || StudentRegistrationNumberHistory::where('new_number', $number)->exists()) {
                        $value++;
                        $number = $this->builder->buildForArm($arm, $value, $settings);
                    }
                    $allocation = $this->builder->allocationForArm($arm);
                    $this->reserveAndApply($student, $number, $actor, $value, $allocation, 'initial_allocation');
                    $sequence->update(['next_number' => $value + 1]);
                    if ($key === 'school') {
                        $settings->update(['registration_next_number' => $value + 1]);
                    }

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
            $arm = ClassArm::with('schoolClass.level.section')->findOrFail($student->class_arm_id);
            $this->reserveAndApply(
                $student, $number, $actor, null, $this->builder->allocationForArm($arm), 'manual_change'
            );

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
        $hasNumbers = $settings->registration_move_policy_locked_at !== null
            || Student::whereNotNull('registration_number')->exists()
            || StudentRegistrationNumberHistory::exists();
        if ($hasNumbers && $policy !== $settings->registration_move_policy) {
            throw ValidationException::withMessages(['registration_move_policy' => 'This choice is locked because pupils already have registration numbers.']);
        }

        $includeSchool = (bool) ($data['registration_include_school'] ?? false);
        $includeSection = (bool) ($data['registration_include_section'] ?? false);
        $includeLevel = (bool) ($data['registration_include_level'] ?? false);
        $schoolCode = $this->optionalCode($data['registration_school_code'] ?? null, 'registration_school_code', 2);
        if ($includeSchool && $schoolCode === null) {
            throw ValidationException::withMessages(['registration_school_code' => 'Use 2 to 6 letters or numbers for the School code.']);
        }
        $schoolCode ??= $settings->registration_school_code;
        if (! in_array((string) ($data['registration_separator'] ?? ''), ['-', '/', '.', ''], true)) {
            throw ValidationException::withMessages(['registration_separator' => 'Choose hyphen, slash, dot or no separator.']);
        }
        if (! in_array((int) ($data['registration_number_length'] ?? 0), range(3, 6), true)) {
            throw ValidationException::withMessages(['registration_number_length' => 'Choose a number length from 3 to 6.']);
        }
        if (! in_array((int) ($data['registration_year_digits'] ?? 0), [2, 4], true)) {
            throw ValidationException::withMessages(['registration_year_digits' => 'Choose a 2-digit or 4-digit year.']);
        }

        $sections = Section::with('levels')->orderBy('id')->get();
        $sectionCodes = $this->codesFor($sections, $data['section_codes'] ?? [], 'section_codes');
        $levels = AcademicLevel::orderBy('id')->get();
        $levelCodes = $this->codesFor($levels, $data['level_codes'] ?? [], 'level_codes');
        $this->validateCodes($sections, $levels, $sectionCodes, $levelCodes, $includeSection, $includeLevel);

        $next = (int) $data['registration_next_number'];
        $highest = $this->highestIssued();
        if ($next <= $highest) {
            throw ValidationException::withMessages([
                'registration_next_number' => 'The next number cannot be below the highest number already issued.',
            ]);
        }

        DB::transaction(function () use ($data, $settings, $policy, $hasNumbers, $actor, $includeSchool, $includeSection, $includeLevel, $schoolCode, $sections, $levels, $sectionCodes, $levelCodes, $next): void {
            $settings->update([
                'registration_prefix' => $schoolCode ?: $settings->registration_prefix,
                'registration_school_code' => $schoolCode,
                'registration_number_length' => (int) $data['registration_number_length'],
                'registration_include_year' => (bool) ($data['registration_include_year'] ?? false),
                'registration_include_school' => $includeSchool,
                'registration_include_section' => $includeSection,
                'registration_include_level' => $includeLevel,
                'registration_separator' => (string) $data['registration_separator'],
                'registration_year_digits' => (int) $data['registration_year_digits'],
                'registration_next_number' => $next,
                'registration_move_policy' => $policy,
                'registration_move_policy_locked_at' => $hasNumbers ? ($settings->registration_move_policy_locked_at ?: now()) : null,
            ]);
            foreach ($sections as $section) {
                $section->update(['registration_code' => $sectionCodes[$section->id]]);
            }
            foreach ($levels as $level) {
                $level->update(['registration_code' => $levelCodes[$level->id]]);
            }
            foreach (RegistrationNumberSequence::get() as $sequence) {
                $sequence->update(['next_number' => $next]);
            }
            app(AuditRecorder::class)->record($actor, 'registration_number_settings_changed', $settings, [
                'move_policy' => $policy, 'parts' => array_values(array_filter([
                    $includeSchool ? 'school' : null, $includeSection ? 'section' : null,
                    $includeLevel ? 'level' : null, ($data['registration_include_year'] ?? false) ? 'year' : null,
                    'number',
                ])),
            ]);
        });
    }

    public function highestIssued(): int
    {
        return (int) (StudentRegistrationNumberHistory::whereNotNull('sequence_value')->max('sequence_value') ?: 0);
    }

    public function nextNumber(): int
    {
        return max(
            (int) ($this->settings()->registration_next_number ?: 1),
            (int) (RegistrationNumberSequence::max('next_number') ?: 1),
        );
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

    /**
     * @param  array{numbers?:array<string,bool>,counters?:array<string,int>}  $state
     * @return array{old_number:?string,new_number:?string,sequence_value:?int,issued_year:?int,section_code:?string,level_code:?string,changed:bool,adjusted:bool,reason:string}
     */
    public function planMovement(Student $student, ClassArm $destination, array &$state = [], bool $reserveCounter = false): array
    {
        $student->loadMissing('classArm.schoolClass.level.section');
        $destination->loadMissing('schoolClass.level.section');
        $old = $student->registration_number;
        $unchanged = [
            'old_number' => $old, 'new_number' => $old, 'sequence_value' => null,
            'issued_year' => null, 'section_code' => null, 'level_code' => null,
            'changed' => false, 'adjusted' => false, 'reason' => 'movement_policy_kept_number',
        ];
        if (! $old) {
            return $unchanged;
        }

        $settings = $this->settings();
        $sourceLevel = $student->classArm?->schoolClass?->level;
        $destinationLevel = $destination->schoolClass?->level;
        $sectionChanged = (bool) $settings->registration_include_section
            && $sourceLevel?->section_id !== $destinationLevel?->section_id;
        $levelChanged = (bool) $settings->registration_include_level
            && $sourceLevel?->id !== $destinationLevel?->id;
        if ((! $sectionChanged && ! $levelChanged)
            || $settings->registration_move_policy === self::KEEP_WHOLE_NUMBER) {
            return $unchanged;
        }

        $allocation = StudentRegistrationNumberHistory::where('student_id', $student->id)
            ->where('new_number', $old)->latest('changed_at')->latest('id')->first();
        $issuedYear = $allocation?->issued_year ?: (int) $allocation?->changed_at?->format('Y');
        $adjusted = false;

        if ($settings->registration_move_policy === self::KEEP_DIGITS_CHANGE_PREFIX) {
            if (! $allocation?->sequence_value) {
                throw ValidationException::withMessages([
                    'registration_number' => 'The stored number sequence is missing for '.$student->user?->name.'. Assign a number before moving this pupil.',
                ]);
            }
            $value = (int) $allocation->sequence_value;
            $number = $this->builder->buildForArm($destination, $value, $settings, $issuedYear);
            if (! $this->availableForPlan($number, $student->id, $state)) {
                $adjusted = true;
                [$value, $number] = $this->nextFromPrefix($destination, $settings, $state, $reserveCounter);
                $issuedYear = (int) now('Africa/Lagos')->format('Y');
            }
        } else {
            [$value, $number] = $this->nextFromPrefix($destination, $settings, $state, $reserveCounter);
            $issuedYear = (int) now('Africa/Lagos')->format('Y');
        }

        $state['numbers'][$number] = true;
        $metadata = $this->builder->allocationForArm($destination, $issuedYear);

        return [
            'old_number' => $old, 'new_number' => $number, 'sequence_value' => $value,
            ...$metadata, 'changed' => $old !== $number, 'adjusted' => $adjusted,
            'reason' => $adjusted ? 'movement_policy_clash_adjustment' : 'movement_policy',
        ];
    }

    /**
     * @param  array{numbers?:array<string,bool>,counters?:array<string,int>}  $state
     * @return array{old_number:null,new_number:string,sequence_value:int,issued_year:int,section_code:?string,level_code:?string,changed:true,adjusted:false,reason:string}
     */
    public function planInitial(ClassArm $destination, array &$state = [], bool $reserveCounter = false): array
    {
        $settings = $this->settings();
        $prefix = $this->builder->prefixForArm($destination, $settings);
        $key = $this->sequenceKey($prefix, $settings->registration_move_policy);
        [$value, $number] = $this->nextFromKey($destination, $settings, $key, $state, $reserveCounter);
        $state['numbers'][$number] = true;
        $metadata = $this->builder->allocationForArm($destination);

        return [
            'old_number' => null, 'new_number' => $number, 'sequence_value' => $value,
            ...$metadata, 'changed' => true, 'adjusted' => false, 'reason' => 'incoming_acceptance',
        ];
    }

    /** @param array<string,mixed> $plan */
    public function applyMovementPlan(Student $student, array $plan, User $actor): Student
    {
        if (! ($plan['changed'] ?? false)) {
            return $student;
        }
        if ($student->registration_number !== $plan['old_number']) {
            throw ValidationException::withMessages(['registration_number' => 'The pupil registration number changed after preview. Review the movement again.']);
        }
        $this->reserveAndApply($student, $plan['new_number'], $actor, $plan['sequence_value'], [
            'issued_year' => $plan['issued_year'], 'section_code' => $plan['section_code'],
            'level_code' => $plan['level_code'],
        ], $plan['reason'], (bool) $plan['adjusted']);

        return $student->refresh();
    }

    /** @param array<string,mixed> $plan */
    public function applyInitialPlan(Student $student, array $plan, User $actor): Student
    {
        if ($student->registration_number) {
            return $student;
        }
        $this->reserveAndApply($student, $plan['new_number'], $actor, $plan['sequence_value'], [
            'issued_year' => $plan['issued_year'], 'section_code' => $plan['section_code'],
            'level_code' => $plan['level_code'],
        ], $plan['reason']);

        return $student->refresh();
    }

    /** @param array{issued_year:?int,section_code:?string,level_code:?string} $allocation */
    private function reserveAndApply(Student $student, string $number, User $actor, ?int $sequenceValue, array $allocation, string $reason, bool $adjusted = false): void
    {
        $this->assertAvailable($number, $student->id);
        StudentRegistrationNumberHistory::create([
            'student_id' => $student->id,
            'old_number' => $student->registration_number,
            'new_number' => $number,
            'sequence_value' => $sequenceValue,
            'issued_year' => $allocation['issued_year'],
            'section_code' => $allocation['section_code'],
            'level_code' => $allocation['level_code'],
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
            'reason' => $reason,
            'clash_adjusted' => $adjusted,
        ]);
    }

    /** @param array{numbers?:array<string,bool>,counters?:array<string,int>} $state */
    private function availableForPlan(string $number, int $studentId, array $state): bool
    {
        if (isset($state['numbers'][$number])) {
            return false;
        }

        return ! Student::where('registration_number', $number)->whereKeyNot($studentId)->exists()
            && ! StudentRegistrationNumberHistory::where('new_number', $number)->exists();
    }

    /**
     * @param  array{numbers?:array<string,bool>,counters?:array<string,int>}  $state
     * @return array{0:int,1:string}
     */
    private function nextFromPrefix(ClassArm $destination, SchoolSetting $settings, array &$state, bool $reserveCounter): array
    {
        $prefix = $this->builder->prefixForArm($destination, $settings);
        $key = 'prefix:'.($prefix !== '' ? $prefix : 'plain');

        return $this->nextFromKey($destination, $settings, $key, $state, $reserveCounter);
    }

    /**
     * @param  array{numbers?:array<string,bool>,counters?:array<string,int>}  $state
     * @return array{0:int,1:string}
     */
    private function nextFromKey(ClassArm $destination, SchoolSetting $settings, string $key, array &$state, bool $reserveCounter): array
    {
        if (! isset($state['counters'][$key])) {
            $query = RegistrationNumberSequence::where('sequence_key', $key);
            if ($reserveCounter) {
                RegistrationNumberSequence::firstOrCreate(
                    ['sequence_key' => $key], ['next_number' => (int) ($settings->registration_next_number ?: 1)]
                );
                $state['counters'][$key] = (int) $query->lockForUpdate()->value('next_number');
            } else {
                $state['counters'][$key] = (int) ($query->value('next_number') ?: $settings->registration_next_number ?: 1);
            }
        }
        $value = $state['counters'][$key];
        $number = $this->builder->buildForArm($destination, $value, $settings);
        while (! $this->availableForPlan($number, 0, $state)) {
            $value++;
            $number = $this->builder->buildForArm($destination, $value, $settings);
        }
        $state['counters'][$key] = $value + 1;
        if ($reserveCounter) {
            RegistrationNumberSequence::where('sequence_key', $key)->update(['next_number' => $value + 1]);
            if ($key === 'school') {
                $settings->update(['registration_next_number' => $value + 1]);
            }
        }

        return [$value, $number];
    }

    private function sequenceKey(string $prefix, string $policy): string
    {
        return $policy === self::NEW_PREFIX_SEQUENCE ? 'prefix:'.($prefix !== '' ? $prefix : 'plain') : 'school';
    }

    private function normalizePrefix(?string $prefix): string
    {
        $prefix = strtoupper(trim((string) $prefix));
        if (! preg_match('/^[A-Z0-9]{2,6}$/', $prefix)) {
            throw ValidationException::withMessages(['registration_prefix' => 'Use 2 to 6 letters or numbers, with no spaces or symbols.']);
        }

        return $prefix;
    }

    private function normalizeNumber(string $number): string
    {
        $number = strtoupper(trim($number));
        if (strlen($number) > 64 || ! preg_match('/^[A-Z0-9.\/-]+$/', $number)) {
            throw ValidationException::withMessages(['registration_number' => 'Use letters, numbers, hyphens, slashes or dots only.']);
        }

        return $number;
    }

    private function isUniqueFailure(QueryException $exception): bool
    {
        return in_array((string) ($exception->errorInfo[0] ?? ''), ['23000', '23505'], true)
            || str_contains(strtolower($exception->getMessage()), 'unique');
    }

    private function optionalCode(mixed $value, string $field, int $minimum = 1): ?string
    {
        $code = strtoupper(trim((string) $value));
        if ($code === '') {
            return null;
        }
        if (preg_match('/^[A-Z0-9]{'.$minimum.',6}$/', $code) !== 1) {
            throw ValidationException::withMessages([$field => 'Use '.$minimum.' to 6 letters or numbers.']);
        }

        return $code;
    }

    /** @return array<int, string|null> */
    private function codesFor($models, array $submitted, string $field): array
    {
        $known = $models->pluck('id')->map(fn ($id) => (string) $id)->all();
        foreach (array_keys($submitted) as $id) {
            if (! in_array((string) $id, $known, true)) {
                throw ValidationException::withMessages([$field => 'Choose codes only for this school.']);
            }
        }

        return $models->mapWithKeys(fn ($model) => [
            $model->id => $this->optionalCode(
                array_key_exists($model->id, $submitted) ? $submitted[$model->id] : $model->registration_code,
                $field.'.'.$model->id,
            ),
        ])->all();
    }

    private function validateCodes($sections, $levels, array $sectionCodes, array $levelCodes, bool $includeSection, bool $includeLevel): void
    {
        $errors = [];
        if ($includeSection) {
            $seen = [];
            foreach ($sections as $section) {
                $code = $sectionCodes[$section->id];
                if (! $code) {
                    $errors['section_codes.'.$section->id] = $section->name.' needs a code.';
                } elseif (isset($seen[$code])) {
                    $errors['section_codes.'.$section->id] = 'Section code '.$code.' is used twice.';
                } else {
                    $seen[$code] = true;
                }
            }
        }
        if ($includeLevel) {
            $seen = [];
            foreach ($levels as $level) {
                $code = $levelCodes[$level->id];
                $group = $includeSection ? (string) ($level->section_id ?: 'no-section') : 'school';
                if (! $code) {
                    $errors['level_codes.'.$level->id] = $level->name.' needs a code.';
                } elseif (isset($seen[$group][$code])) {
                    $errors['level_codes.'.$level->id] = 'Level code '.$code.' is used twice.';
                } else {
                    $seen[$group][$code] = true;
                }
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
