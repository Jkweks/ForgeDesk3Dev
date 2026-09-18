<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdElevationType extends Model
{
    protected $table = 'fd_elevation_types';

    protected $fillable = ['name', 'color', 'sort_order', 'active', 'aliases', 'standard_joint_count'];

    protected $casts = ['active' => 'boolean', 'aliases' => 'array', 'standard_joint_count' => 'integer'];

    /**
     * Every string that should resolve a work-order import row to this type:
     * its canonical name plus any configured aliases, trimmed + lower-cased and
     * de-duplicated. The name is always included, so exact-name matching keeps
     * working even when no aliases are set.
     *
     * @return list<string>
     */
    public function matchTerms(): array
    {
        return collect([$this->name])
            ->merge($this->aliases ?? [])
            ->map(fn ($s) => mb_strtolower(trim((string) $s)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

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
