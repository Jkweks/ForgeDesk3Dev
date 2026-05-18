<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FdStageTemplate extends Model
{
    protected $table = 'fd_stage_templates';

    protected $fillable = ['job_type', 'name', 'description', 'sort_order'];
}
