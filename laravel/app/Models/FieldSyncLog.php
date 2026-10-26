<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldSyncLog extends Model
{
    protected $table = 'field_sync_log';

    protected $fillable = [
        'submission_uuid', 'kind', 'asset_number', 'status', 'error', 'payload',
        'target_type', 'target_id', 'ingested_at', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['payload' => 'array', 'ingested_at' => 'datetime', 'resolved_at' => 'datetime'];
}
