<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorHwlibVariable extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'label', 'group_name', 'var_type', 'unit', 'options',
        'is_calculated', 'formula', 'default_value', 'notes', 'sort_order',
        'overrides_variable_id', 'side', 'show_in_report', 'is_inspection',
    ];

    protected $casts = [
        'options' => 'array',
        'is_calculated' => 'boolean',
        'sort_order' => 'integer',
        'show_in_report' => 'boolean',
        'is_inspection' => 'boolean',
    ];

    public function overriddenBy()
    {
        return $this->belongsTo(self::class, 'overrides_variable_id');
    }
}
