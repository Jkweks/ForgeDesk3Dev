<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingElevation extends Model
{
    protected $fillable = [
        'drawing_set_id', 'page', 'label', 'arch_ref', 'system', 'finish', 'finish_code', 'thus_reqd', 'opp_reqd', 'total_reqd',
        'ro_w', 'fs_w', 'ro_h', 'fs_h', 'door_w', 'door_h', 'review_status', 'reviewed_by', 'reviewed_at', 'multi_sheet', 'manual_takeoff',
    ];

    protected $casts = ['multi_sheet' => 'boolean', 'reviewed_at' => 'datetime'];

    public function dims()
    {
        return $this->hasMany(DrawingElevationDim::class)->orderBy('axis')->orderBy('position');
    }

    public function sheets()
    {
        return $this->hasMany(DrawingSheet::class)->orderBy('page');
    }

    public function marks()
    {
        return $this->hasMany(DrawingDimMark::class)->orderBy('page')->orderBy('kind')->orderBy('idx');
    }

    public function axisChecks()
    {
        return $this->hasMany(DrawingAxisCheck::class);
    }

    public function printedMembers()
    {
        return $this->hasMany(DrawingPrintedMember::class)->orderBy('row')->orderBy('position');
    }

    public function set()
    {
        return $this->belongsTo(DrawingSet::class, 'drawing_set_id');
    }

    public function glassTags()
    {
        return $this->hasMany(DrawingElevationGlassTag::class);
    }
}
