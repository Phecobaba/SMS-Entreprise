<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model
{
    use BelongsToTenant;

    protected $fillable = ['question_id', 'text', 'is_correct'];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
