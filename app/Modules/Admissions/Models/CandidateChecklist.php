<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateChecklist extends Model
{
    use BelongsToTenant;

    protected $table = 'candidate_checklist';

    protected $fillable = ['candidate_id', 'checklist_item_id', 'is_complete', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['is_complete' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(AdmissionChecklistItem::class, 'checklist_item_id');
    }
}
