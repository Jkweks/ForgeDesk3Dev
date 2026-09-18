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
        'business_job_id', 'job_reservation_id', 'doc_type', 'label', 'original_name',
        'file_path', 'file_size', 'file_mime', 'uploaded_by', 'archived', 'archived_at',
    ];

    protected $casts = [
        'archived' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function businessJob(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(JobReservation::class, 'job_reservation_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Not archived — the default working set (see JobDocumentController::index()). */
    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }

    public function archive(): void
    {
        if (! $this->archived) {
            $this->update(['archived' => true, 'archived_at' => now()]);
        }
    }
}
