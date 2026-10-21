<?php

namespace App\Modules\CBT\Services;

use App\Modules\CBT\Models\Exam;

class ExamUsage
{
    public function countForTerm(int $termId): int
    {
        return Exam::where('term_id', $termId)->count();
    }
}
