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

class TicketCreatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public ?User $author = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail_ticket_created_subject', [
                'uuid' => $this->ticket->uuid,
                'name' => $this->ticket->name,
            ]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-notification',
            with: [
                'heading' => __('app.mail_ticket_created_heading'),
                'intro' => __('app.mail_ticket_created_intro', [
                    'author' => $this->author?->name ?? __('app.mail_someone'),
                    'project' => $this->ticket->project?->name ?? '—',
                ]),
                'ticket' => $this->ticket,
                'bodyTitle' => __('app.description'),
                'bodyHtml' => $this->ticket->description,
                'url' => $this->ticket->adminUrl(),
            ],
        );
    }
}
