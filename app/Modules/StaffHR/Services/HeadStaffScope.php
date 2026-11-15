<?php

namespace App\Modules\StaffHR\Services;

use App\Modules\Core\Models\User;
use App\Modules\StaffHR\Models\Staff;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class HeadStaffScope
{
    /** @return Collection<int, int> */
    public function sectionIds(User $head): Collection
    {
        return $head->headSections()->orderBy('sections.name')->pluck('sections.id');
    }

    /** @param Collection<int, int> $sectionIds */
    public function list(Collection $sectionIds, ?string $search): LengthAwarePaginator
    {
        return $this->query($sectionIds)
            ->when(filled($search), function (Builder $query) use ($search): void {
                $needle = '%'.addcslashes(trim((string) $search), '%_\\').'%';
                $query->whereHas('user', fn (Builder $users) => $users->where('name', 'like', $needle));
            })
            ->with($this->relations($sectionIds))
            ->withCount('disciplinaryLogs')
            ->orderBy(User::select('name')->whereColumn('users.id', 'staff.user_id'))
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Staff $staff): array => $this->payload($staff, $sectionIds, true));
    }

    /** @param Collection<int, int> $sectionIds */
    public function find(Collection $sectionIds, int $staffId): Staff
    {
        return $this->query($sectionIds)
            ->with($this->relations($sectionIds))
            ->findOrFail($staffId);
    }

    /** @param Collection<int, int> $sectionIds */
    public function profile(Staff $staff, Collection $sectionIds): array
    {
        return $this->payload($staff, $sectionIds, false);
    }

    /** @param Collection<int, int> $sectionIds */
    private function query(Collection $sectionIds): Builder
    {
        $ids = $sectionIds->all();

        return Staff::query()
            ->select(['id', 'user_id', 'role_title', 'status'])
            ->where('status', 'Active')
            ->whereHas('user', function (Builder $users) use ($ids): void {
                $users
                    ->whereDoesntHave('roles.permissions', fn (Builder $permissions) => $permissions->where('permissions.name', 'view_all_sections'))
                    ->whereDoesntHave('roles.permissions', fn (Builder $permissions) => $permissions->where('permissions.name', 'view_section_staff'))
                    ->where(function (Builder $linked) use ($ids): void {
                        $linked->whereHas('sections', fn (Builder $sections) => $sections->whereIn('sections.id', $ids))
                            ->orWhereHas('teacherSubjectAssignments.schoolClass.level', fn (Builder $levels) => $levels->whereIn('section_id', $ids))
                            ->orWhereHas('formTeacherArms.schoolClass.level', fn (Builder $levels) => $levels->whereIn('section_id', $ids));
                    });
            });
    }

    /** @param Collection<int, int> $sectionIds */
    private function relations(Collection $sectionIds): array
    {
        $ids = $sectionIds->all();

        return [
            'user:id,name,email',
            'user.roles:id,name',
            'user.sections' => fn ($query) => $query->whereIn('sections.id', $ids)->select(['sections.id', 'sections.name']),
            'user.teacherSubjectAssignments' => fn ($query) => $query
                ->whereHas('schoolClass.level', fn (Builder $levels) => $levels->whereIn('section_id', $ids))
                ->select(['id', 'user_id', 'class_id', 'subject_id']),
            'user.teacherSubjectAssignments.subject:id,name',
            'user.teacherSubjectAssignments.schoolClass:id,academic_level_id,name',
            'user.teacherSubjectAssignments.schoolClass.level:id,section_id,name',
            'user.teacherSubjectAssignments.schoolClass.level.section:id,name',
            'user.formTeacherArms' => fn ($query) => $query
                ->whereHas('schoolClass.level', fn (Builder $levels) => $levels->whereIn('section_id', $ids))
                ->select(['id', 'class_id', 'name', 'form_teacher_user_id']),
            'user.formTeacherArms.schoolClass:id,academic_level_id,name',
            'user.formTeacherArms.schoolClass.level:id,section_id,name',
            'user.formTeacherArms.schoolClass.level.section:id,name',
        ];
    }

    /** @param Collection<int, int> $sectionIds */
    private function payload(Staff $staff, Collection $sectionIds, bool $includeCount): array
    {
        $user = $staff->user;
        $sections = collect($user->sections->all())
            ->merge($user->teacherSubjectAssignments->map(fn ($assignment) => $assignment->schoolClass->level->section)->all())
            ->merge($user->formTeacherArms->map(fn ($arm) => $arm->schoolClass->level->section)->all())
            ->filter(fn ($section) => $section && $sectionIds->contains($section->id))
            ->unique('id')->sortBy('name')->pluck('name')->values()->all();
        $teaching = collect($user->teacherSubjectAssignments->map(fn ($assignment): string => $assignment->subject->name.' · '.$assignment->schoolClass->name)->all())
            ->merge($user->formTeacherArms->map(fn ($arm): string => 'Form teacher · '.$arm->schoolClass->name.' '.$arm->name)->all())
            ->unique()->sort()->values()->all();

        return array_filter([
            'id' => $staff->id,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $staff->role_title,
            'roles' => $user->roles->pluck('name')->sort()->values()->all(),
            'sections' => $sections,
            'teaching' => $teaching,
            'discipline_count' => $includeCount ? (int) $staff->disciplinary_logs_count : null,
        ], fn ($value, string $key): bool => $key !== 'discipline_count' || $includeCount, ARRAY_FILTER_USE_BOTH);
    }
}
