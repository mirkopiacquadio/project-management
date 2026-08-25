<?php

namespace App\Mail;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusChangedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?string $oldStatusName = null,
        public ?string $newStatusName = null,
        public ?User $author = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail_ticket_status_subject', [
                'uuid' => $this->ticket->uuid,
                'status' => $this->newStatusName ?? '—',
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-notification',
            with: [
                'heading' => __('app.mail_ticket_status_heading'),
                'intro' => __('app.mail_ticket_status_intro', [
                    'author' => $this->author?->name ?? __('app.mail_someone'),
                    'old' => $this->oldStatusName ?? '—',
                    'new' => $this->newStatusName ?? '—',
                ]),
                'ticket' => $this->ticket,
                'bodyTitle' => null,
                'bodyHtml' => null,
                'url' => $this->ticket->adminUrl(),
            ],
        );
    }
}
