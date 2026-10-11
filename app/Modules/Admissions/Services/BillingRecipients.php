<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\Candidate;
use App\Modules\Admissions\Models\Student;
use App\Modules\Assignments\Services\ParentAccountDirectory;
use App\Modules\Core\Models\User;

class BillingRecipients
{
    public function emailFor(?int $candidateId, ?int $studentId): ?string
    {
        return $candidateId
            ? Candidate::findOrFail($candidateId)->user()->firstOrFail()->email
            : Student::findOrFail($studentId)->user()->firstOrFail()->email;
    }

    public function userIdFor(?int $candidateId, ?int $studentId): int
    {
        return (int) ($candidateId
            ? Candidate::findOrFail($candidateId)->user_id
            : Student::findOrFail($studentId)->user_id);
    }

    public function owns(?int $candidateId, ?int $studentId, User $actor): bool
    {
        if ($candidateId) {
            return Candidate::whereKey($candidateId)->where('user_id', $actor->id)->exists();
        }

        return $studentId !== null && (
            Student::whereKey($studentId)->where('user_id', $actor->id)->exists()
            || app(ParentAccountDirectory::class)->isWard($studentId, $actor->id)
        );
    }

    public function confirmRegistration(int $candidateId, ?User $actor): void
    {
        app(AdmissionPipeline::class)->confirmPayment(Candidate::findOrFail($candidateId), $actor);
    }
}
