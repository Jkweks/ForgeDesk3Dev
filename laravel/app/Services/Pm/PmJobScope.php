<?php

namespace App\Services\Pm;

use App\Models\BusinessJob;
use App\Models\Scopes\FabricationVisibleScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Which jobs a Project Management user can see: every stage of their own jobs (they are the
 * project manager, or they created it), or every job with pm.jobs.view_all (company scope).
 */
class PmJobScope
{
    public static function canSeeAll(?User $user): bool
    {
        return $user && $user->hasPermission('pm.jobs.view_all');
    }

    public static function query(?User $user): Builder
    {
        $q = BusinessJob::withoutGlobalScope(FabricationVisibleScope::class);
        if (! self::canSeeAll($user)) {
            $q->where(fn ($w) => $w->where('project_manager_id', $user?->id)->orWhere('created_by_id', $user?->id));
        }

        return $q;
    }

    public static function find(?User $user, int $id): BusinessJob
    {
        return self::query($user)->findOrFail($id);
    }

    /** @return int[] ids of visible jobs, for filtering other PM lists (null = no restriction) */
    public static function visibleIds(?User $user): ?array
    {
        return self::canSeeAll($user) ? null : self::query($user)->pluck('id')->all();
    }
}
