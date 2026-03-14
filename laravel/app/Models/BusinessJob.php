<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BusinessJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'job_number',
        'job_name',
        'customer_name',
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
