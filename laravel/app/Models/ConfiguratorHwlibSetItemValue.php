<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibSetItemValue extends Model
{
    use HasFactory;

    protected $fillable = ['set_item_id', 'variable_id', 'value_text'];

    public function setItem()
    {
        return $this->belongsTo(ConfiguratorHwlibSetItem::class, 'set_item_id');
    }

    public function variable()
    {
        return $this->belongsTo(ConfiguratorHwlibVariable::class, 'variable_id');
    }
}
