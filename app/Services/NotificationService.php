<?php

namespace App\Services;

use App\Mail\ProjectAssignmentNotification;
use App\Mail\TicketCommentNotification;
use App\Mail\TicketCreatedNotification;
use App\Mail\TicketStatusChangedNotification;
use App\Models\Notification;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyCommentAdded(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        $commenter = $comment->user;

        $usersToNotify = $this->getUsersToNotifyForComment($ticket, $commenter);

        foreach ($usersToNotify as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'comment_added',
                'title' => __('app.notif_comment_added_title'),
                'message' => __('app.notif_comment_added_message', [
                    'author' => $commenter->name,
                    'ticket' => $ticket->name,
                ]),
                'data' => [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'commenter_id' => $commenter->id,
                    'commenter_name' => $commenter->name,
                ],
            ]);
        }

        $this->sendMailTo($usersToNotify, fn () => new TicketCommentNotification($comment));
    }

    public function notifyCommentUpdated(TicketComment $comment): void
    {
        $ticket = $comment->ticket;
        $commenter = $comment->user;

        $usersToNotify = $this->getUsersToNotifyForComment($ticket, $commenter);

        foreach ($usersToNotify as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'comment_updated',
                'title' => __('app.notif_comment_updated_title'),
                'message' => __('app.notif_comment_updated_message', [
                    'author' => $commenter->name,
                    'ticket' => $ticket->name,
                ]),
                'data' => [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'commenter_id' => $commenter->id,
                    'commenter_name' => $commenter->name,
                ],
            ]);
        }
    }

    /**
     * Nuovo ticket creato: avvisa i super admin (email + notifica in-app).
     */
    public function notifyTicketCreated(Ticket $ticket): void
    {
        $author = $ticket->creator;

        try {
            $recipients = User::query()
                ->role('super_admin')
                ->when($author, fn ($query) => $query->where('id', '!=', $author->id))
                ->get();
        } catch (Exception $e) {
            // Ruolo assente (es. database appena azzerato): non deve bloccare la creazione del ticket.
            Log::warning('Impossibile determinare i destinatari del nuovo ticket: '.$e->getMessage());

            return;
        }

        if ($recipients->isEmpty()) {
            return;
        }

        $authorName = $author?->name ?? __('app.mail_someone');
        $projectName = $ticket->project?->name ?? '-';

        foreach ($recipients as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'ticket_created',
                'title' => __('app.notif_ticket_created_title'),
                'message' => __('app.notif_ticket_created_message', [
                    'author' => $authorName,
                    'ticket' => $ticket->name,
                    'project' => $projectName,
                ]),
                'data' => [
                    'ticket_id' => $ticket->id,
                    'project_id' => $ticket->project_id,
                    'author_id' => $author?->id,
                    'author_name' => $authorName,
                ],
            ]);
        }

        $this->sendMailTo($recipients, fn () => new TicketCreatedNotification($ticket, $author));
    }

    /**
     * Stato del ticket cambiato: avvisa chi lo ha aperto, gli assegnatari e chi ha commentato.
     */
    public function notifyTicketStatusChanged(Ticket $ticket, ?string $oldStatusName, ?string $newStatusName, ?User $author = null): void
    {
        $recipients = $this->getTicketParticipants($ticket, $author);

        if ($recipients->isEmpty()) {
            return;
        }

        $authorName = $author?->name ?? __('app.mail_someone');

        foreach ($recipients as $user) {
            Notification::create([
                'user_id' => $user->id,
                'type' => 'ticket_status_changed',
                'title' => __('app.notif_ticket_status_title'),
                'message' => __('app.notif_ticket_status_message', [
                    'author' => $authorName,
                    'ticket' => $ticket->name,
                    'old' => $oldStatusName ?? '-',
                    'new' => $newStatusName ?? '-',
                ]),
                'data' => [
                    'ticket_id' => $ticket->id,
                    'project_id' => $ticket->project_id,
                    'author_id' => $author?->id,
                    'author_name' => $authorName,
                    'old_status' => $oldStatusName,
                    'new_status' => $newStatusName,
                ],
            ]);
        }

        $this->sendMailTo(
            $recipients,
            fn () => new TicketStatusChangedNotification($ticket, $oldStatusName, $newStatusName, $author)
        );
    }

    /**
     * Chi segue il ticket: autore, assegnatari e chi ha gia' commentato (escluso chi ha fatto l'azione).
     */
    private function getTicketParticipants(Ticket $ticket, ?User $except = null): Collection
    {
        $participants = collect();

        if ($ticket->creator) {
            $participants->push($ticket->creator);
        }

        $participants = $participants
            ->merge($ticket->assignees()->get())
            ->merge(
                $ticket->comments()->with('user')->get()->pluck('user')->filter()
            )
            ->filter()
            ->unique('id');

        if ($except) {
            $participants = $participants->where('id', '!=', $except->id);
        }

        return $participants->values();
    }

    /**
     * Le email sono attive per istanza (TICKET_EMAIL_NOTIFICATIONS nel .env);
     * l'interruttore in Impostazioni di sistema, se usato, ha la precedenza.
     */
    public function emailNotificationsEnabled(): bool
    {
        $default = config('notifications.ticket_emails') ? '1' : '0';

        return (string) Setting::getUserValue('email_notifications_enabled', $default) === '1';
    }

    /**
     * Invia una mail a ogni destinatario senza mai far fallire l'azione che l'ha generata.
     */
    private function sendMailTo(Collection $users, callable $mailableFactory): void
    {
        if (! $this->emailNotificationsEnabled()) {
            return;
        }

        foreach ($users as $user) {
            if (empty($user?->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->send($mailableFactory($user));
            } catch (Exception $e) {
                Log::error('Failed to send ticket notification email: '.$e->getMessage(), [
                    'user_id' => $user->id,
                    'to_email' => $user->email,
                ]);
            }
        }
    }

    private function getUsersToNotifyForComment(Ticket $ticket, User $commenter): Collection
    {
        $usersToNotify = collect();

        if ($ticket->creator && $ticket->creator->id !== $commenter->id) {
            $usersToNotify->push($ticket->creator);
        }

        $assignedUsers = $ticket->assignees()->where('users.id', '!=', $commenter->id)->get();
        $usersToNotify = $usersToNotify->merge($assignedUsers);

        $commenters = $ticket->comments()
            ->with('user')
            ->where('user_id', '!=', $commenter->id)
            ->get()
            ->pluck('user')
            ->unique('id');
        $usersToNotify = $usersToNotify->merge($commenters);

        return $usersToNotify->unique('id');
    }

    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if ($notification) {
            $notification->markAsRead();

            return true;
        }

        return false;
    }

    public function markAllAsRead(int $userId): void
    {
        Notification::where('user_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function notifyProjectAssignment(Project $project, User $assignedUser, User $assignedBy): void
    {
        try {
            Notification::create([
                'user_id' => $assignedUser->id,
                'type' => 'project_assigned',
                'title' => 'Added to Project',
                'message' => "You have been added to project '{$project->name}' by {$assignedBy->name}",
                'data' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'assigned_by_id' => $assignedBy->id,
                    'assigned_by_name' => $assignedBy->name,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to create in-app notification: '.$e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $assignedUser->id,
            ]);
        }

        try {
            $mail = new ProjectAssignmentNotification($project, $assignedUser, $assignedBy);
            Mail::to($assignedUser->email)->send($mail);
        } catch (Exception $e) {
            // Log error but don't fail the assignment
            Log::error('Failed to send project assignment email: '.$e->getMessage(), [
                'project_id' => $project->id,
                'user_id' => $assignedUser->id,
                'assigned_by_id' => $assignedBy->id,
                'to_email' => $assignedUser->email,
            ]);
        }
    }

    public function notifyProjectRemoval(Project $project, User $removedUser, User $removedBy): void
    {
        Notification::create([
            'user_id' => $removedUser->id,
            'type' => 'project_removed',
            'title' => 'Removed from Project',
            'message' => "You have been removed from project '{$project->name}' by {$removedBy->name}",
            'data' => [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'removed_by_id' => $removedBy->id,
                'removed_by_name' => $removedBy->name,
            ],
        ]);
    }
}
