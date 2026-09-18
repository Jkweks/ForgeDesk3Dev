<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupRun extends Model
{
    /** Every status a recorded run may hold, worst-to-best. */
    public const STATUSES = ['failed', 'remote_failed', 'local_failed', 'success'];

    protected $fillable = [
        'run_date', 'started_at', 'finished_at', 'status', 'components', 'error_message',
    ];

    protected $casts = [
        'run_date' => 'date',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'components' => 'array',
    ];
}
