<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibLinkValue extends Model
{
    use HasFactory;

    protected $fillable = ['link_id', 'variable_id', 'value_text'];

    public function link()
    {
        return $this->belongsTo(ConfiguratorHwlibLink::class, 'link_id');
    }

    public function variable()
    {
        return $this->belongsTo(ConfiguratorHwlibVariable::class, 'variable_id');
    }
}
