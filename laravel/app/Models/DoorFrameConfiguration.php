<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DoorFrameConfiguration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'business_job_id',
        'configuration_name',
        'job_scope',
        'quantity',
        'status',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'status_label',
        'scope_label',
    ];

    // Job scope configuration
    public static $jobScopes = [
        'door_and_frame' => 'Door and Frame',
        'frame_only' => 'Frame Only',
        'door_only' => 'Door Only',
    ];

    // Status configuration
    public static $statuses = [
        'draft' => 'Draft',
        'released' => 'Released',
        'in_progress' => 'In Progress',
        'completed' => 'Completed',
        'on_hold' => 'On Hold',
        'cancelled' => 'Cancelled',
    ];

    /**
     * Get the business job this configuration belongs to
     */
    public function businessJob()
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    /**
     * Get the user who created this configuration
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Get door tags for this configuration
     */
    public function doors()
    {
        return $this->hasMany(DoorFrameConfigurationDoor::class, 'configuration_id');
    }

    /**
     * Get opening specifications
     */
    public function openingSpecs()
    {
        return $this->hasOne(DoorFrameOpeningSpec::class, 'configuration_id');
    }

    /**
     * Get frame configuration
     */
    public function frameConfig()
    {
        return $this->hasOne(DoorFrameFrameConfig::class, 'configuration_id');
    }

    /**
     * Get door configurations
     */
    public function doorConfigs()
    {
        return $this->hasMany(DoorFrameDoorConfig::class, 'configuration_id');
    }

    /**
     * Check if configuration includes frame
     */
    public function includesFrame()
    {
        return in_array($this->job_scope, ['door_and_frame', 'frame_only']);
    }

    /**
     * Check if configuration includes door
     */
    public function includesDoor()
    {
        return in_array($this->job_scope, ['door_and_frame', 'door_only']);
    }

    /**
     * Get formatted status label
     */
    public function getStatusLabelAttribute()
    {
        return self::$statuses[$this->status] ?? $this->status;
    }

    /**
     * Get formatted scope label
     */
    public function getScopeLabelAttribute()
    {
        return self::$jobScopes[$this->job_scope] ?? $this->job_scope;
    }

    /**
     * Check if configuration can be edited
     */
    public function canEdit()
    {
        return in_array($this->status, ['draft', 'on_hold']);
    }

    /**
     * Check if configuration is complete
     */
    public function isComplete()
    {
        // Check required relationships exist
        if (!$this->openingSpecs) {
            return false;
        }

        if ($this->includesFrame() && !$this->frameConfig) {
            return false;
        }

        if ($this->includesDoor() && $this->doorConfigs->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * Get validation errors
     */
    public function getValidationErrors()
    {
        $errors = [];

        if (!$this->openingSpecs) {
            $errors[] = 'Opening specifications are required';
        }

        if ($this->includesFrame() && !$this->frameConfig) {
            $errors[] = 'Frame configuration is required';
        }

        if ($this->includesDoor() && $this->doorConfigs->isEmpty()) {
            $errors[] = 'Door configuration is required';
        }

        return $errors;
    }
}
