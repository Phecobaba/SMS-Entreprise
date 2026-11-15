<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Role;
use App\Modules\Core\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DefaultRoleCatalog
{
    /** @return array<string, list<string>> */
    public function definitions(): array
    {
        return [
            'School Admin' => [
                'view_dashboard', 'manage_academics', 'manage_school_profile',
                'manage_admissions', 'manage_exam_sessions', 'override_admission_checklist',
                'manage_result_settings', 'enter_results', 'approve_results',
                'add_principal_comment', 'add_proprietor_comment', 'manage_cbt',
                'view_cbt_results', 'mark_attendance', 'manage_attendance',
                'view_attendance_alerts', 'manage_assignments', 'mark_assignments',
                'manage_enotes', 'upload_enotes', 'manage_payments',
                'approve_payments', 'manage_fee_schedule', 'manage_payment_gateway',
                'manage_staff_records', 'view_staff_salary',
                'view_staff_disciplinary', 'view_all_sections', 'manage_promotions',
                'create_staff', 'manage_staff_access', 'view_sign_in_rules',
                'view_activity_log',
                'view_students', 'create_students', 'manage_student_accounts',
                'manage_registration_numbers', 'change_registration_numbers',
                'view_parents', 'view_all_parents', 'manage_parent_accounts',
            ],
            'Head' => [
                'view_dashboard', 'approve_results', 'add_principal_comment',
                'manage_attendance', 'view_attendance_alerts',
                'manage_section_classes', 'manage_section_levels',
                'manage_section_arms', 'manage_section_subjects',
                'manage_section_teacher_assignments', 'manage_section_result_settings',
                'manage_promotions', 'change_registration_numbers',
                'view_section_staff', 'record_section_discipline',
                'manage_cbt', 'view_cbt_results', 'manage_exam_sessions',
                'view_parents',
            ],
            'Teacher' => [
                'view_dashboard', 'enter_results', 'mark_attendance',
                'mark_assignments', 'upload_enotes', 'view_cbt_results',
            ],
            'Bursar' => [
                'view_dashboard', 'manage_payments', 'manage_fee_schedule',
                'view_parents', 'view_all_parents',
            ],
            'Student' => [],
            'Parent' => [],
        ];
    }

    /** @return Collection<string, Role> */
    public function provision(): Collection
    {
        $permissions = $this->permissionIds();
        $schoolId = app(TenantContext::class)->id();

        return collect($this->definitions())->mapWithKeys(function (array $names, string $roleName) use ($permissions, $schoolId): array {
            $role = Role::firstOrCreate(['name' => $roleName]);
            if ($names !== []) {
                $role->permissions()->syncWithoutDetaching(
                    collect($names)->mapWithKeys(fn (string $name): array => [
                        $permissions[$name] => ['school_id' => $schoolId],
                    ])->all(),
                );
            }

            return [$roleName => $role];
        });
    }

    public function addMissingPermissionsToExistingRoles(): void
    {
        if (! DB::table('roles')->whereIn('name', array_keys($this->definitions()))->exists()) {
            return;
        }

        $permissions = $this->permissionIds();

        foreach ($this->definitions() as $roleName => $names) {
            foreach (DB::table('roles')->where('name', $roleName)->get(['id', 'school_id']) as $role) {
                foreach ($names as $name) {
                    DB::table('role_permissions')->insertOrIgnore([
                        'school_id' => $role->school_id,
                        'role_id' => $role->id,
                        'permission_id' => $permissions[$name],
                    ]);
                }
            }
        }
    }

    /** @return array<string, int> */
    private function permissionIds(): array
    {
        return collect($this->definitions())->flatten()->unique()->mapWithKeys(function (string $name): array {
            $id = DB::table('permissions')->where('name', $name)->value('id')
                ?: DB::table('permissions')->insertGetId([
                    'name' => $name,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            return [$name => (int) $id];
        })->all();
    }
}
