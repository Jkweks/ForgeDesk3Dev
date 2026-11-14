<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidEstimateLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['bid_estimate_id', 'section', 'row', 'who', 'cost_code', 'additional_cost_code', 'description', 'qty', 'unit_price', 'total', 'confidence', 'review_reason'];
}
