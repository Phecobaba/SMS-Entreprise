<?php

namespace App\Modules\Academics\Services;

class TeacherFacingLabel
{
    public function pair(?string $subject, ?string $class): string
    {
        return trim(($subject ?: 'Subject').' · '.($class ?: 'Class'));
    }

    public function arm(?string $class, ?string $arm): string
    {
        return trim(($class ?: 'Class').' '.($arm ?: ''));
    }
}
