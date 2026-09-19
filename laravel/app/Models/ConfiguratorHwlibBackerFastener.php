<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibBackerFastener extends Model
{
    use HasFactory;

    protected $fillable = ['backer_id', 'fastener_id', 'qty', 'notes', 'sort_order'];

    protected $casts = ['qty' => 'decimal:3', 'sort_order' => 'integer'];

    public function backer()
    {
        return $this->belongsTo(ConfiguratorHwlibBacker::class, 'backer_id');
    }

    public function fastener()
    {
        return $this->belongsTo(ConfiguratorHwlibFastener::class, 'fastener_id');
    }
}
