<?php

namespace App\Models;

use App\Services\Configurator\ElevationConfigurationMatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class FdWoElevation extends Model
{
    protected $table = 'fd_wo_elevations';

    protected $fillable = [
        'work_order_id', 'elevation_type_id', 'template_set_id', 'elevation_tag',
        'quantity', 'joint_qty', 'date_requested', 'date_completed', 'completed_by_id', 'notes', 'scope',
    ];

    protected $casts = [
        'date_requested' => 'date',
        'date_completed' => 'date',
        'joint_qty' => 'integer',
    ];

    protected static function booted(): void
    {
        // Every Door/Frame elevation should have a matching configurator
        // record going forward. Best-effort: a failure here (e.g. the
        // elevation type isn't Door/Frame, or the matcher errors) must never
        // block creating the elevation itself, which is the primary,
        // load-bearing action.
        static::created(function (FdWoElevation $elevation) {
            if (! in_array($elevation->elevationType?->name, ['Door', 'Frame'], true)) {
                return;
            }

            try {
                app(ElevationConfigurationMatcher::class)->syncWorkOrder($elevation->workOrder);
            } catch (\Throwable $e) {
                Log::error('Failed to auto-match configurator configuration for elevation', [
                    'elevation_id' => $elevation->id,
                    'message' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * The line's effective "minutes per joint": the sum of the per-step rates
     * when any step sets one, otherwise the tier-level fallback rate.
     */
    public function minutesPerJoint(): float
    {
        $stages = $this->relationLoaded('stages') ? $this->stages : $this->stages()->get();

        $stepRates = $stages->map(fn ($s) => $s->minutes_per_joint)->filter(fn ($v) => $v !== null);
        if ($stepRates->isNotEmpty()) {
            return (float) $stepRates->sum();
        }

        $set = $this->relationLoaded('templateSet') ? $this->templateSet : $this->templateSet()->first();

        return (float) ($set?->minutes_per_joint ?? 0);
    }

    /**
     * Time estimate for this line: joint quantity x the summed per-joint rate.
     * Null when either side is missing — nothing to contribute to the total.
     *
     * @return array{computed: int|null, rate: float, effective: int|null}
     */
    public function estimateMinutes(): array
    {
        $rate = $this->minutesPerJoint();
        $joints = $this->joint_qty;

        $computed = ($joints !== null && $rate > 0)
            ? (int) round($joints * $rate)
            : null;

        return [
            'computed' => $computed,
            'rate' => $rate,
            'effective' => $computed,
        ];
    }

    /**
     * Time estimate for the portion of this line not yet complete. Mirrors
     * estimateMinutes(), but when per-step rates exist it only sums the rate
     * for stages that aren't terminal yet, so a partially-worked line only
     * counts its open steps' share. A line with no per-step rates (only a
     * tier-level fallback) can't be split by stage, so its full rate counts
     * as remaining until the whole line is marked complete.
     *
     * @return array{computed: int|null, rate: float, effective: int|null}
     */
    public function remainingMinutes(): array
    {
        if ($this->date_completed) {
            return ['computed' => 0, 'rate' => 0.0, 'effective' => 0];
        }

        $stages = $this->relationLoaded('stages') ? $this->stages : $this->stages()->get();
        $stepRates = $stages->map(fn ($s) => $s->minutes_per_joint)->filter(fn ($v) => $v !== null);

        $rate = $stepRates->isNotEmpty()
            ? (float) $stages->filter(fn ($s) => $s->minutes_per_joint !== null && ! $s->isTerminal())->sum('minutes_per_joint')
            : $this->minutesPerJoint();

        $joints = $this->joint_qty;
        $computed = ($joints !== null && $rate > 0) ? (int) round($joints * $rate) : null;

        return [
            'computed' => $computed,
            'rate' => $rate,
            'effective' => $computed,
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(FdWorkOrder::class, 'work_order_id');
    }

    public function elevationType(): BelongsTo
    {
        return $this->belongsTo(FdElevationType::class, 'elevation_type_id');
    }

    public function templateSet(): BelongsTo
    {
        return $this->belongsTo(FdStageTemplateSet::class, 'template_set_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(FdUser::class, 'completed_by_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(FdWoStage::class, 'elevation_id')->orderBy('sort_order');
    }

    public function qualityReports(): HasMany
    {
        return $this->hasMany(QualityReport::class, 'elevation_id');
    }
}
