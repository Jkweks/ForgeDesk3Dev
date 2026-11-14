<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimateAdjustment extends Model
{
    public $timestamps = false;

    protected $fillable = ['bid_estimate_id', 'section', 'row', 'description', 'rate', 'base', 'amount'];
}
