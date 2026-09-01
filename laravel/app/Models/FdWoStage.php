<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FdWoStage extends Model
{
    protected $table = 'fd_wo_stages';

    /** Every status a stage may hold. */
    public const STATUSES = ['pending', 'in_progress', 'complete', 'blocked', 'not_required', 'on_hold'];

    /** Statuses that count as "done" for gating purposes. */
    public const TERMINAL = ['complete', 'not_required'];

    protected $fillable = [
        'work_order_id', 'elevation_id', 'template_id', 'name', 'description',
        'sort_order', 'blocks_next', 'status', 'assigned_to_id', 'completed_by_id', 'started_at', 'completed_at', 'notes',
    ];

    protected $casts = [
        'blocks_next'  => 'boolean',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL, true);
    }

    /**
     * Stages that are "in play": pending/in_progress, on an open elevation of a
     * live work order. Joins `e` (fd_wo_elevations) and `w` (fd_work_orders) so
     * callers can order by `w.priority` / `e.date_requested`.
     */
    public function scopeActionable($query)
    {
        return $query
            ->select('fd_wo_stages.*')
            ->join('fd_wo_elevations as e', 'e.id', '=', 'fd_wo_stages.elevation_id')
            ->join('fd_work_orders as w', 'w.id', '=', 'e.work_order_id')
            ->whereIn('fd_wo_stages.status', ['pending', 'in_progress'])
            ->whereNull('e.date_completed')
            ->where('w.archived', false);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function elevation(): BelongsTo
    {
        return $this->belongsTo(FdWoElevation::class, 'elevation_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'assigned_to_id');
    }

    /**
     * Every operator this stage is assigned to. A stage in multiple operators'
     * queues is still one row — whoever moves it to a terminal status clears it
     * for all of them.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(FdUser::class, 'fd_wo_stage_assignees', 'stage_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * The single writer for stage assignment. Keeps the assignees pivot and the
     * legacy `assigned_to_id` "primary assignee" column in sync. Pass an empty
     * array to unassign entirely.
     */
    public function syncAssignees(array $userIds): void
    {
        $ids = collect($userIds)
            ->map(fn ($v) => (int) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assignees()->sync($ids);

        $primary = $ids[0] ?? null;
        if ((int) $this->assigned_to_id !== (int) $primary) {
            $this->assigned_to_id = $primary;
            $this->save();
        }
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'completed_by_id');
    }

    public function log(): HasMany
    {
        return $this->hasMany(FdStageLog::class, 'stage_id')->orderBy('created_at');
    }
}
