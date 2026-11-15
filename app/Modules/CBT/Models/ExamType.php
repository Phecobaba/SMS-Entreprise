<?php

namespace App\Modules\CBT\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamType extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'feeds_results', 'active'];

    protected function casts(): array
    {
        return ['feeds_results' => 'boolean', 'active' => 'boolean'];
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }
}
