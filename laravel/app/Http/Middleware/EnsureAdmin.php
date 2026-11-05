<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    /** Server-side gate for admin-only endpoints (the UI hiding is UX only). */
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        return $next($request);
    }
}
