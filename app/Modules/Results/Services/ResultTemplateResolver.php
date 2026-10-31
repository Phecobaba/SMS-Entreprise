<?php

namespace App\Modules\Results\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultTemplate;

class ResultTemplateResolver
{
    public function forClassArm(int $classArmId): ?ResultTemplate
    {
        $arm = ClassArm::with('schoolClass.level')->findOrFail($classArmId);
        $class = $arm->schoolClass;
        $level = $class->level;

        return ResultTemplate::with('components.component')
            ->whereNull('retired_at')
            ->where('section_id', $level->section_id)
            ->where(function ($query) use ($class, $level): void {
                $query->where('class_id', $class->id)
                    ->orWhere(function ($levelScope) use ($level): void {
                        $levelScope->whereNull('class_id')->where('academic_level_id', $level->id);
                    })
                    ->orWhere(function ($sectionScope): void {
                        $sectionScope->whereNull('class_id')->whereNull('academic_level_id');
                    });
            })
            ->orderByRaw('case when class_id is not null then 1 when academic_level_id is not null then 2 else 3 end')
            ->first();
    }

    public function stored(int $templateId): ResultTemplate
    {
        return ResultTemplate::with('components.component')->findOrFail($templateId);
    }

    public function storedFor(Result $result): ResultTemplate
    {
        if ($result->relationLoaded('template') && $result->template) {
            return $result->template->loadMissing('components.component');
        }

        return $this->stored($result->result_template_id);
    }
}
