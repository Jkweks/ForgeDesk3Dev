<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibItemValue extends Model
{
    use HasFactory;

    protected $fillable = ['item_id', 'variable_id', 'value_text'];

    public function item()
    {
        return $this->belongsTo(ConfiguratorHwlibItem::class, 'item_id');
    }

    public function variable()
    {
        return $this->belongsTo(ConfiguratorHwlibVariable::class, 'variable_id');
    }
}
