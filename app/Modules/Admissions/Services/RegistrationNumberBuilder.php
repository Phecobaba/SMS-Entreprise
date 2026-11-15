<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Core\Models\SchoolSetting;
use Illuminate\Validation\ValidationException;

class RegistrationNumberBuilder
{
    public function buildForArm(ClassArm $arm, int $number, SchoolSetting $settings, ?int $issuedYear = null): string
    {
        return implode((string) $settings->registration_separator, array_merge(
            $this->partsForArm($arm, $settings, $issuedYear),
            [str_pad((string) $number, (int) $settings->registration_number_length, '0', STR_PAD_LEFT)],
        ));
    }

    public function prefixForArm(ClassArm $arm, SchoolSetting $settings): string
    {
        return implode((string) $settings->registration_separator, $this->partsForArm($arm, $settings));
    }

    /** @return list<string> */
    public function allocationForArm(ClassArm $arm, ?int $issuedYear = null): array
    {
        $arm->loadMissing('schoolClass.level.section');
        $level = $arm->schoolClass?->level;
        if (! $level) {
            throw ValidationException::withMessages(['class_arm_id' => 'The class must have a Level before a registration number can be issued.']);
        }

        return [
            'issued_year' => $issuedYear ?? (int) now('Africa/Lagos')->format('Y'),
            'section_code' => $level->section?->registration_code,
            'level_code' => $level->registration_code,
        ];
    }

    /** @return list<string> */
    private function partsForArm(ClassArm $arm, SchoolSetting $settings, ?int $issuedYear = null): array
    {
        $arm->loadMissing('schoolClass.level.section');
        $level = $arm->schoolClass?->level;
        if (! $level) {
            throw ValidationException::withMessages(['class_arm_id' => 'The class must have a Level before a registration number can be issued.']);
        }

        $parts = [];
        if ($settings->registration_include_school) {
            $parts[] = $this->requiredCode($settings->registration_school_code, 'The School code is missing.');
        }
        if ($settings->registration_include_section) {
            if (! $level->section) {
                throw ValidationException::withMessages(['class_arm_id' => 'The pupil\'s Level must belong to a Section before a registration number can be issued.']);
            }
            $parts[] = $this->requiredCode($level->section->registration_code, 'The Section code is missing.');
        }
        if ($settings->registration_include_level) {
            $parts[] = $this->requiredCode($level->registration_code, 'The Level code is missing.');
        }
        if ($settings->registration_include_year) {
            $year = $issuedYear ?? (int) now('Africa/Lagos')->format('Y');
            $parts[] = (int) $settings->registration_year_digits === 2
                ? str_pad((string) ($year % 100), 2, '0', STR_PAD_LEFT)
                : (string) $year;
        }

        return $parts;
    }

    private function requiredCode(?string $code, string $message): string
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            throw ValidationException::withMessages(['registration_format' => $message]);
        }

        return $code;
    }
}
