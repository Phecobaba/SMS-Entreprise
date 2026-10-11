<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ExamAnswer extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_submission_id', 'question_id', 'selected_option_id', 'flagged'];

    protected function casts(): array
    {
        return ['flagged' => 'boolean'];
    }
}
