<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class FdWorkOrder extends Model
{
    protected $table = 'fd_work_orders';

    /** Every lifecycle status a work order may hold. */
    public const STATUSES = ['active', 'on_hold', 'complete'];

    /** Set true around bulk creates (seeders/importers) to skip auto-resequencing. */
    public static bool $suspendResequence = false;

    protected static function boot(): void
    {
        parent::boot();

        static::created(function (FdWorkOrder $wo) {
            $defaults = ['Cut List Prepared', 'Cut List Reviewed', 'Dropbox Uploaded', 'Kanban Entered'];
            foreach ($defaults as $i => $name) {
                FdJobStep::create([
                    'work_order_id' => $wo->id,
                    'name' => $name,
                    'sort_order' => $i + 1,
                    'status' => 'pending',
                ]);
            }
        });
    }

    protected $fillable = [
        'business_job_id', 'release_number', 'release_code', 'date_issued', 'due_date',
        'planned_start_date', 'planned_completion_date',
        'material_delivery', 'estimated_minutes_override', 'notes', 'archived', 'priority', 'priority_locked',
        'status', 'completed_at', 'completed_by_user_id', 'completion_email_sent_at',
    ];

    protected $casts = [
        'date_issued' => 'date',
        'due_date' => 'date',
        'planned_start_date' => 'date',
        'planned_completion_date' => 'date',
        'archived' => 'boolean',
        'priority_locked' => 'boolean',
        'estimated_minutes_override' => 'integer',
        'completed_at' => 'datetime',
        'completion_email_sent_at' => 'datetime',
    ];

    /**
     * The work order's labour-time estimate: every elevation's effective
     * estimate summed, unless a manual override is set on the work order.
     *
     * @return array{computed: int|null, override: int|null, effective: int|null}
     */
    public function estimateMinutes(): array
    {
        $elevations = $this->relationLoaded('elevations')
            ? $this->elevations
            : $this->elevations()->with(['stages', 'templateSet'])->get();

        $sum = 0;
        $any = false;
        foreach ($elevations as $elev) {
            $eff = $elev->estimateMinutes()['effective'];
            if ($eff !== null) {
                $sum += $eff;
                $any = true;
            }
        }

        $computed = $any ? $sum : null;
        $override = $this->estimated_minutes_override;

        return [
            'computed' => $computed,
            'override' => $override,
            'effective' => $override ?? $computed,
        ];
    }

    /**
     * Estimated labour-time remaining: every elevation's not-yet-complete
     * share summed. Unlike estimateMinutes(), there's no manual override —
     * this always reflects current stage progress.
     *
     * @return array{computed: int|null, effective: int|null}
     */
    public function estimateRemainingMinutes(): array
    {
        $elevations = $this->relationLoaded('elevations')
            ? $this->elevations
            : $this->elevations()->with(['stages', 'templateSet'])->get();

        $sum = 0;
        $any = false;
        foreach ($elevations as $elev) {
            $eff = $elev->remainingMinutes()['effective'];
            if ($eff !== null) {
                $sum += $eff;
                $any = true;
            }
        }

        $computed = $any ? $sum : null;

        return [
            'computed' => $computed,
            'effective' => $computed,
        ];
    }

    /**
     * Rebuild the global `priority` ranking over non-archived, not-yet-complete
     * work orders.
     *
     * Locked WOs keep the position their stored `priority` names (de-duped and
     * clamped into range). Everything else is ordered by due date (nulls last),
     * then issue date, then id, and slotted into the remaining positions.
     * Completed work orders never occupy a ranking slot — any leftover
     * priority from before completion is cleared here regardless of whether
     * anything else is left to rank.
     */
    public static function resequencePriorities(): void
    {
        if (self::$suspendResequence) {
            return;
        }

        DB::transaction(function () {
            self::where('archived', false)
                ->where('status', 'complete')
                ->whereNotNull('priority')
                ->update(['priority' => null]);

            $all = self::where('archived', false)->where('status', '!=', 'complete')->get();
            $total = $all->count();
            if ($total === 0) {
                return;
            }

            $locked = $all->where('priority_locked', true)
                ->sortBy(fn ($w) => [$w->priority === null, $w->priority ?? PHP_INT_MAX, $w->id])
                ->values();

            $reserved = [];   // position (1-based) => wo id
            $cursor = 1;
            foreach ($locked as $w) {
                $want = ($w->priority >= 1 && $w->priority <= $total && ! isset($reserved[$w->priority]))
                    ? (int) $w->priority
                    : null;
                if ($want === null) {
                    while (isset($reserved[$cursor])) {
                        $cursor++;
                    }
                    $want = $cursor;
                }
                $reserved[$want] = $w->id;
            }

            $auto = $all->where('priority_locked', false)
                ->sortBy(fn ($w) => [
                    // On-hold work always ranks below every active one, no
                    // matter how soon its due date is — due date only breaks
                    // ties within each status bucket, not across them.
                    $w->status === 'on_hold' ? 1 : 0,
                    $w->due_date === null,
                    optional($w->due_date)->format('Y-m-d') ?? '9999-99-99',
                    optional($w->date_issued)->format('Y-m-d') ?? '9999-99-99',
                    $w->id,
                ])
                ->values();

            $pos = 1;
            foreach ($auto as $w) {
                while (isset($reserved[$pos])) {
                    $pos++;
                }
                if ((int) $w->priority !== $pos) {
                    self::whereKey($w->id)->update(['priority' => $pos]);
                }
                $pos++;
            }

            foreach ($reserved as $p => $id) {
                self::whereKey($id)->update(['priority' => $p]);
            }
        });
    }

    /**
     * Re-derive `due_date` from the elevations: the earliest elevation
     * `date_requested` wins ("closest date"). Null when no elevation carries a
     * date. Persists only on a real change. Callers should follow with
     * resequencePriorities() so the ranking picks up the move.
     */
    public function recalcDueDateFromElevations(): void
    {
        $earliest = $this->elevations()->whereNotNull('date_requested')->min('date_requested');
        $value = $earliest ? \Illuminate\Support\Carbon::parse($earliest)->format('Y-m-d') : null;

        if (optional($this->due_date)->format('Y-m-d') !== $value) {
            $this->forceFill(['due_date' => $value])->save();
        }
    }

    public function businessJob(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function elevations(): HasMany
    {
        return $this->hasMany(FdWoElevation::class, 'work_order_id')->orderBy('created_at')->orderBy('id');
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(FdWoDrawing::class, 'work_order_id')->orderBy('created_at');
    }

    /**
     * The release token — a custom `release_code` when set, otherwise the auto
     * "R{release_number}". Used to build the release label.
     */
    public function getReleaseTokenAttribute(): string
    {
        $code = trim((string) $this->release_code);

        return $code !== '' ? $code : 'R'.$this->release_number;
    }

    public function releaseLabel(): string
    {
        $jobNumber = $this->businessJob?->job_number ?? '?';

        return "{$jobNumber}-{$this->release_token}";
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(FdUser::class, 'fd_wo_assignments', 'work_order_id', 'user_id')
            ->withTimestamps();
    }

    /** True when every elevation stage is complete (or there are no stages). */
    public function isComplete(): bool
    {
        foreach ($this->elevations as $elev) {
            foreach ($elev->stages as $stage) {
                if (! in_array($stage->status, ['complete', 'not_required'])) {
                    return false;
                }
            }
        }

        return true;
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FdJobStep::class, 'work_order_id')->orderBy('sort_order');
    }

    public function completedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
    }

    public function statusLog(): HasMany
    {
        return $this->hasMany(FdWoStatusLog::class, 'work_order_id')->orderByDesc('created_at');
    }

    /** True when every work-order-level step is terminal (complete / not_required). */
    public function stepsComplete(): bool
    {
        return $this->steps->every(fn ($s) => in_array($s->status, FdJobStep::TERMINAL, true));
    }

    /** True when the WO has elevations and every one of them carries a completion date. */
    public function elevationsComplete(): bool
    {
        return $this->elevations->isNotEmpty()
            && $this->elevations->every(fn ($e) => $e->date_completed !== null);
    }

    /**
     * True when every elevation is marked complete AND every WO-level step is
     * done — the point at which the office is prompted to tag the WO complete.
     * Callers must eager-load `elevations.stages` and `steps`.
     */
    public function isReadyToComplete(): bool
    {
        return $this->elevationsComplete() && $this->stepsComplete() && $this->isComplete();
    }

    /** Human-readable reasons the WO is not yet ready to complete. */
    public function completionBlockers(): array
    {
        $reasons = [];
        if ($this->elevations->isEmpty()) {
            $reasons[] = 'No elevations have been added.';
        } elseif (! $this->elevationsComplete()) {
            $open = $this->elevations->filter(fn ($e) => $e->date_completed === null)->count();
            $reasons[] = "{$open} elevation(s) not yet marked complete.";
        }
        if (! $this->stepsComplete()) {
            $open = $this->steps->filter(fn ($s) => ! in_array($s->status, FdJobStep::TERMINAL, true))->count();
            $reasons[] = "{$open} work-order step(s) still open.";
        }

        return $reasons;
    }

    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }
}
