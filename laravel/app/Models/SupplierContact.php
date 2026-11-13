<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierContact extends Model
{
    protected $fillable = ['supplier_id', 'name', 'title', 'email', 'phone', 'is_preferred_rep', 'sort_order'];

    protected $casts = ['is_preferred_rep' => 'boolean'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
