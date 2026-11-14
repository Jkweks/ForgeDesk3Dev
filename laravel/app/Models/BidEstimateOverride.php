<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimateOverride extends Model
{
    protected $fillable = ['business_job_id', 'sheet', 'section', 'row', 'action', 'cost_code', 'note', 'created_by'];
}
