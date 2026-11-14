<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PmTodo extends Model
{
    protected $fillable = ['business_job_id', 'title', 'notes', 'due_date', 'assigned_to', 'created_by', 'completed_at', 'completed_by'];

    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function job()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id')->withoutGlobalScopes();
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isDone(): bool
    {
        return $this->completed_at !== null;
    }
}
