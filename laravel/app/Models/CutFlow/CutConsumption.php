<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

/** One cut's use of stock, as a fraction of a stock length (see CutConsumptionService). */
class CutConsumption extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = ['cut_log_entry_id', 'cut_job_id', 'product_id', 'stock_fraction'];

    protected $casts = ['stock_fraction' => 'decimal:4'];
}
