<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkOrderRequest extends Model
{
    protected $fillable = [
        'business_job_id', 'release_code', 'requested_due_date', 'scope', 'notes', 'status',
        'requested_by', 'decided_by', 'decided_at', 'decision_note', 'fd_work_order_id',
    ];

    protected $casts = ['requested_due_date' => 'date:Y-m-d', 'decided_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id')->withoutGlobalScopes();
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function files()
    {
        return $this->hasMany(WorkOrderRequestFile::class)->with('file:id,original_name,size');
    }

    public function workOrder()
    {
        return $this->belongsTo(FdWorkOrder::class, 'fd_work_order_id');
    }
}
