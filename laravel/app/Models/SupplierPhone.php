<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPhone extends Model
{
    protected $fillable = ['supplier_id', 'label', 'number', 'sort_order'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
