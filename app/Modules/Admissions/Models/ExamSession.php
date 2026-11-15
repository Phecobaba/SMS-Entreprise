<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSession extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'starts_at', 'venue_note', 'is_in_person', 'section_id', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'is_in_person' => 'boolean'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
