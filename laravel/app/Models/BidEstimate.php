<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimate extends Model
{
    protected $fillable = [
        'business_job_id', 'pm_file_id', 'sheet', 'file_sha256', 'parser_revision',
        'contract_amount', 'line_count', 'review_count', 'data', 'imported_by',
    ];

    protected $casts = ['data' => 'array', 'contract_amount' => 'decimal:2'];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }
}
