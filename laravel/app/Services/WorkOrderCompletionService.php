<?php

namespace App\Services;

use App\Mail\WorkOrderCompletedMail;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Works out who hears about a completed work order and sends the notice.
 *
 * Recipients: the parent job's linked project-manager user (if any) plus every
 * active admin. The list is not user-editable — see the completion prompt.
 */
class WorkOrderCompletionService
{
    /**
     * Distinct, lower-cased recipient email addresses for a work order's
     * completion notice.
     *
     * @return list<string>
     */
    public function recipientsFor(FdWorkOrder $workOrder): array
    {
        $emails = collect();

        $pm = $workOrder->businessJob?->projectManager;
        if ($pm?->email) {
            $emails->push($pm->email);
        }

        $emails = $emails->merge(
            User::query()->where('role', 'admin')->where('is_active', true)->pluck('email')
        );

        return $emails
            ->filter()
            ->map(fn ($e) => mb_strtolower(trim($e)))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Queue the completion notice. Returns the recipient list actually used
     * (empty when nobody could be resolved — caller should surface that).
     *
     * @return list<string>
     */
    public function send(FdWorkOrder $workOrder, ?string $note = null, ?string $completedBy = null): array
    {
        $recipients = $this->recipientsFor($workOrder);

        if ($recipients !== []) {
            Mail::to($recipients)->send(new WorkOrderCompletedMail($workOrder, $note, $completedBy));
        }

        return $recipients;
    }
}
