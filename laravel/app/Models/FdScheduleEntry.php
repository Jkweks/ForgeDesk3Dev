<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdScheduleEntry extends Model
{
    protected $table = 'fd_schedule_entries';

    protected $fillable = ['user_id', 'work_order_id', 'start_date', 'end_date', 'start_quarter', 'end_quarter', 'note'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'user_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }
}
