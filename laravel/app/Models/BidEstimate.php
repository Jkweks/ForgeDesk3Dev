<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimate extends Model
{
    protected $fillable = [
        'business_job_id', 'pm_file_id', 'sheet', 'file_sha256', 'parser_revision',
        'project_name', 'gc', 'architect', 'due',
        'contract_amount', 'dollars_per_sf_glass', 'material', 'tax', 'large_sub', 'other', 'labor', 'material_overhead',
        'imported_by',
    ];

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }

    public function lines()
    {
        return $this->hasMany(BidEstimateLine::class)->orderBy('row');
    }

    public function adjustments()
    {
        return $this->hasMany(BidEstimateAdjustment::class)->orderBy('row');
    }

    public function warnings()
    {
        return $this->hasMany(BidEstimateWarning::class);
    }
}
