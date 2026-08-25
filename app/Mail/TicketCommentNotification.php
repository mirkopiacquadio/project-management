<?php

namespace App\Mail;

use App\Models\TicketComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCommentNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TicketComment $comment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('app.mail_ticket_comment_subject', [
                'uuid' => $this->comment->ticket?->uuid,
                'name' => $this->comment->ticket?->name,
            ]),
        );
    }

    public function content(): Content
    {
        $ticket = $this->comment->ticket;

        return new Content(
            view: 'emails.ticket-notification',
            with: [
                'heading' => __('app.mail_ticket_comment_heading'),
                'intro' => __('app.mail_ticket_comment_intro', [
                    'author' => $this->comment->user?->name ?? __('app.mail_someone'),
                ]),
                'ticket' => $ticket,
                'bodyTitle' => __('app.comments'),
                'bodyHtml' => $this->comment->comment,
                'url' => $ticket?->adminUrl(),
            ],
        );
    }
}
