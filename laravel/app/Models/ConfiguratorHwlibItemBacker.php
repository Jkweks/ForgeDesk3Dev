<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibItemBacker extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id', 'side', 'series', 'pn', 'description', 'qty', 'notes', 'sort_order', 'backer_id',
    ];

    protected $casts = ['qty' => 'decimal:3', 'sort_order' => 'integer'];

    public function item()
    {
        return $this->belongsTo(ConfiguratorHwlibItem::class, 'item_id');
    }

    public function backer()
    {
        return $this->belongsTo(ConfiguratorHwlibBacker::class, 'backer_id');
    }
}
