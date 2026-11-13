<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admin-defined configurator rule: when `conditions` hold for an opening, `actions` alter its
 * generated BOMs / prep values. Evaluated by App\Services\Configurator\ConfiguratorRuleEngine.
 */
class ConfiguratorRule extends Model
{
    protected $fillable = ['name', 'notes', 'enabled', 'sort_order', 'conditions', 'actions'];

    protected $casts = [
        'enabled' => 'boolean',
        'sort_order' => 'integer',
        'conditions' => 'array',
        'actions' => 'array',
    ];
}
