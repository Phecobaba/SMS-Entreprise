<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class CandidateStatusHistory extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'candidate_status_history';

    protected $fillable = ['candidate_id', 'from_status', 'to_status', 'changed_by', 'reason', 'is_override', 'changed_at'];

    protected function casts(): array
    {
        return ['is_override' => 'boolean', 'changed_at' => 'datetime'];
    }
}
