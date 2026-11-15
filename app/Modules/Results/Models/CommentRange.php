<?php

namespace App\Modules\Results\Models;

use App\Modules\Academics\Models\Section;
use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentRange extends Model
{
    use BelongsToTenant;

    protected $fillable = ['section_id', 'comment_type', 'min_average', 'max_average', 'comment_text'];

    protected function casts(): array
    {
        return ['min_average' => 'integer', 'max_average' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
