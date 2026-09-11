<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobDocument extends Model
{
    protected $table = 'job_documents';

    /** Fixed document types + their display labels. */
    public const TYPES = [
        'sof' => 'SOF',
        'ez_estimate' => 'EZ Estimate',
        'purchase_order' => 'Purchase Order',
        'other' => 'Other',
    ];

    protected $fillable = [
        'business_job_id', 'doc_type', 'label', 'original_name',
        'file_path', 'file_size', 'file_mime', 'uploaded_by',
    ];

    public function businessJob(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
