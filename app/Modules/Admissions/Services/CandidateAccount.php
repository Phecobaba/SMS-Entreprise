<?php

namespace App\Modules\Admissions\Services;

use App\Modules\Admissions\Models\Candidate;

class CandidateAccount
{
    public function existsForUser(int $userId): bool
    {
        return Candidate::where('user_id', $userId)->exists();
    }
}
