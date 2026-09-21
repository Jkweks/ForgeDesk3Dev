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
        'work_order_id',
        'job_reservation_id',
        'job_scope',
        'quantity',
        'duplicate_group_id',
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
        'reserved' => 'Reserved',
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
     * The work order that will actually produce this configuration, once one
     * exists. Null for a job pre-configured ahead of production scheduling.
     */
    public function workOrder()
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    /**
     * The production elevation row(s) this configuration specs — 1-3 rows
     * per opening (e.g. a pair = 2 Door elevations + 1 Frame elevation).
     * See ElevationConfigurationMatcher for how the link gets stamped.
     */
    public function elevations()
    {
        return $this->hasMany(FdWoElevation::class, 'door_frame_configuration_id');
    }

    /**
     * The reservation that commits this configuration's generated BOM
     * (frame + door + hardware parts) against real inventory. Set on
     * release — see ConfigurationReservationBridge.
     */
    public function jobReservation()
    {
        return $this->belongsTo(JobReservation::class, 'job_reservation_id');
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
     * Other configurations created alongside this one via bulk duplication
     * that are still linked to it (share duplicate_group_id). Empty when
     * this configuration was never duplicated, or has since been unlinked.
     */
    public function linkedSiblings()
    {
        return self::where('duplicate_group_id', $this->duplicate_group_id)
            ->where('id', '!=', $this->id)
            ->when(! $this->duplicate_group_id, fn ($q) => $q->whereRaw('1 = 0'));
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
     * Get hardware items linked to this configuration
     */
    public function hardwareLinks()
    {
        return $this->hasMany(ConfiguratorHwlibLink::class, 'configuration_id');
    }

    public function appliedHardwareSets()
    {
        return $this->belongsToMany(ConfiguratorHwlibSet::class, 'door_frame_configuration_hwlib_sets', 'configuration_id', 'set_id')
            ->withPivot('applied_at');
    }

    /**
     * Get the generated hardware BOM parts
     */
    public function hardwareParts()
    {
        return $this->hasMany(DoorFrameHardwarePart::class, 'configuration_id');
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
        return in_array($this->status, ['draft', 'reserved', 'on_hold']);
    }

    /**
     * Check if configuration is complete
     */
    public function isComplete()
    {
        // Check required relationships exist
        if (! $this->openingSpecs) {
            return false;
        }

        if ($this->includesFrame() && ! $this->frameConfig) {
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

        if (! $this->openingSpecs) {
            $errors[] = 'Opening specifications are required';
        }

        if ($this->includesFrame() && ! $this->frameConfig) {
            $errors[] = 'Frame configuration is required';
        }

        if ($this->includesDoor() && $this->doorConfigs->isEmpty()) {
            $errors[] = 'Door configuration is required';
        }

        return $errors;
    }
}
