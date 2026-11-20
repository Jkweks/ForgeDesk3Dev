<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingDimMark extends Model
{
    public $timestamps = false;

    public const KINDS = ['ro_w', 'fs_w', 'ro_h', 'fs_h', 'do_w', 'do_h', 'dlo_w', 'dlo_h'];

    protected $fillable = ['drawing_elevation_id', 'page', 'kind', 'idx', 'value', 'x0', 'top', 'x1', 'bottom', 'counted', 'confirmed_value', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['confirmed_at' => 'datetime', 'counted' => 'boolean'];

    protected $appends = ['status'];

    /** green = a person confirmed the value; red = they corrected it to something else; yellow = still just the parser's reading. */
    public function getStatusAttribute(): string
    {
        if ($this->confirmed_value === null) {
            return 'suggested';
        }

        return abs((float) $this->confirmed_value - (float) $this->value) < 0.01 ? 'confirmed' : 'mismatch';
    }

    public function elevation()
    {
        return $this->belongsTo(DrawingElevation::class, 'drawing_elevation_id');
    }
}
