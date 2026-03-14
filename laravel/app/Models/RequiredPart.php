<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequiredPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_product_id',
        'required_product_id',
        'quantity',
        'finish_policy',
        'specific_finish',
        'sort_order',
        'is_optional',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'is_optional' => 'boolean',
    ];

    /**
     * Get the parent product (the product that requires other parts)
     */
    public function parentProduct()
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    /**
     * Get the required product (the part that is needed)
     */
    public function requiredProduct()
    {
        return $this->belongsTo(Product::class, 'required_product_id');
    }
}
