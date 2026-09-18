<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per work-order status change (hold / release / completion), with the
 * office user's note. Mirrors {@see FdStageLog}.
 */
class FdWoStatusLog extends Model
{
    protected $table = 'fd_wo_status_log';

    public $timestamps = false;

    protected $fillable = ['work_order_id', 'user_id', 'from_status', 'to_status', 'note'];

    protected $casts = ['created_at' => 'datetime'];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
