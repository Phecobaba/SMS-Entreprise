<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\ClassArm;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClassArmDirectory
{
    public function all(): Collection
    {
        return ClassArm::with('schoolClass.level')->orderBy('name')->get();
    }

    public function assertExists(int $armId): void
    {
        if (! ClassArm::whereKey($armId)->exists()) {
            throw ValidationException::withMessages(['class_arm_id' => 'Select a class arm in this school.']);
        }
    }

    public function label(int $armId): string
    {
        $arm = ClassArm::with('schoolClass')->find($armId);

        return trim(($arm?->schoolClass?->name ?? 'Class').' '.($arm?->name ?? ''));
    }

    public function labels(array $armIds): array
    {
        return ClassArm::with('schoolClass')->whereIn('id', $armIds)->get()->mapWithKeys(
            fn (ClassArm $arm) => [$arm->id => trim(($arm->schoolClass?->name ?? 'Class').' '.$arm->name)]
        )->all();
    }
}
