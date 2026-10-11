<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Question extends Model
{
    use BelongsToTenant;

    protected $fillable = ['exam_id', 'text', 'marks', 'explanation', 'position'];

    public function options(): HasMany
    {
        return $this->hasMany(QuestionOption::class);
    }
}
