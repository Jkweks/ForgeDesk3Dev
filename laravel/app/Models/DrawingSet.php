<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingSet extends Model
{
    protected $fillable = ['business_job_id', 'pm_file_id', 'status', 'error', 'page_count', 'skipped_pages', 'set_finish', 'created_by', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id')->withoutGlobalScopes();
    }

    public function file()
    {
        return $this->belongsTo(PmFile::class, 'pm_file_id');
    }

    public function elevations()
    {
        return $this->hasMany(DrawingElevation::class)->orderBy('page')->orderBy('id');
    }

    public function warnings()
    {
        return $this->hasMany(DrawingSetWarning::class);
    }
}
