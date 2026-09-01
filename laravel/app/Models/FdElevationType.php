<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdElevationType extends Model
{
    protected $table = 'fd_elevation_types';

    protected $fillable = ['name', 'color', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];

    public function stageTemplates(): HasMany
    {
        return $this->hasMany(FdStageTemplate::class, 'elevation_type_id')->orderBy('sort_order');
    }

    public function templateSets(): HasMany
    {
        return $this->hasMany(FdStageTemplateSet::class, 'elevation_type_id')->orderBy('sort_order');
    }

    public function elevations(): HasMany
    {
        return $this->hasMany(FdWoElevation::class, 'elevation_type_id');
    }
}
