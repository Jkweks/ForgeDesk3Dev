<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MaintenanceTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'machine_id',
        'title',
        'description',
        'frequency',
        'assigned_to',
        'interval_count',
        'interval_unit',
        'start_date',
        'status',
        'priority',
    ];

    protected $casts = [
        'start_date' => 'date',
        'interval_count' => 'integer',
    ];

    protected $appends = ['next_due_date', 'is_overdue', 'is_due_soon'];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class, 'task_id');
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function getNextDueDateAttribute()
    {
        if (!$this->start_date || !$this->interval_count || !$this->interval_unit) {
            return null;
        }

        $lastRecord = $this->maintenanceRecords()
            ->orderBy('performed_at', 'desc')
            ->first();

        $baseDate = $lastRecord ? $lastRecord->performed_at : $this->start_date;

        return Carbon::parse($baseDate)->add($this->interval_count, $this->interval_unit . 's');
    }

    public function getIsOverdueAttribute()
    {
        $nextDue = $this->next_due_date;
        if (!$nextDue) {
            return false;
        }

        return Carbon::parse($nextDue)->isPast();
    }

    public function getIsDueSoonAttribute()
    {
        $nextDue = $this->next_due_date;
        if (!$nextDue) {
            return false;
        }

        $dueDate = Carbon::parse($nextDue);
        $now = Carbon::now();

        return $dueDate->isFuture() && $dueDate->diffInDays($now) <= 14;
    }
}
