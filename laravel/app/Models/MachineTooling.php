<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineTooling extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'machine_tooling';

    protected $fillable = [
        'machine_id',
        'product_id',
        'location_on_machine',
        'installed_at',
        'installed_by',
        'maintenance_record_id',
        'tool_life_used',
        'tool_life_remaining',
        'status',
        'removed_at',
        'removed_by',
        'replacement_maintenance_record_id',
        'notes',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'removed_at' => 'datetime',
        'tool_life_used' => 'decimal:2',
        'tool_life_remaining' => 'decimal:2',
    ];

    protected $appends = ['tool_life_percentage', 'needs_warning'];

    /**
     * Boot method to automatically calculate remaining life
     */
    protected static function booted()
    {
        static::saving(function ($tooling) {
            $tooling->calculateRemainingLife();
        });
    }

    /**
     * Relationships
     */
    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function installationRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class, 'maintenance_record_id');
    }

    public function replacementRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class, 'replacement_maintenance_record_id');
    }

    /**
     * Calculate remaining tool life based on max life and used life
     */
    public function calculateRemainingLife()
    {
        if ($this->product && $this->product->isConsumableTool() && $this->product->tool_life_max) {
            $this->tool_life_remaining = $this->product->tool_life_max - $this->tool_life_used;

            // Update status based on remaining life
            $this->updateStatusFromLife();
        }
    }

    /**
     * Update status based on remaining tool life
     */
    public function updateStatusFromLife()
    {
        if (!$this->product || !$this->product->isConsumableTool() || !$this->product->tool_life_max) {
            return;
        }

        $percentageRemaining = ($this->tool_life_remaining / $this->product->tool_life_max) * 100;
        $warningThreshold = $this->product->tool_life_warning_threshold ?? 20;

        if ($this->status === 'replaced') {
            // Don't change status if already replaced
            return;
        }

        if ($percentageRemaining <= 0) {
            $this->status = 'needs_replacement';
        } elseif ($percentageRemaining <= $warningThreshold) {
            $this->status = 'warning';
        } else {
            $this->status = 'active';
        }
    }

    /**
     * Get tool life percentage used (0-100)
     */
    public function getToolLifePercentageAttribute()
    {
        if ($this->product && $this->product->isConsumableTool() && $this->product->tool_life_max > 0) {
            return round(($this->tool_life_used / $this->product->tool_life_max) * 100, 1);
        }
        return null;
    }

    /**
     * Check if tool needs warning
     */
    public function getNeedsWarningAttribute()
    {
        return in_array($this->status, ['warning', 'needs_replacement']);
    }

    /**
     * Check if tool is active (installed and not removed)
     */
    public function isActive()
    {
        return $this->removed_at === null && in_array($this->status, ['active', 'warning', 'needs_replacement']);
    }

    /**
     * Mark tool as replaced
     */
    public function markAsReplaced($replacementMaintenanceRecordId, $removedBy = null)
    {
        $this->status = 'replaced';
        $this->removed_at = now();
        $this->removed_by = $removedBy ?? auth()->user()->name ?? 'System';
        $this->replacement_maintenance_record_id = $replacementMaintenanceRecordId;
        $this->save();
    }

    /**
     * Get formatted tool life display
     */
    public function getFormattedToolLifeAttribute()
    {
        if (!$this->product || !$this->product->isConsumableTool()) {
            return 'N/A';
        }

        $unit = $this->product->tool_life_unit_name ?? $this->product->tool_life_unit;
        return number_format($this->tool_life_used, 0) . ' / ' . number_format($this->product->tool_life_max, 0) . ' ' . $unit;
    }

    /**
     * Get status badge color for UI
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'green',
            'warning' => 'yellow',
            'needs_replacement' => 'red',
            'replaced' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status display text
     */
    public function getStatusTextAttribute()
    {
        return match($this->status) {
            'active' => 'Active',
            'warning' => 'Warning',
            'needs_replacement' => 'Needs Replacement',
            'replaced' => 'Replaced',
            default => ucfirst($this->status),
        };
    }

    /**
     * Scope for active tooling only
     */
    public function scopeActive($query)
    {
        return $query->whereNull('removed_at')
            ->whereIn('status', ['active', 'warning', 'needs_replacement']);
    }

    /**
     * Scope for tools needing attention (warning or needs replacement)
     */
    public function scopeNeedsAttention($query)
    {
        return $query->whereNull('removed_at')
            ->whereIn('status', ['warning', 'needs_replacement']);
    }

    /**
     * Scope for specific machine
     */
    public function scopeForMachine($query, $machineId)
    {
        return $query->where('machine_id', $machineId);
    }
}
