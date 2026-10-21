<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicTerm;

class AcademicCalendar
{
    public function currentTerm(): ?AcademicTerm
    {
        $today = today('Africa/Lagos');

        return AcademicTerm::with('session')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }
}
