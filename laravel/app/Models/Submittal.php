<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submittal extends Model
{
    protected $fillable = ['business_job_id', 'number', 'name', 'spec_section', 'kind', 'status', 'revision_number', 'sent_at', 'returned_at', 'notes', 'created_by'];

    public const STATUSES = ['pending', 'approved', 'approved_as_noted', 'revise_resubmit', 'rejected', 'for_record_only'];

    public const KINDS = ['docs', 'sample', 'docs_samples'];

    protected $casts = ['sent_at' => 'date', 'returned_at' => 'date'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id')->withoutGlobalScopes();
    }

    public function revisions()
    {
        return $this->hasMany(SubmittalRevision::class)->orderBy('revision_number');
    }

    public function responses()
    {
        return $this->hasMany(SubmittalResponse::class)->orderByDesc('id');
    }

    public function samples()
    {
        return $this->hasMany(SubmittalSample::class)->orderBy('id');
    }

    public function purchaseOrders()
    {
        return $this->belongsToMany(PurchaseOrder::class, 'submittal_purchase_orders');
    }
}
