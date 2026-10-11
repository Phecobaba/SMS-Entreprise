<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TermDefaultComment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['term_id', 'section_id', 'comment_type', 'comment_text'];
}
