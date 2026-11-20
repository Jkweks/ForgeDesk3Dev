<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Active (undismissed) system notifications for the nav bar bell.
     * The shared feed is ops alerts (admins) and requests waiting on an approver; on top of that anyone gets the
     * notifications addressed to them personally (e.g. a PM told that Fabrication changed their work order request).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        // addressed to this person (anyone can have these), plus the shared ops feed they are allowed to see
        $mine = SystemNotification::active()->where('user_id', $user?->id);
        if ($user?->isAdmin()) {
            $shared = SystemNotification::active()->whereNull('user_id');
        } elseif ($types = $this->approverTypes($user)) {
            // Approvers see only the requests waiting on them (shipping, work orders).
            $shared = SystemNotification::active()->whereNull('user_id')->whereIn('type', $types);
        } else {
            $shared = null;
        }

        return response()->json($user ? $mine->get()->concat($shared ? $shared->get() : collect())->sortByDesc('id')->values() : []);
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
        $allowed = $notification->user_id !== null
            ? $notification->user_id === $user?->id          // a personal notification is dismissed by its person, nobody else
            : ($user?->isAdmin() || in_array($notification->type, $this->approverTypes($user), true));
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
