<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StorageLocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'type',
        'description',
        'parent_id',
        'path',
        'depth',
        'aisle',
        'bay',
        'level',
        'position',
        'capacity',
        'capacity_unit',
        'is_active',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'capacity' => 'decimal:2',
        'sort_order' => 'integer',
        'depth' => 'integer',
    ];

    protected $appends = ['has_children', 'full_path'];

    // Hierarchical relationships

    /**
     * Get the parent location
     */
    public function parent()
    {
        return $this->belongsTo(StorageLocation::class, 'parent_id');
    }

    /**
     * Get child locations
     */
    public function children()
    {
        return $this->hasMany(StorageLocation::class, 'parent_id')->ordered();
    }

    /**
     * Get all descendants (recursive)
     */
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    /**
     * Get all descendant IDs (flat array including self)
     */
    public function getDescendantIds($includeSelf = true)
    {
        $ids = $includeSelf ? [$this->id] : [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds(false));
        }

        return $ids;
    }

    /**
     * Get all ancestors
     */
    public function ancestors()
    {
        $ancestors = collect([]);
        $parent = $this->parent;

        while ($parent) {
            $ancestors->prepend($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get inventory locations that use this storage location
     */
    public function inventoryLocations()
    {
        return $this->hasMany(InventoryLocation::class, 'storage_location_id');
    }

    /**
     * Check if location has children
     */
    public function getHasChildrenAttribute()
    {
        return $this->children()->count() > 0;
    }

    /**
     * Get full path as string (e.g., "Aisle A > Rack 1 > Shelf 2")
     */
    public function getFullPathAttribute()
    {
        $pathNames = $this->ancestors()->pluck('name')->push($this->name);
        return $pathNames->implode(' > ');
    }

    /**
     * Get full address of the location
     */
    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->aisle ? "Aisle {$this->aisle}" : null,
            $this->bay ? "Bay {$this->bay}" : null,
            $this->level ? "Level {$this->level}" : null,
            $this->position ? "Pos {$this->position}" : null,
        ]);

        return !empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Get current utilization (count of products stored here)
     */
    public function getUtilizationAttribute()
    {
        return $this->inventoryLocations()->count();
    }

    /**
     * Get total quantity stored at this location
     */
    public function getTotalQuantityAttribute()
    {
        return $this->inventoryLocations()->sum('quantity');
    }

    /**
     * Scope: active locations only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: order by sort order then name
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Scope: only root level locations (no parent)
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope: by depth level
     */
    public function scopeAtDepth($query, $depth)
    {
        return $query->where('depth', $depth);
    }

    /**
     * Update path and depth when model is saved
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($location) {
            if ($location->parent_id) {
                $parent = StorageLocation::find($location->parent_id);
                if ($parent) {
                    $location->depth = $parent->depth + 1;
                    $location->path = $parent->path ? $parent->path . '/' . $parent->id : $parent->id;
                }
            } else {
                $location->depth = 0;
                $location->path = null;
            }
        });

        // Update children's paths when parent changes
        static::saved(function ($location) {
            if ($location->wasChanged('parent_id') || $location->wasChanged('path')) {
                foreach ($location->children as $child) {
                    $child->save(); // This triggers the saving event, updating the path recursively
                }
            }
        });
    }
}

