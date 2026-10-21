<?php

namespace App\Modules\Payments\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class InvoiceGenerationRun extends Model
{
    use BelongsToTenant;

    protected $fillable = ['term_id', 'scope_type', 'scope_id', 'due_date', 'students_in_scope', 'created_count', 'skipped_count', 'no_class_count', 'no_schedule_count', 'created_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }
}
