<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\School;
use App\Modules\Core\Models\SchoolSetting;
use App\Modules\Core\Models\User;
use App\Modules\Core\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BootstrapSchoolAdmin extends Command
{
    protected $signature = 'oakfield:bootstrap-admin {school-name} {admin-name} {email}';

    protected $description = 'Create the configured school and its first administrator';

    public function handle(TenantContext $context): int
    {
        if (config('tenancy.mode') !== 'single_school') {
            $this->error('This command only supports single-school mode.');

            return self::FAILURE;
        }

        $schoolId = (int) config('tenancy.default_school_id');
        $email = strtolower((string) $this->argument('email'));

        if ($schoolId < 1 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid configured school ID and administrator email.');

            return self::FAILURE;
        }

        $password = $this->secret('Administrator password (at least 12 characters)');

        if (! is_string($password) || strlen($password) < 12) {
            $this->error('The administrator password must contain at least 12 characters.');

            return self::FAILURE;
        }

        $context->set($schoolId);

        try {
            DB::transaction(function () use ($schoolId, $email, $password): void {
                $school = School::find($schoolId);

                if (! $school) {
                    $school = new School;
                    $school->id = $schoolId;
                    $school->name = (string) $this->argument('school-name');
                    $school->save();
                }

                SchoolSetting::firstOrCreate([]);
                $permission = Permission::firstOrCreate(['name' => 'view_dashboard']);
                $academicsPermission = Permission::firstOrCreate(['name' => 'manage_academics']);
                $profilePermission = Permission::firstOrCreate(['name' => 'manage_school_profile']);
                $admissionsPermission = Permission::firstOrCreate(['name' => 'manage_admissions']);
                $overridePermission = Permission::firstOrCreate(['name' => 'override_admission_checklist']);
                $resultSettingsPermission = Permission::firstOrCreate(['name' => 'manage_result_settings']);
                $resultEntryPermission = Permission::firstOrCreate(['name' => 'enter_results']);
                $resultApprovalPermission = Permission::firstOrCreate(['name' => 'approve_results']);
                $principalCommentPermission = Permission::firstOrCreate(['name' => 'add_principal_comment']);
                $proprietorCommentPermission = Permission::firstOrCreate(['name' => 'add_proprietor_comment']);
                $cbtPermission = Permission::firstOrCreate(['name' => 'manage_cbt']);
                $cbtApprovalPermission = Permission::firstOrCreate(['name' => 'approve_cbt_results']);
                $attendanceMarkPermission = Permission::firstOrCreate(['name' => 'mark_attendance']);
                $attendanceManagePermission = Permission::firstOrCreate(['name' => 'manage_attendance']);
                $attendanceAlertsPermission = Permission::firstOrCreate(['name' => 'view_attendance_alerts']);
                $assignmentManagePermission = Permission::firstOrCreate(['name' => 'manage_assignments']);
                $assignmentMarkPermission = Permission::firstOrCreate(['name' => 'mark_assignments']);
                $enoteManagePermission = Permission::firstOrCreate(['name' => 'manage_enotes']);
                $enoteUploadPermission = Permission::firstOrCreate(['name' => 'upload_enotes']);
                $paymentsPermission = Permission::firstOrCreate(['name' => 'manage_payments']);
                $feeSchedulePermission = Permission::firstOrCreate(['name' => 'manage_fee_schedule']);
                $staffPermission = Permission::firstOrCreate(['name' => 'manage_staff_records']);
                $salaryPermission = Permission::firstOrCreate(['name' => 'view_staff_salary']);
                $disciplinePermission = Permission::firstOrCreate(['name' => 'view_staff_disciplinary']);
                $allSectionsPermission = Permission::firstOrCreate(['name' => 'view_all_sections']);
                $role = Role::firstOrCreate(['name' => 'School Admin']);
                $role->permissions()->syncWithoutDetaching([$permission->id, $academicsPermission->id, $profilePermission->id, $admissionsPermission->id, $overridePermission->id, $resultSettingsPermission->id, $resultEntryPermission->id, $resultApprovalPermission->id, $principalCommentPermission->id, $proprietorCommentPermission->id, $cbtPermission->id, $cbtApprovalPermission->id, $attendanceMarkPermission->id, $attendanceManagePermission->id, $attendanceAlertsPermission->id, $assignmentManagePermission->id, $assignmentMarkPermission->id, $enoteManagePermission->id, $enoteUploadPermission->id, $paymentsPermission->id, $feeSchedulePermission->id, $staffPermission->id, $salaryPermission->id, $disciplinePermission->id, $allSectionsPermission->id]);

                $user = User::firstOrCreate(
                    ['email' => $email],
                    ['name' => (string) $this->argument('admin-name'), 'password' => $password, 'status' => 'active'],
                );

                if ($user->status !== 'active') {
                    throw new RuntimeException('The existing administrator account is inactive.');
                }

                $user->roles()->syncWithoutDetaching([$role->id]);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            $context->clear();
        }

        $this->info('School administrator is ready to sign in.');

        return self::SUCCESS;
    }
}
