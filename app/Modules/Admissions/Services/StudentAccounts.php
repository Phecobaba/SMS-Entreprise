<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Academics\Models\ClassArm;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Models\ParentProfile;
use App\Modules\Assignments\Models\ParentStudent;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use App\Modules\Core\Services\TemporaryCredentials;
use App\Modules\Core\Services\UsernameRules;
use App\Modules\Core\TenantContext;
use App\Modules\Payments\Services\FeeInvoicingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StudentAccounts
{
    public function create(array $data, User $actor): array
    {
        abort_unless($actor->can('create_students'), 403);
        ClassArm::findOrFail((int) $data['class_arm_id']);
        $username = app(UsernameRules::class)->validateAvailable($data['username']);
        $studentPassword = app(TemporaryCredentials::class)->generate();
        $parentPassword = ($data['parent_mode'] ?? 'none') === 'new'
            ? app(TemporaryCredentials::class)->generate() : null;
        $parentUsername = $parentPassword
            ? app(UsernameRules::class)->validateAvailable($data['parent_username']) : null;
        if ($parentUsername && $parentUsername === $username) {
            throw ValidationException::withMessages(['parent_username' => 'The parent and student need different usernames.']);
        }

        return DB::transaction(function () use ($data, $actor, $username, $studentPassword, $parentPassword, $parentUsername): array {
            $studentUser = $this->createUser($data['name'], $username, $data['email'] ?? null, $studentPassword, 'Student');
            $student = Student::create([
                'user_id' => $studentUser->id,
                'class_arm_id' => (int) $data['class_arm_id'],
                'status' => Student::STATUS_ACTIVE,
                'admission_date' => today(),
            ]);

            if (($data['registration_mode'] ?? 'automatic') === 'typed') {
                abort_unless($actor->can('manage_registration_numbers'), 403);
                app(RegistrationNumbers::class)->change($student, (string) $data['registration_number'], $actor);
            } else {
                app(RegistrationNumbers::class)->assignAutomatic($student, $actor);
            }

            $parentUser = null;
            if (($data['parent_mode'] ?? 'none') === 'new') {
                $parentUser = $this->createUser($data['parent_name'], $parentUsername, $data['parent_email'] ?? null, $parentPassword, 'Parent');
                $parent = ParentProfile::create(['user_id' => $parentUser->id]);
                ParentStudent::create(['parent_id' => $parent->id, 'student_id' => $student->id]);
            } elseif (($data['parent_mode'] ?? 'none') === 'existing') {
                $parent = ParentProfile::findOrFail((int) $data['parent_id']);
                ParentStudent::firstOrCreate(['parent_id' => $parent->id, 'student_id' => $student->id]);
            }

            app(FeeInvoicingService::class)->automaticallyInvoice($student, $actor);
            app(AuditRecorder::class)->record($actor, 'student_account_created', $student, [
                'parent_created' => (bool) $parentUser,
                'parent_linked' => ($data['parent_mode'] ?? 'none') !== 'none',
            ]);

            return [
                'student' => $student->refresh(),
                'student_user' => $studentUser,
                'student_password' => $studentPassword,
                'parent_user' => $parentUser,
                'parent_password' => $parentPassword,
            ];
        });
    }

    public function update(Student $student, array $data, User $actor): Student
    {
        abort_unless($actor->can('manage_student_accounts'), 403);
        $username = app(UsernameRules::class)->validateAvailable($data['username'], $student->user_id);
        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        if ($email && User::whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($student->user_id)->exists()) {
            throw ValidationException::withMessages(['email' => 'That email is already in use.']);
        }

        DB::transaction(function () use ($student, $data, $actor, $username, $email): void {
            $user = $student->user()->lockForUpdate()->firstOrFail();
            $before = $user->only(['name', 'username', 'email']);
            $user->forceFill([
                'name' => trim($data['name']),
                'username' => $username,
                'email' => $email,
                'email_verified_at' => $email ? now() : null,
            ])->save();
            app(AuditRecorder::class)->record($actor, 'student_identity_changed', $student, [
                'before' => $before,
                'after' => $user->only(['name', 'username', 'email']),
            ]);
        });

        return $student->refresh();
    }

    public function resetStudent(Student $student, User $actor): array
    {
        abort_unless($actor->can('manage_student_accounts'), 403);

        return app(TemporaryCredentials::class)->reset($student->user, $actor, 'student_password_reset');
    }

    public function resetParent(ParentProfile $parent, User $actor): array
    {
        abort_unless($actor->can('manage_student_accounts'), 403);

        return app(TemporaryCredentials::class)->reset($parent->user, $actor, 'parent_password_reset');
    }

    private function createUser(string $name, string $username, ?string $email, string $password, string $roleName): User
    {
        $user = new User;
        $user->fill([
            'name' => trim($name),
            'username' => $username,
            'email' => filled($email) ? strtolower(trim($email)) : null,
            'must_change_password' => true,
            'status' => 'active',
        ]);
        $user->setRawAttributes(array_merge($user->getAttributes(), [
            'password' => app(TemporaryCredentials::class)->hash($password),
            'email_verified_at' => filled($email) ? now() : null,
        ]));
        $user->save();
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->syncWithoutDetaching([$role->id => ['school_id' => app(TenantContext::class)->id()]]);

        return $user;
    }
}
