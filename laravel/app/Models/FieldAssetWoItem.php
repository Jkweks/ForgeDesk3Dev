<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldAssetWoItem extends Model
{
    protected $table = 'field_asset_wo_items';

    protected $fillable = ['work_order_id', 'description', 'qty', 'unit_cost'];

    protected $casts = ['qty' => 'decimal:2', 'unit_cost' => 'decimal:2'];
}
