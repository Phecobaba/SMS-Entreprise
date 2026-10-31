<?php

namespace App\Modules\Academics\Services;

use App\Modules\Academics\Models\AcademicTerm;
use Carbon\CarbonInterface;

class AcademicCalendar
{
    public function currentTerm(): ?AcademicTerm
    {
        $today = today('Africa/Lagos');

        return $this->termContaining($today);
    }

    public function termForInvoicing(): ?AcademicTerm
    {
        $today = today('Africa/Lagos');

        return $this->termContaining($today) ?? AcademicTerm::with('session')
            ->whereDate('start_date', '>', $today)
            ->orderBy('start_date')
            ->orderBy('id')
            ->first();
    }

    public function termForSummary(): ?AcademicTerm
    {
        $today = today('Africa/Lagos');

        return $this->termContaining($today) ?? AcademicTerm::with('session')
            ->whereDate('end_date', '<', $today)
            ->orderByDesc('end_date')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }

    private function termContaining(CarbonInterface $date): ?AcademicTerm
    {
        return AcademicTerm::with('session')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();
    }
}
