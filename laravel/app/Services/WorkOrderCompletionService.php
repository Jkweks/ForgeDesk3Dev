<?php

namespace App\Services;

use App\Mail\WorkOrderCompletedMail;
use App\Models\FdWorkOrder;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * Works out who hears about a completed work order and sends the notice.
 *
 * Recipients: the parent job's linked project-manager and site-superintendent
 * users (if any, unless disabled) plus every active admin. The list is not user-editable — see
 * the completion prompt.
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

        $job = $workOrder->businessJob;

        // Linked people are emailed even without a login (is_active = false); only a disabled
        // user (left the company) is skipped.
        foreach ([$job?->projectManager, $job?->superintendentUser] as $person) {
            if ($person?->email && ! $person->is_disabled) {
                $emails->push($person->email);
            }
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
