<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawingElevation extends Model
{
    protected $fillable = [
        'drawing_set_id', 'page', 'label', 'arch_ref', 'system', 'finish', 'finish_code', 'thus_reqd', 'opp_reqd', 'total_reqd',
        'ro_w', 'fs_w', 'ro_h', 'fs_h', 'door_w', 'door_h',
    ];

    public function dims()
    {
        return $this->hasMany(DrawingElevationDim::class)->orderBy('axis')->orderBy('position');
    }

    public function glassTags()
    {
        return $this->hasMany(DrawingElevationGlassTag::class);
    }
}
