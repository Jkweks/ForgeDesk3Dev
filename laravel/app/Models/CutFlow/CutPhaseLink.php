<?php

namespace App\Models\CutFlow;

use Illuminate\Database\Eloquent\Model;

/** A manual link from one phase of a cut list to a work-order elevation (see CutStepSync). */
class CutPhaseLink extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = ['cut_job_id', 'phase', 'elevation_id', 'linked_by'];
}
