<?php

namespace App\Models\CutFlow;

use App\Models\FdWorkOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CutJob extends Model
{
    protected $connection = 'cutflow';

    protected $fillable = [
        'name',
        'work_order_id',
        'bom_diverged_at',
    ];

    protected $casts = ['bom_diverged_at' => 'datetime'];

    /**
     * Name to print on labels: the ForgeDesk job's name when this cut job came
     * from a work order, else the cut job's own name (CSV imports).
     */
    public function labelJobName(): string
    {
        $name = $this->work_order_id
            ? FdWorkOrder::with('businessJob')->find($this->work_order_id)?->businessJob?->job_name
            : null;

        return trim((string) $name) ?: (string) $this->name;
    }

    /**
     * Sets `job_title` and `release_label` on each job for display: the
     * ForgeDesk job's name and its work release code (e.g. "WO1") when the cut
     * job came from a work order, else the cut job's own name and no release.
     * Work orders live in the main DB, so they're loaded in one query for the
     * whole set rather than per job. Returns the jobs sorted by title, then
     * release number.
     */
    public static function withDisplayLabels(Collection $jobs): Collection
    {
        $workOrders = FdWorkOrder::with('businessJob')
            ->whereIn('id', $jobs->pluck('work_order_id')->filter()->all())
            ->get()
            ->keyBy('id');

        return $jobs->each(function (CutJob $job) use ($workOrders) {
            $wo = $job->work_order_id ? $workOrders->get($job->work_order_id) : null;

            $job->setAttribute('job_title', trim((string) $wo?->businessJob?->job_name) ?: $job->name);
            $job->setAttribute('release_label', $wo ? trim((string) ($wo->release_code ?: ($wo->release_number ? 'Rel '.$wo->release_number : ''))) : '');
            $job->setAttribute('release_sort', (int) ($wo?->release_number ?? 0));
        })->sortBy([
            fn ($a, $b) => strcasecmp($a->job_title, $b->job_title),
            fn ($a, $b) => $a->release_sort <=> $b->release_sort,
        ])->values();
    }

    public function parts()
    {
        return $this->hasMany(Part::class);
    }
}
