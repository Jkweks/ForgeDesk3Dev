<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FdJobStep extends Model
{
    protected $table = 'fd_job_steps';

    protected $fillable = [
        'business_job_id', 'name', 'sort_order', 'status', 'completed_by_id', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'completed_by_id');
    }
}
