<?php

namespace App\Mail;

use App\Models\FdWorkOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent when the office confirms a work order is complete and opts in to
 * notifying the project manager. Goes to the job's linked PM plus every active
 * admin. Triggered only from WorkOrderController::sendCompletionEmail().
 */
class WorkOrderCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public FdWorkOrder $workOrder,
        public ?string $note = null,
        public ?string $completedBy = null,
    ) {}

    public function envelope(): Envelope
    {
        $jobName = $this->workOrder->businessJob?->job_name ?: 'Job';
        $woNumber = 'WO'.$this->workOrder->release_number;
        $hasNote = trim((string) $this->note) !== '';

        return new Envelope(
            subject: "Work Order Complete: {$jobName} - {$woNumber}".($hasNote ? ' (see notes)' : ''),
        );
    }

    public function content(): Content
    {
        $job = $this->workOrder->businessJob;

        return new Content(
            markdown: 'mail.work-orders.completed',
            with: [
                'label' => $this->workOrder->releaseLabel(),
                'jobNumber' => $job?->job_number,
                'jobName' => $job?->job_name,
                'completedAt' => $this->workOrder->completed_at?->format('M j, Y'),
                'completedBy' => $this->completedBy,
                'note' => $this->note,
                'url' => rtrim(config('app.url', ''), '/').'/fabrication/work-orders',
            ],
        );
    }
}
