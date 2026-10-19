<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Backup failure email to the ops contact. Mirrors the matching
 * SystemNotification raised in RecordBackupRun (same title/message).
 */
class BackupAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public string $detail,
        public array $components = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ForgeDesk: '.$this->title);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.backups.alert',
            with: [
                'title' => $this->title,
                'detail' => $this->detail,
                'components' => $this->components,
                'url' => rtrim(config('app.url', ''), '/').'/status',
            ],
        );
    }
}
