<?php

namespace App\Modules\CBT\Services;

use App\Modules\CBT\Models\ExamType;
use App\Modules\Core\Models\User;
use App\Modules\Core\Services\AuditRecorder;
use Illuminate\Validation\ValidationException;

class ExamTypeManager
{
    public function save(?ExamType $type, array $data, User $actor): ExamType
    {
        $this->assertAdmin($actor);
        $values = [
            'name' => trim($data['name']),
            'feeds_results' => (bool) ($data['feeds_results'] ?? false),
            'active' => (bool) ($data['active'] ?? false),
        ];
        $duplicate = ExamType::whereRaw('LOWER(name) = ?', [mb_strtolower($values['name'])]);
        if ($type) {
            $duplicate->whereKeyNot($type->id);
        }
        if ($duplicate->exists()) {
            throw ValidationException::withMessages(['name' => 'An exam type with this name already exists.']);
        }

        $before = $type?->only(['name', 'feeds_results', 'active']) ?? [];
        $type ??= new ExamType;
        $type->fill($values)->save();
        app(AuditRecorder::class)->record(
            $actor,
            $before === [] ? 'cbt_exam_type_created' : 'cbt_exam_type_updated',
            $type,
            ['before' => $before, 'after' => $type->only(['name', 'feeds_results', 'active'])],
            'CBT',
            $before === [] ? 'Exam type created.' : 'Exam type updated.',
        );

        return $type;
    }

    public function delete(ExamType $type, User $actor): void
    {
        $this->assertAdmin($actor);
        $count = $type->exams()->count();
        if ($count > 0) {
            throw ValidationException::withMessages(['type' => "This exam type is used by {$count} exam".($count === 1 ? '' : 's').'. Deactivate it instead.']);
        }
        app(AuditRecorder::class)->record(
            $actor,
            'cbt_exam_type_deleted',
            $type,
            ['name' => $type->name],
            'CBT',
            'Unused exam type deleted.',
        );
        $type->delete();
    }

    private function assertAdmin(User $actor): void
    {
        abort_unless($actor->can('manage_cbt') && $actor->can('view_all_sections'), 403);
    }
}
