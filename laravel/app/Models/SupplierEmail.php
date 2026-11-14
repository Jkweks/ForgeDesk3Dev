<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierEmail extends Model
{
    protected $fillable = ['supplier_id', 'label', 'address', 'sort_order'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
