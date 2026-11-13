<?php

namespace App\Models\Scopes;

use App\Models\BusinessJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Jobs are shared data, but a job only becomes visible to Fabrication once it is
 * active: bidding / won / lost jobs belong to Project Management. Fail-closed:
 * every existing job query hides them; PM code opts out with
 * BusinessJob::withoutGlobalScope(FabricationVisibleScope::class).
 */
class FabricationVisibleScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNotIn($model->qualifyColumn('status'), BusinessJob::PM_ONLY_STATUSES);
    }
}
