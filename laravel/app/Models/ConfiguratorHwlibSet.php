<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibSet extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'notes', 'is_pair'];

    protected $casts = ['is_pair' => 'boolean'];

    public function setItems()
    {
        return $this->hasMany(ConfiguratorHwlibSetItem::class, 'set_id');
    }
}
