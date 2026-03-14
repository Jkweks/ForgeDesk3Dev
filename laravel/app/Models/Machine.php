<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'equipment_type',
        'machine_type_id',
        'manufacturer',
        'model',
        'serial_number',
        'location',
        'documents',
        'notes',
        'total_downtime_minutes',
        'last_service_at',
    ];

    protected $casts = [
        'documents' => 'array',
        'total_downtime_minutes' => 'integer',
        'last_service_at' => 'datetime',
    ];

    protected $appends = ['task_count'];

    public function machineType()
    {
        return $this->belongsTo(MachineType::class);
    }

    public function maintenanceTasks()
    {
        return $this->hasMany(MaintenanceTask::class);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function assets()
    {
        return $this->belongsToMany(Asset::class, 'asset_machine');
    }

    public function tooling()
    {
        return $this->hasMany(MachineTooling::class);
    }

    public function activeTooling()
    {
        return $this->tooling()->active();
    }

    public function toolsNeedingAttention()
    {
        return $this->tooling()->needsAttention();
    }

    public function getTaskCountAttribute()
    {
        return $this->maintenanceTasks()->count();
    }

    public function updateDowntime()
    {
        $this->total_downtime_minutes = $this->maintenanceRecords()
            ->sum('downtime_minutes') ?? 0;
        $this->save();
    }

    public function updateLastService()
    {
        $lastRecord = $this->maintenanceRecords()
            ->orderBy('performed_at', 'desc')
            ->first();

        if ($lastRecord) {
            $this->last_service_at = $lastRecord->performed_at;
            $this->save();
        }
    }
}
