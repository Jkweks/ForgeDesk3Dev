<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdWorkOrder extends Model
{
    protected $table = 'fd_work_orders';

    protected $fillable = [
        'project_name', 'job_number', 'wo_number', 'job_type', 'system',
        'date_received', 'planned_start_date', 'planned_complete_date',
        'requested_finish_date', 'joints', 'dr_fr_units', 'doors_units',
        'estimated_hours_calc', 'estimated_hours_override', 'material_arrived',
        'cut_list_glazer', 'notes', 'project_manager', 'assigned_to_id',
        'archived', 'archived_at',
    ];

    protected $casts = [
        'date_received'          => 'date',
        'planned_start_date'     => 'date',
        'planned_complete_date'  => 'date',
        'requested_finish_date'  => 'date',
        'archived_at'            => 'datetime',
        'cut_list_glazer'        => 'boolean',
        'archived'               => 'boolean',
    ];

    public static array $rates = [
        'cw_prep'    => 0.5,
        'fab_joints' => 0.25,
        'fab_doors'  => 2.25,
        'fab_frames' => 1.5,
    ];

    public function calcHours(): float
    {
        $j = (int) $this->joints;
        $f = (int) $this->dr_fr_units;
        $d = (int) $this->doors_units;

        if ($this->job_type === 'CW') {
            return ($j * self::$rates['cw_prep']) + ($f * self::$rates['fab_frames']) + ($d * self::$rates['fab_doors']);
        }

        return ($j * self::$rates['fab_joints']) + ($f * self::$rates['fab_frames']) + ($d * self::$rates['fab_doors']);
    }

    public function effectiveHours(): float
    {
        return $this->estimated_hours_override ?? $this->estimated_hours_calc ?? 0;
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'assigned_to_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FdWoStage::class, 'work_order_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }
}
