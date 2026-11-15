<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ActivityLogPresenter
{
    /** @return list<string> */
    public function modules(): array
    {
        return ['Staff access', 'Staff HR', 'Sign-in', 'Students', 'Academics', 'Admissions', 'Results', 'Attendance', 'Assignments', 'E-Notes', 'Payments', 'CBT', 'Other'];
    }

    public function module(AuditLog $entry): string
    {
        return filled($entry->module) ? $entry->module : $this->moduleForAction($entry->action);
    }

    public function description(AuditLog $entry): string
    {
        if (filled($entry->description)) {
            return $entry->description;
        }

        return Str::of($entry->action)->replace(['.', '_'], ' ')->headline()->append('.')->toString();
    }

    public function constrainToModule(Builder $query, string $module): void
    {
        $patterns = $this->patterns()[$module] ?? [];
        $query->where(function (Builder $filtered) use ($module, $patterns): void {
            $filtered->where('module', $module);
            if ($patterns !== []) {
                $filtered->orWhere(function (Builder $legacy) use ($patterns): void {
                    $legacy->whereNull('module')->where(function (Builder $actions) use ($patterns): void {
                        foreach ($patterns as $pattern) {
                            $actions->orWhere('action', 'like', $pattern);
                        }
                    });
                });
            }
        });
    }

    private function moduleForAction(string $action): string
    {
        foreach ($this->patterns() as $module => $patterns) {
            foreach ($patterns as $pattern) {
                if (Str::is(str_replace('%', '*', $pattern), $action)) {
                    return $module;
                }
            }
        }

        return 'Other';
    }

    /** @return array<string, list<string>> */
    private function patterns(): array
    {
        return [
            'Staff access' => ['staff_account_%', 'staff_access_%', 'staff_password_%', 'staff_sign_in_%'],
            'Staff HR' => ['staff_employment_%', 'staff_exited'],
            'Sign-in' => ['password_%', 'admin_password_%'],
            'Students' => ['student_%', 'registration_number_%'],
            'Academics' => ['academic_%', 'class_%', 'subject_%', 'teacher_subject_%', 'form_teacher_%'],
            'Admissions' => ['admission_%'],
            'Results' => ['assessment_%', 'grading_%', 'grade_band_%', 'result_%', 'report_card_%', 'proprietor_comment_%', 'teacher_score_%'],
            'Attendance' => ['attendance.%'],
            'Assignments' => ['assignment_%', 'parent_account_%'],
            'E-Notes' => ['enote_%'],
            'Payments' => ['payment_%', 'payment.%', 'manual_payment.%', 'invoice.%', 'fee_%'],
            'CBT' => ['cbt_%', 'exam_%'],
        ];
    }
}
