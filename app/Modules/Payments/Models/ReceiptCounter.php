<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ReceiptCounter extends Model
{
    use BelongsToTenant;

    protected $fillable = ['issue_year', 'next_number'];

    protected function casts(): array
    {
        return ['issue_year' => 'integer', 'next_number' => 'integer'];
    }
}
