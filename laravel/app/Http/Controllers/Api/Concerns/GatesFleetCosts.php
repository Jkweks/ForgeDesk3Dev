<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;

trait GatesFleetCosts
{
    protected function canSeeCosts(Request $request): bool
    {
        return (bool) $request->user()?->hasPermission('fleet.costs.view');
    }

    /** Drop cost keys from validated input when the user cannot see/set costs. */
    protected function withoutCosts(Request $request, array $data, array $costKeys): array
    {
        return $this->canSeeCosts($request) ? $data : array_diff_key($data, array_flip($costKeys));
    }
}
