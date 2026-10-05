<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a PO's chosen approver (purchase_orders.approver_id) when it's
 * submitted. Triggered only from PurchaseOrderController::submit().
 */
class PurchaseOrderSubmittedForApproval extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Purchase Order Awaiting Approval: {$this->purchaseOrder->po_number}",
        );
    }

    public function content(): Content
    {
        $po = $this->purchaseOrder->loadMissing('supplier', 'creator');

        return new Content(
            markdown: 'mail.purchase-orders.submitted-for-approval',
            with: [
                'poNumber' => $po->po_number,
                'supplierName' => $po->supplier?->name,
                'totalAmount' => $po->total_amount,
                'submittedBy' => $po->creator?->name,
                'orderDate' => $po->order_date?->format('M j, Y'),
                'url' => rtrim(config('app.url', ''), '/').'/purchase-orders',
            ],
        );
    }
}
