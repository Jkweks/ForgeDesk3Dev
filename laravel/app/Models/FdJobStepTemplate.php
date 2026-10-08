<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One entry in the admin-managed list of job-level steps copied onto each new work order. */
class FdJobStepTemplate extends Model
{
    protected $table = 'fd_job_step_templates';

    protected $fillable = ['name', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean', 'sort_order' => 'integer'];
}
