<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An admin-defined integrity rule run against every uploaded cut-list CSV (see CutlistRuleEngine). */
class CutlistRule extends Model
{
    public const KINDS = ['row', 'count', 'companion'];

    public const SEVERITIES = ['error', 'warning'];

    protected $fillable = ['name', 'description', 'kind', 'severity', 'enabled', 'definition', 'message', 'sort_order', 'created_by'];

    protected $casts = ['enabled' => 'boolean', 'definition' => 'array'];
}
