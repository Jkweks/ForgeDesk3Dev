<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_number',
        'job_name',
        'customer_name',
        'project_manager',
        'site_address',
        'contact_name',
        'contact_phone',
        'contact_email',
        'status',
        'start_date',
        'target_completion_date',
        'actual_completion_date',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'target_completion_date' => 'date',
        'actual_completion_date' => 'date',
    ];


    // Status configuration
    public static $statuses = [
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Get the user who created this job
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get all door/frame configurations for this job
     */
    public function doorFrameConfigurations()
    {
        return $this->hasMany(DoorFrameConfiguration::class, 'business_job_id');
    }

    /**
     * Get all job reservations for this job
     */
    public function jobReservations()
    {
        return $this->hasMany(JobReservation::class, 'business_job_id');
    }

    /**
     * Get fabrication work orders for this job
     */
    public function workOrders()
    {
        return $this->hasMany(\App\Models\FdWorkOrder::class, 'business_job_id')->orderBy('release_number');
    }

    /**
     * Division derived from first character of job_number
     */
    public function getDivisionAttribute(): string
    {
        return $this->job_number ? substr($this->job_number, 0, 1) : '—';
    }

    /**
     * Get formatted status label
     */
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? $this->status;
    }

    /**
     * Check if job is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if job is completed
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Auto-sync job status based on open reservations and work orders
     */
    public function syncAutoStatus(): void
    {
        // Don't override manually set terminal/hold statuses
        if (in_array($this->status, ['cancelled', 'on_hold'])) {
            return;
        }

        $openReservations = $this->jobReservations()
            ->whereNotIn('status', ['fulfilled', 'cancelled'])
            ->count();

        $nonArchivedWOs = $this->workOrders()->where('archived', false)->with('elevations.stages')->get();
        $openWorkOrders = $nonArchivedWOs->filter(fn($wo) => !$wo->isComplete())->count();

        $totalItems = $this->jobReservations()->count() + $this->workOrders()->count();

        if ($totalItems > 0 && $openReservations === 0 && $openWorkOrders === 0) {
            $this->updateQuietly(['status' => 'completed']);
        } elseif ($openReservations > 0 || $openWorkOrders > 0) {
            $this->updateQuietly(['status' => 'active']);
        }
    }

    /**
     * Get days until target completion
     */
    public function getDaysUntilCompletionAttribute()
    {
        if (!$this->target_completion_date || $this->isCompleted()) {
            return null;
        }

        return now()->diffInDays($this->target_completion_date, false);
    }
}
