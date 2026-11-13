<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Active (undismissed) system notifications for the nav bar bell.
     * Admin-only — these are ops alerts (e.g. backup health), not a
     * per-user feed, so non-admins just get an empty list.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user?->isAdmin()) {
            return response()->json(SystemNotification::active()->latest()->get());
        }
        // Approvers see only the shipping requests waiting on them.
        if ($user?->hasPermission('shipments.approve')) {
            return response()->json(SystemNotification::active()->where('type', 'shipment_request')->latest()->get());
        }

        return response()->json([]);
    }

    public function dismiss(Request $request, SystemNotification $notification)
    {
        $user = $request->user();
        $allowed = $user?->isAdmin() || ($notification->type === 'shipment_request' && $user?->hasPermission('shipments.approve'));
        if (! $allowed) {
            return response()->json([
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }

        $notification->update([
            'dismissed_at' => now(),
            'dismissed_by_user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Dismissed']);
    }
}
