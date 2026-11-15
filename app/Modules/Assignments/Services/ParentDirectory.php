<?php

namespace App\Modules\Assignments\Services;

use App\Modules\Academics\Services\LearnerTerminology;
use App\Modules\Academics\Services\SectionAccess;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Payments\Services\PaymentLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ParentDirectory
{
    private array $armIdsByActor = [];

    public function index(User $actor, string $search = ''): LengthAwarePaginator
    {
        $query = ParentProfile::with(['user', 'students.user', 'students.classArm.schoolClass.level.section'])
            ->withCount('students');
        $this->scopeParents($query, $actor);
        if ($search !== '') {
            $query->where(function (Builder $part) use ($search): void {
                $like = '%'.$search.'%';
                $part->whereHas('user', fn (Builder $user) => $user->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)->orWhere('email', 'like', $like))
                    ->orWhereHas('students.user', fn (Builder $user) => $user->where('name', 'like', $like));
            });
        }

        $parents = $query->orderBy('id')->paginate(20)->withQueryString();
        $students = $parents->getCollection()->flatMap(fn (ParentProfile $parent) => $this->visibleStudents($parent, $actor));
        $fees = app(PaymentLedger::class)->termFeeStatusByStudentIds($students->pluck('id')->all());
        $parents->getCollection()->each(function (ParentProfile $parent) use ($actor, $fees): void {
            $visible = $this->visibleStudents($parent, $actor);
            $parent->setRelation('students', $visible);
            $parent->setAttribute('students_count', $visible->count());
            $parent->setAttribute('worst_fee_status', $this->worstFeeStatus($visible->pluck('id')->all(), $fees));
        });

        return $parents;
    }

    public function find(User $actor, int $parentId): ParentProfile
    {
        $query = ParentProfile::with(['user', 'students.user', 'students.classArm.schoolClass.level.section']);
        $this->scopeParents($query, $actor);
        $parent = $query->findOrFail($parentId);
        $students = $this->visibleStudents($parent, $actor);
        $fees = app(PaymentLedger::class)->termFeeStatusByStudentIds($students->pluck('id')->all());
        $terminology = app(LearnerTerminology::class);
        $students->each(function (Student $student) use ($fees, $terminology): void {
            $student->setAttribute('fee_summary', $fees[$student->id] ?? ['status' => 'No invoice', 'outstanding_kobo' => 0]);
            $student->setAttribute('learner_word', $terminology->word($student));
        });
        $parent->setRelation('students', $students);

        return $parent;
    }

    public function studentChoices(User $actor, string $search): mixed
    {
        if ($search === '') {
            return collect();
        }
        $query = Student::with(['user', 'classArm.schoolClass.level.section'])
            ->whereHas('user', fn (Builder $user) => $user->where('name', 'like', '%'.$search.'%')
                ->orWhere('username', 'like', '%'.$search.'%'));
        $this->scopeStudents($query, $actor);

        return $query->orderBy('id')->limit(20)->get();
    }

    public function link(ParentProfile $parent, Student $student, User $actor): void
    {
        $this->find($actor, $parent->id);
        $this->assertStudentVisible($actor, $student);
        DB::transaction(function () use ($parent, $student, $actor): void {
            app(AssignmentService::class)->linkWard($parent->user_id, $student->id);
            app(AuditRecorder::class)->record($actor, 'parent_child_linked', $parent, ['student_id' => $student->id], 'Accounts');
        });
    }

    public function unlink(ParentProfile $parent, Student $student, User $actor): void
    {
        $this->find($actor, $parent->id);
        $this->assertStudentVisible($actor, $student);
        DB::transaction(function () use ($parent, $student, $actor): void {
            ParentStudent::where('parent_id', $parent->id)->where('student_id', $student->id)->delete();
            app(AuditRecorder::class)->record($actor, 'parent_child_unlinked', $parent, ['student_id' => $student->id], 'Accounts');
        });
    }

    private function scopeParents(Builder $query, User $actor): void
    {
        $armIds = $this->armIds($actor);
        if ($armIds !== null) {
            $query->whereHas('students', fn (Builder $student) => $student->whereIn('class_arm_id', $armIds));
        }
    }

    private function scopeStudents(Builder $query, User $actor): void
    {
        $armIds = $this->armIds($actor);
        if ($armIds !== null) {
            $query->whereIn('class_arm_id', $armIds);
        }
    }

    public function assertStudentVisible(User $actor, Student $student): void
    {
        $query = Student::whereKey($student->id);
        $this->scopeStudents($query, $actor);
        abort_unless($query->exists(), 404);
    }

    private function visibleStudents(ParentProfile $parent, User $actor)
    {
        $armIds = $this->armIds($actor);

        return $armIds === null ? $parent->students : $parent->students->whereIn('class_arm_id', $armIds)->values();
    }

    private function armIds(User $actor): ?array
    {
        if (array_key_exists($actor->id, $this->armIdsByActor)) {
            return $this->armIdsByActor[$actor->id];
        }
        if ($actor->can('view_all_parents')) {
            return $this->armIdsByActor[$actor->id] = null;
        }

        return $this->armIdsByActor[$actor->id] = app(SectionAccess::class)->visibleArmIds($actor);
    }

    private function worstFeeStatus(array $studentIds, array $fees): string
    {
        $rank = ['No invoice' => 0, 'Paid' => 1, 'Part-paid' => 2, 'Owing' => 3];

        return collect($studentIds)->map(fn (int $id): string => $fees[$id]['status'] ?? 'No invoice')
            ->sortByDesc(fn (string $status): int => $rank[$status])->first() ?? 'No invoice';
    }
}
