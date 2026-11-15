<?php

namespace App\Modules\Payments\Services;

use App\Modules\Payments\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptPdf
{
    public function bytes(Receipt $receipt): string
    {
        return Pdf::loadView('payments.receipt-pdf', ['receipt' => $receipt])->setPaper('a4')->output();
    }
}
