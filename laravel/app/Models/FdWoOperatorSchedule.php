<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One operator's planning window on a work order (Work Queue schedule). When a
 * work order has any of these, its own planned dates are kept as the envelope
 * of them; an operator without one falls back to the work order's dates.
 */
class FdWoOperatorSchedule extends Model
{
    protected $table = 'fd_wo_operator_schedules';

    protected $fillable = ['work_order_id', 'user_id', 'start_date', 'end_date', 'start_quarter', 'end_quarter'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'user_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    /** Ids of every operator holding an open (non-terminal) stage on the work order. */
    public static function assignedUserIds(int $workOrderId): Collection
    {
        $stages = FdWoStage::query()
            ->whereNotIn('status', FdWoStage::TERMINAL)
            ->where(function ($q) use ($workOrderId) {
                $q->whereHas('elevation', fn ($e) => $e->where('work_order_id', $workOrderId))
                    ->orWhere('work_order_id', $workOrderId);
            })
            ->with('assignees:id')
            ->get(['id', 'assigned_to_id']);

        return $stages
            ->flatMap(fn ($s) => $s->assignees->isNotEmpty() ? $s->assignees->pluck('id') : collect([$s->assigned_to_id]))
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();
    }

    /**
     * Drop windows of operators who no longer hold an open stage on the work
     * order, then re-derive the work order's planned dates from what is left.
     */
    public static function pruneFor(int $workOrderId): void
    {
        if (! static::where('work_order_id', $workOrderId)->exists()) {
            return;
        }

        $deleted = static::where('work_order_id', $workOrderId)
            ->whereNotIn('user_id', static::assignedUserIds($workOrderId))
            ->delete();

        if ($deleted) {
            static::syncEnvelope($workOrderId);
        }
    }

    /** Work order planned dates = earliest window start → latest window finish. */
    public static function syncEnvelope(int $workOrderId): void
    {
        $windows = static::where('work_order_id', $workOrderId)->get();
        if ($windows->isEmpty()) {
            return;
        }

        $slot = fn ($date, $quarter) => $date->format('Y-m-d').'|'.$quarter;
        $first = $windows->sortBy(fn ($w) => $slot($w->start_date, $w->start_quarter))->first();
        $last = $windows->sortByDesc(fn ($w) => $slot($w->end_date, $w->end_quarter))->first();

        FdWorkOrder::whereKey($workOrderId)->update([
            'planned_start_date' => $first->start_date->format('Y-m-d'),
            'planned_start_quarter' => $first->start_quarter,
            'planned_completion_date' => $last->end_date->format('Y-m-d'),
            'planned_completion_quarter' => $last->end_quarter,
        ]);
    }
}
