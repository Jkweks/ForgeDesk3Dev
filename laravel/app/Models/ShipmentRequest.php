<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentRequest extends Model
{
    protected $fillable = ['requested_date', 'job_id', 'custom_job', 'destination', 'shipment_type_ids', 'notes', 'status',
        'requested_by', 'decided_by', 'decided_at', 'decision_note', 'shipment_id'];

    protected $casts = ['requested_date' => 'date:Y-m-d', 'shipment_type_ids' => 'array', 'decided_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'job_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
