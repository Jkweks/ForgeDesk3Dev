<?php

namespace App\Services;

use App\Mail\SpecialOrdersReceivedMail;
use App\Models\JobReservation;
use App\Models\SystemNotification;
use Illuminate\Support\Facades\Mail;

/**
 * "Notify me when the special orders are in" on a job reservation.
 *
 * A reservation is ready when it has at least one open special-order line and, for every
 * such line, the product's stock on hand covers the open quantity (requested - consumed)
 * without being over-committed to other active reservations. Stock is shared, so a part
 * received for another job's earlier reservation does not count twice.
 *
 * Fires once per reservation. Run after receiving stock (PO receipt) and hourly as a
 * safety net for manual adjustments / cycle counts.
 */
class SpecialOrderReceivedNotifier
{
    public const OPEN_STATUSES = ['draft', 'active', 'in_progress', 'on_hold'];

    /**
     * @param  list<int>|null  $productIds  only reservations touching these products (null = all)
     * @return int number of reservations notified
     */
    public function check(?array $productIds = null): int
    {
        $query = JobReservation::query()
            ->where('notify_special_received', true)
            ->whereNull('special_received_notified_at')
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereHas('items.product', fn ($q) => $q->where('is_special_order', true));

        if ($productIds !== null) {
            if ($productIds === []) {
                return 0;
            }
            $query->whereHas('items', fn ($q) => $q->whereIn('product_id', $productIds));
        }

        $sent = 0;
        foreach ($query->with(['items.product', 'notifyUsers'])->get() as $reservation) {
            if ($this->isReady($reservation) && $this->notify($reservation)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function isReady(JobReservation $reservation): bool
    {
        $special = $reservation->items->filter(fn ($i) => $i->product?->is_special_order
            && (float) $i->requested_qty - (float) $i->consumed_qty > 0);

        if ($special->isEmpty()) {
            return false;
        }

        return $special->every(function ($i) {
            $open = (float) $i->requested_qty - (float) $i->consumed_qty;

            return (float) $i->product->quantity_on_hand >= $open && (float) $i->product->quantity_available >= 0;
        });
    }

    private function notify(JobReservation $reservation): bool
    {
        $label = trim(($reservation->job_number ?? '').' R'.($reservation->release_number ?? '').' '.($reservation->job_name ?? ''));
        $emails = $reservation->notifyUsers->pluck('email')->filter()->map(fn ($e) => mb_strtolower(trim($e)))->unique()->values()->all();

        // In-app notice always; email to the chosen people who have an address on file.
        $title = "Special orders received: {$label}";
        if (! SystemNotification::ofType('special_orders_received')->where('title', $title)->exists()) {
            SystemNotification::create([
                'type' => 'special_orders_received', 'level' => 'success', 'title' => $title,
                'message' => 'All special-order items for this reservation are now in stock.',
                'data' => ['job_reservation_id' => $reservation->id, 'notify_user_ids' => $reservation->notifyUsers->pluck('id')->all()],
            ]);
        }

        if ($emails !== []) {
            Mail::to($emails)->send(new SpecialOrdersReceivedMail($reservation));
        }

        $reservation->forceFill(['special_received_notified_at' => now()])->save();

        return true;
    }
}
