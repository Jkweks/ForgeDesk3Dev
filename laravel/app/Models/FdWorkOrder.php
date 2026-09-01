<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use App\Models\FdJobStep;

class FdWorkOrder extends Model
{
    protected $table = 'fd_work_orders';

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
                    'name'          => $name,
                    'sort_order'    => $i + 1,
                    'status'        => 'pending',
                ]);
            }
        });
    }

    protected $fillable = [
        'business_job_id', 'release_number', 'date_issued', 'due_date',
        'material_delivery', 'notes', 'archived', 'priority', 'priority_locked',
    ];

    protected $casts = [
        'date_issued'     => 'date',
        'due_date'        => 'date',
        'archived'        => 'boolean',
        'priority_locked' => 'boolean',
    ];

    /**
     * Rebuild the global `priority` ranking over non-archived work orders.
     *
     * Locked WOs keep the position their stored `priority` names (de-duped and
     * clamped into range). Everything else is ordered by due date (nulls last),
     * then issue date, then id, and slotted into the remaining positions.
     */
    public static function resequencePriorities(): void
    {
        if (self::$suspendResequence) {
            return;
        }

        DB::transaction(function () {
            $all = self::where('archived', false)->get();
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

    public function businessJob(): BelongsTo
    {
        return $this->belongsTo(BusinessJob::class, 'business_job_id');
    }

    public function elevations(): HasMany
    {
        return $this->hasMany(FdWoElevation::class, 'work_order_id')->orderBy('created_at');
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(FdWoDrawing::class, 'work_order_id')->orderBy('created_at');
    }

    public function releaseLabel(): string
    {
        $jobNumber = $this->businessJob?->job_number ?? '?';
        return "{$jobNumber}-R{$this->release_number}";
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
                if (!in_array($stage->status, ['complete', 'not_required'])) return false;
            }
        }
        return true;
    }

    public function steps(): HasMany
    {
        return $this->hasMany(FdJobStep::class, 'work_order_id')->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('archived', false);
    }
}
