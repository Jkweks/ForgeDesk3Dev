<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguratorFrameProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'frame_series_id',
        'role_label',
        'product_id',
        'formula',
        'condition',
        'glass_thicknesses',
        'section_height',
        'qty_per_opening',
        'sort_order',
    ];

    protected $casts = [
        'formula' => 'array',
        'glass_thicknesses' => 'array',
        'section_height' => 'decimal:4',
        'qty_per_opening' => 'integer',
        'sort_order' => 'integer',
    ];

    // A null condition applies regardless of opening type / transom / threshold.
    public static $conditions = [
        'single' => 'Single',
        'pair' => 'Pair',
        'transom' => 'Transom',
        'threshold' => 'Threshold',
        'transom_pair' => 'Transom + Pair',
    ];

    public function frameSeries()
    {
        return $this->belongsTo(ConfiguratorFrameSeries::class, 'frame_series_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function components()
    {
        return $this->hasMany(ConfiguratorFrameComponent::class, 'frame_profile_id')->orderBy('sort_order');
    }

    /**
     * Whether this profile applies to the given opening conditions.
     * A null condition means the profile is always included.
     */
    public function appliesTo(string $openingType, bool $hasTransom, bool $hasThreshold, ?float $transomGlazing = null): bool
    {
        switch ($this->condition) {
            case 'single':
                if ($openingType !== 'single') {
                    return false;
                }
                break;
            case 'pair':
                if ($openingType !== 'pair') {
                    return false;
                }
                break;
            case 'transom':
                if (! $hasTransom) {
                    return false;
                }
                break;
            case 'threshold':
                if (! $hasThreshold) {
                    return false;
                }
                break;
            case 'transom_pair':
                if (! $hasTransom || $openingType !== 'pair') {
                    return false;
                }
                break;
        }

        if ($transomGlazing !== null && ! empty($this->glass_thicknesses)) {
            $allowed = array_map(fn ($v) => round((float) $v, 4), $this->glass_thicknesses);
            if (! in_array(round($transomGlazing, 4), $allowed, true)) {
                return false;
            }
        }

        return true;
    }
}
