<?php

namespace App\Mail;

use App\Models\JobReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent once when every special-order item on a reservation is in stock. */
class SpecialOrdersReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public JobReservation $reservation) {}

    public function envelope(): Envelope
    {
        $r = $this->reservation;

        return new Envelope(subject: 'Special orders received: '.trim(($r->job_number ?? '').' R'.($r->release_number ?? '').' '.($r->job_name ?? '')));
    }

    public function content(): Content
    {
        $r = $this->reservation->loadMissing('items.product');

        return new Content(
            markdown: 'mail.reservations.special-orders-received',
            with: [
                'jobNumber' => $r->job_number, 'release' => $r->release_number, 'jobName' => $r->job_name,
                'neededBy' => $r->needed_by?->format('M j, Y'),
                'items' => $r->items->filter(fn ($i) => $i->product?->is_special_order)
                    ->map(fn ($i) => ['sku' => $i->product->sku, 'description' => $i->product->description, 'qty' => (float) $i->requested_qty])->values()->all(),
                'url' => rtrim(config('app.url', ''), '/').'/fulfillment/job-reservations',
            ],
        );
    }
}
