<?php

namespace App\Modules\Assignments\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Academics\Services\ResultAcademicCatalog;
use App\Modules\Academics\Services\ResultTeachingAssignments;
use App\Modules\Academics\Services\TeacherFacingLabel;
use App\Modules\Admissions\Services\StudentDirectory;
use App\Modules\Assignments\Models\Assignment;
use App\Modules\Assignments\Models\AssignmentSubmission;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\SchoolUserDirectory;
use App\Modules\Core\Services\TemporaryCredentials;
use App\Modules\Core\TenantContext;
use App\Modules\Notifications\Services\InAppNotifications;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignmentService
{
    public function countForArm(int $armId): int
    {
        return Assignment::where('class_arm_id', $armId)->count();
    }

    public function create(array $data, User $actor): Assignment
    {
        Gate::forUser($actor)->authorize('mark_assignments');
        $arm = ClassArm::findOrFail((int) $data['class_arm_id']);
        if ($arm->class_id !== (int) $data['class_id']) {
            throw ValidationException::withMessages(['class_arm_id' => 'Choose an arm from the selected class.']);
        }
        app(ResultAcademicCatalog::class)->assertClassSubject($arm->class_id, (int) $data['subject_id']);
        $this->assertMayTeach($actor, $arm, (int) $data['subject_id']);

        return DB::transaction(function () use ($data, $actor, $arm): Assignment {
            $assignment = Assignment::create([
                'class_arm_id' => $arm->id, 'subject_id' => $data['subject_id'],
                'title' => $data['title'], 'description' => $data['description'] ?? null,
                'due_date' => $data['due_date'], 'created_by' => $actor->id,
            ]);
            $roster = app(StudentDirectory::class)->rosterForArm($arm->id);
            foreach ($roster as $student) {
                AssignmentSubmission::create(['assignment_id' => $assignment->id, 'student_id' => $student->id, 'status' => 'Not Submitted']);
            }
            foreach ($roster as $student) {
                app(InAppNotifications::class)->sendForStudent(
                    $student->id, $student->user_id, $student->user?->name ?? 'your child',
                    'New assignment', $assignment->title.' is due '.$assignment->due_date->format('j M Y').'.',
                );
            }

            return $assignment;
        });
    }

    public function mark(int $assignmentId, int $studentId, string $status, User $actor): void
    {
        Gate::forUser($actor)->authorize('mark_assignments');
        $assignment = Assignment::findOrFail($assignmentId);
        $arm = ClassArm::findOrFail($assignment->class_arm_id);
        $this->assertMayTeach($actor, $arm, $assignment->subject_id);
        if (! in_array($status, ['Submitted', 'Not Submitted', 'Late'], true)) {
            throw ValidationException::withMessages(['status' => 'Choose a valid tick status.']);
        }
        $submission = AssignmentSubmission::where('assignment_id', $assignmentId)->where('student_id', $studentId)->firstOrFail();
        $submission->update(['status' => $status, 'marked_by' => $actor->id, 'marked_at' => now()]);
    }

    public function linkWard(int $parentUserId, int $studentId): void
    {
        $user = app(SchoolUserDirectory::class)->find($parentUserId);
        abort_unless($user, 404);
        app(StudentDirectory::class)->classArmId($studentId);
        $parent = ParentProfile::firstOrCreate(['user_id' => $user->id]);
        ParentStudent::firstOrCreate(['parent_id' => $parent->id, 'student_id' => $studentId]);
    }

    public function createParentWithWard(array $data, User $actor): array
    {
        $temporary = app(TemporaryCredentials::class)->generate();

        $user = DB::transaction(function () use ($data, $actor, $temporary): User {
            $user = new User;
            $user->fill(['name' => trim($data['name']), 'username' => strtolower(trim($data['username'])),
                'email' => filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null,
                'email_verified_at' => filled($data['email'] ?? null) ? now() : null,
                'must_change_password' => true, 'status' => 'active']);
            $user->setRawAttributes(array_merge($user->getAttributes(), ['password' => app(TemporaryCredentials::class)->hash($temporary)]));
            $user->save();
            $role = Role::firstOrCreate(['name' => 'Parent']);
            $user->roles()->syncWithoutDetaching([$role->id => ['school_id' => app(TenantContext::class)->id()]]);
            $this->linkWard($user->id, (int) $data['student_id']);
            app(AuditRecorder::class)->record($actor, 'parent_account_created', $user, ['student_id' => (int) $data['student_id']]);

            return $user;
        });

        return ['user' => $user, 'temporary_password' => $temporary];
    }

    public function staffList(User $actor)
    {
        $query = Assignment::with(['subject', 'classArm.schoolClass'])->orderByDesc('id');
        if (! Gate::forUser($actor)->allows('manage_assignments')) {
            $scopes = app(ResultTeachingAssignments::class)->resultScopesFor($actor);
            $query->where(function ($builder) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $builder->orWhere(function ($pair) use ($scope): void {
                        $pair->where('subject_id', $scope['subject_id'])->whereIn('class_arm_id', $scope['arm_ids']);
                    });
                }
                if ($scopes === []) {
                    $builder->whereRaw('1 = 0');
                }
            });
        }

        return $query->paginate(20);
    }

    public function submissions(int $assignmentId, User $actor): array
    {
        $assignment = Assignment::with(['subject', 'classArm.schoolClass'])->findOrFail($assignmentId);
        $arm = ClassArm::findOrFail($assignment->class_arm_id);
        $this->assertMayTeach($actor, $arm, $assignment->subject_id);
        $roster = app(StudentDirectory::class)->rosterForArm($arm->id);
        $rows = AssignmentSubmission::where('assignment_id', $assignmentId)->get()->keyBy('student_id');

        $pairLabel = app(TeacherFacingLabel::class)->pair($assignment->subject?->name, $arm->schoolClass?->name);

        return compact('assignment', 'arm', 'roster', 'rows', 'pairLabel');
    }

    public function forStudent(User $actor, string $show = 'todo')
    {
        $student = app(StudentDirectory::class)->forActiveUser($actor);
        abort_unless($student, 403);

        return $this->portalRows($student->id, $show);
    }

    public function forParent(User $actor, int $studentId, string $show = 'todo')
    {
        abort_unless(app(ParentAccountDirectory::class)->isWard($studentId, $actor->id), 403);
        abort_unless(app(StudentDirectory::class)->isActive($studentId), 403);

        return $this->portalRows($studentId, $show);
    }

    private function portalRows(int $studentId, string $show)
    {
        $query = AssignmentSubmission::with(['assignment.subject'])->where('student_id', $studentId);
        if ($show === 'todo') {
            $query->where('status', 'Not Submitted');
        } elseif ($show === 'done') {
            $query->whereIn('status', ['Submitted', 'Late']);
        }

        return $query->orderByDesc('assignment_id')->paginate(20)->withQueryString();
    }

    public function portalSummary(int $studentId, User $viewer): array
    {
        $isOwner = app(StudentDirectory::class)->belongsToUser($studentId, $viewer);
        $isWard = app(ParentAccountDirectory::class)->isWard($studentId, $viewer->id);
        abort_unless($isOwner || $isWard, 403);
        $rows = AssignmentSubmission::with('assignment')->where('student_id', $studentId)
            ->orderByDesc('assignment_id')->limit(5)->get();

        return [
            'recent' => $rows,
            'pending' => AssignmentSubmission::where('student_id', $studentId)->where('status', 'Not Submitted')->count(),
        ];
    }

    private function assertMayTeach(User $actor, ClassArm $arm, int $subjectId): void
    {
        if (! Gate::forUser($actor)->allows('manage_assignments')
            && ! app(ResultTeachingAssignments::class)->mayEnter($actor, $arm->class_id, $subjectId)) {
            throw ValidationException::withMessages(['subject_id' => 'You are not assigned to this class and subject.']);
        }
    }
}
