<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityReportFile extends Model
{
    protected $table = 'quality_report_files';

    protected $fillable = [
        'quality_report_id', 'original_name', 'file_path', 'file_size', 'file_mime', 'uploaded_by',
    ];

    public function qualityReport(): BelongsTo
    {
        return $this->belongsTo(QualityReport::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
