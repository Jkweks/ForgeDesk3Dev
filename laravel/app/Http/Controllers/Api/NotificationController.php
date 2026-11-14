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
        // Approvers see only the requests waiting on them (shipping, work orders).
        $types = $this->approverTypes($user);
        if ($types) {
            return response()->json(SystemNotification::active()->whereIn('type', $types)->latest()->get());
        }

        return response()->json([]);
    }

    /** Notification types a non-admin may see and dismiss, by the permission that lets them act on it. */
    private function approverTypes($user): array
    {
        $types = [];
        if ($user?->hasPermission('shipments.approve')) {
            $types[] = 'shipment_request';
        }
        if ($user?->hasPermission('fabrication.work-orders.create')) {
            $types[] = 'work_order_request';
        }

        return $types;
    }

    public function dismiss(Request $request, SystemNotification $notification)
    {
        $user = $request->user();
        $allowed = $user?->isAdmin() || in_array($notification->type, $this->approverTypes($user), true);
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
