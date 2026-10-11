<?php

namespace App\Modules\Admissions\Models;

use App\Modules\Academics\Models\AcademicLevel;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Candidate extends Model
{
    use BelongsToTenant;

    protected $fillable = ['user_id', 'applied_level_id', 'name', 'phone', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appliedLevel(): BelongsTo
    {
        return $this->belongsTo(AcademicLevel::class, 'applied_level_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(CandidateStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(CandidateChecklist::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(AdmissionOffer::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }
}
