<?php

namespace App\Modules\Admissions\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class CandidateSlipPdf
{
    /** @param array<int, array<string, mixed>> $slips */
    public function render(array $slips): string
    {
        return Pdf::loadView('applications.credential-slips-pdf', ['slips' => $slips])
            ->setPaper('a4', 'portrait')
            ->output();
    }
}
