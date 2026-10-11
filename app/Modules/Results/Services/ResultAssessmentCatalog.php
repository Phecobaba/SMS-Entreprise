<?php

namespace App\Modules\Results\Services;

use App\Modules\Results\Models\AssessmentComponent;

class ResultAssessmentCatalog
{
    public function maxScore(int $componentId): float
    {
        return (float) AssessmentComponent::findOrFail($componentId)->max_score;
    }

    public function options()
    {
        return AssessmentComponent::orderBy('name')->get(['id', 'name', 'max_score']);
    }
}
