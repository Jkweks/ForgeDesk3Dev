<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'parent_id',
        'description',
        'system',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get all child categories (subcategories)
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
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
     * Get full path as string (e.g., "Grouping > Style > System > Part Type")
     */
    public function getFullPathAttribute()
    {
        $pathNames = $this->ancestors()->pluck('name')->push($this->name);
        return $pathNames->implode(' > ');
    }

    /**
     * Get all products in this category (many-to-many through pivot table)
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /**
     * Get products where this is the primary category
     */
    public function primaryProducts()
    {
        return $this->products()->wherePivot('is_primary', true);
    }
}
