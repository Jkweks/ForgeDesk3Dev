<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierAlias extends Model
{
    protected $fillable = ['supplier_id', 'alias', 'alias_normalized', 'source'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Case/space/punctuation-insensitive key ("Perrybrg $" == "perrybrg"). */
    public static function normalize(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?? '';
    }
}
