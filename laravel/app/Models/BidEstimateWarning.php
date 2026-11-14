<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimateWarning extends Model
{
    public $timestamps = false;

    protected $fillable = ['bid_estimate_id', 'type', 'section', 'computed_from_lines', 'stated_in_sheet', 'note'];
}
