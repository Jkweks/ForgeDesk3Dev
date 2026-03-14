<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'machine_id',
        'task_id',
        'asset_id',
        'performed_by',
        'performed_at',
        'notes',
        'downtime_minutes',
        'labor_hours',
        'parts_used',
        'tools_replaced',
        'attachments',
    ];

    protected $casts = [
        'performed_at' => 'date',
        'downtime_minutes' => 'integer',
        'labor_hours' => 'decimal:2',
        'parts_used' => 'array',
        'tools_replaced' => 'array',
        'attachments' => 'array',
    ];

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function task()
    {
        return $this->belongsTo(MaintenanceTask::class, 'task_id');
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function toolingInstallations()
    {
        return $this->hasMany(MachineTooling::class, 'maintenance_record_id');
    }

    public function toolingReplacements()
    {
        return $this->hasMany(MachineTooling::class, 'replacement_maintenance_record_id');
    }

    protected static function booted()
    {
        static::created(function ($record) {
            $record->machine->updateDowntime();
            $record->machine->updateLastService();
        });

        static::updated(function ($record) {
            $record->machine->updateDowntime();
            $record->machine->updateLastService();
        });

        static::deleted(function ($record) {
            $record->machine->updateDowntime();
            $record->machine->updateLastService();
        });
    }
}
