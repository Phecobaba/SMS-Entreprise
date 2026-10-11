<?php

namespace App\Modules\Results\Models;

use App\Modules\Core\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ResultCardCommentAudit extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'result_card_comment_audit';

    protected $fillable = ['result_card_id', 'term_id', 'section_id', 'user_id', 'comment_type', 'scope', 'comment_text', 'created_at'];
}
