<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Blocks authenticated users who still owe a password change (they signed in
 * with a temporary password). They may only reach the endpoints needed to see
 * their own state and set a new password; everything else returns 403 with a
 * `password_change_required` code the frontend uses to force the change screen.
 */
class EnsurePasswordIsCurrent
{
    /**
     * Paths (relative to the app root, no leading slash) that a user on a
     * temporary password is still allowed to call.
     */
    private const ALLOWED = [
        'api/v1/user',
        'api/v1/user/change-password',
        'api/v1/user/profile',
        'api/logout',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->is(...self::ALLOWED)) {
            return response()->json([
                'message' => 'You must set a new password before continuing.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
