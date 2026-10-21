<?php

namespace App\Modules\Results\Services;

use App\Modules\Results\Models\Result;
use App\Modules\Results\Models\ResultCard;
use App\Modules\Results\Models\ResultCardCommentAudit;
use App\Modules\Results\Models\ResultTemplate;
use App\Modules\Results\Models\TermDefaultComment;

class ResultUsage
{
    public function countForArms(array $armIds): int
    {
        return $armIds === [] ? 0 : Result::whereIn('class_arm_id', $armIds)->count();
    }

    public function usageForArm(int $armId): array
    {
        return [
            'results' => Result::where('class_arm_id', $armId)->count(),
            'result_cards' => ResultCard::where('class_arm_id', $armId)->count(),
            'terms' => Result::where('class_arm_id', $armId)->distinct()->count('term_id'),
        ];
    }

    public function usageForTerm(int $termId): array
    {
        return [
            'results' => Result::where('term_id', $termId)->count(),
            'result_cards' => ResultCard::where('term_id', $termId)->count(),
            'default_comments' => TermDefaultComment::where('term_id', $termId)->count(),
            'comment_audits' => ResultCardCommentAudit::where('term_id', $termId)->count(),
        ];
    }

    public function templateCountForLevel(int $levelId): int
    {
        return ResultTemplate::where('academic_level_id', $levelId)->count();
    }
}
