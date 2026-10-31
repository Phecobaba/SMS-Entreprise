<?php

namespace App\Modules\ENotes\Models;

use App\Modules\Academics\Models\SchoolClass;
use App\Modules\Academics\Models\Subject;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ENote extends Model
{
    use BelongsToTenant;

    protected $table = 'enotes';

    protected $fillable = ['subject_id', 'class_id', 'title', 'file_path', 'link', 'uploaded_by'];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
