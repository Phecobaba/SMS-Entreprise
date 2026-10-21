<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Academics\Models\Department;
use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Section;
use App\Modules\Admissions\Models\Candidate;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SectionAdministration
{
    public function createSection(string $name, string $headTitle, User $actor): Section
    {
        Gate::forUser($actor)->authorize('view_all_sections');

        return Section::create(['name' => trim($name), 'head_title' => trim($headTitle)]);
    }

    public function assignHead(Section $section, ?User $head, User $actor): Section
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        if ($head && ! $head->can('approve_results')) {
            throw ValidationException::withMessages(['head_user_id' => 'Choose a user who has result-approval permission.']);
        }
        $section->heads()->sync($head ? [$head->id => ['school_id' => app(TenantContext::class)->id()]] : []);

        return $section->refresh()->load('heads');
    }

    public function createDepartment(string $name, User $actor): Department
    {
        Gate::forUser($actor)->authorize('view_all_sections');

        if (Department::whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])->exists()) {
            throw ValidationException::withMessages(['name' => 'A department with this name already exists.']);
        }

        return Department::create(['name' => trim($name)]);
    }

    public function assignLevel(AcademicLevel $level, Section $section, User $actor): AcademicLevel
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        $level->update(['section_id' => $section->id]);

        return $level->refresh();
    }

    public function assignDepartment(SchoolClass $class, ?Department $department, User $actor): SchoolClass
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        $class->update(['department_id' => $department?->id]);

        return $class->refresh();
    }

    public function assignCandidateLevel(Candidate $candidate, AcademicLevel $level, User $actor): Candidate
    {
        Gate::forUser($actor)->authorize('view_all_sections');
        $candidate->update(['applied_level_id' => $level->id]);

        return $candidate->refresh();
    }

    public function assertAllLevelsAssigned(): void
    {
        if (AcademicLevel::whereNull('section_id')->exists()) {
            throw ValidationException::withMessages([
                'sections' => 'Assign every academic level to a section before enabling section-scoped work.',
            ]);
        }
    }
}
