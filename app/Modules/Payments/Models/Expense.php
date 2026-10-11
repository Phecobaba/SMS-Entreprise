<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use BelongsToTenant;

    protected $fillable = ['date', 'category', 'amount_kobo', 'vendor', 'receipt_file_path', 'approved_by', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount_kobo' => 'integer'];
    }
}
