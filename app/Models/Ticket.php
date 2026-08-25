<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'ticket_status_id',
        'priority_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'uuid',
        'epic_id',
        'created_by',
        'sprint_id',
        'sprint_status_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($ticket) {
            if (empty($ticket->uuid)) {
                $project = Project::find($ticket->project_id);
                $prefix = $project ? $project->ticket_prefix : 'TKT';
                $randomString = Str::upper(Str::random(6));

                $ticket->uuid = "{$prefix}-{$randomString}";
            }

            // Set created_by jika belum di-set dan ada user yang login
            if (empty($ticket->created_by) && auth()->id()) {
                $ticket->created_by = auth()->id();
            }
        });

        static::created(function ($ticket) {
            app(NotificationService::class)->notifyTicketCreated($ticket);
        });

        static::updating(function ($ticket) {
            if ($ticket->isDirty('ticket_status_id')) {
                TicketHistory::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => auth()->id(),
                    'ticket_status_id' => $ticket->ticket_status_id,
                ]);
            }
        });

        static::updated(function ($ticket) {
            if (! $ticket->wasChanged('ticket_status_id')) {
                return;
            }

            // Rilettura diretta: la relazione "status" potrebbe essere ancora quella caricata prima del salvataggio.
            $oldStatus = TicketStatus::find($ticket->getOriginal('ticket_status_id'));
            $newStatus = TicketStatus::find($ticket->ticket_status_id);

            app(NotificationService::class)->notifyTicketStatusChanged(
                $ticket,
                $oldStatus?->name,
                $newStatus?->name,
                auth()->user(),
            );
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_users');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->orderBy('created_at', 'desc');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'priority_id');
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function sprintStatus(): BelongsTo
    {
        return $this->belongsTo(SprintStatus::class);
    }

    public function assignUser(User $user): void
    {
        $this->assignees()->syncWithoutDetaching($user->id);
    }

    public function unassignUser(User $user): void
    {
        $this->assignees()->detach($user->id);
    }

    public function assignUsers(array $userIds): void
    {
        $this->assignees()->sync($userIds);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->where('user_id', $user->id)->exists();
    }

    /**
     * URL della pagina di dettaglio nel pannello admin (usato nelle email).
     */
    public function adminUrl(): ?string
    {
        try {
            return route('filament.admin.resources.tickets.view', ['record' => $this->getKey()]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
