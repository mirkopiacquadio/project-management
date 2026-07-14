<?php

namespace App\Filament\Pages;

use App\Exports\TicketsExport;
use App\Filament\Actions\ExportTicketsAction;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Maatwebsite\Excel\Facades\Excel;

class ProjectBoard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected string $view = 'filament.pages.project-board';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('app.project_board');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('app.project_board');
    }

    public function getSubheading(): ?string
    {
        return __('app.subheading_board');
    }

    protected static ?string $slug = 'project-board/{project_id?}';

    public ?Project $selectedProject = null;

    public Collection $projects;

    // ticketStatuses is now a computed property - see getTicketStatusesProperty()

    public ?Ticket $selectedTicket = null;

    public ?int $selectedProjectId = null;

    public array $sortOrders = [];

    public array $selectedUserIds = [];

    public array $selectedPriorityIds = [];

    public Collection $projectUsers;

    public string $searchProject = '';

    public function mount($project_id = null): void
    {
        if (auth()->user()->hasRole(['super_admin'])) {
            $this->projects = Project::orderByRaw('pinned_date IS NULL')
                ->orderBy('pinned_date', 'desc')
                ->orderBy('name')
                ->get();
        } else {
            $this->projects = auth()->user()->projects()
                ->orderByRaw('pinned_date IS NULL')
                ->orderBy('pinned_date', 'desc')
                ->orderBy('name')
                ->get();
        }

        if ($project_id) {
            $this->selectedProjectId = (int) $project_id;
            $this->selectedProject = Project::find($project_id);
            $this->loadProjectUsers();
        } else {
            $this->projectUsers = collect();
        }
    }

    public function getFilteredProjectsProperty(): Collection
    {
        if (empty($this->searchProject)) {
            return $this->projects;
        }

        return $this->projects->filter(function ($project) {
            return str_contains(strtolower($project->name), strtolower($this->searchProject)) ||
                str_contains(strtolower($project->ticket_prefix ?? ''), strtolower($this->searchProject));
        });
    }

    public function updatedSelectedProjectId($value): void
    {
        if ($value) {
            $this->selectProject($value);
        } else {
            $this->selectedProject = null;
            $this->projectUsers = collect();
            $this->selectedUserIds = [];

            // Full page load (not Livewire.navigate): the board's drag&drop JS
            // needs a fresh Livewire snapshot, otherwise the first drag fails.
            $url = static::getUrl();
            $this->js("window.location.href = '{$url}'");
        }
    }

    public function selectProject(int $projectId): void
    {
        $this->selectedTicket = null;
        $this->ticketStatuses = collect();
        $this->selectedProjectId = $projectId;
        $this->selectedProject = Project::with('tickets')->find($projectId);
        $this->selectedUserIds = [];

        if ($this->selectedProject) {
            $this->loadProjectUsers();

            // Full page load (not Livewire.navigate): the board's drag&drop JS
            // needs a fresh Livewire snapshot, otherwise the first drag fails.
            $url = static::getUrl(['project_id' => $projectId]);
            $this->js("window.location.href = '{$url}'");
        }
    }

    #[Computed]
    public function ticketStatuses(): Collection
    {
        if (! $this->selectedProject) {
            return collect();
        }

        // Board columns are the global statuses; each column shows this
        // project's tickets that sit in that status.
        $statuses = TicketStatus::query()->global()
            ->select('id', 'name', 'color', 'sort_order', 'is_completed')
            ->orderBy('sort_order')
            ->get();

        $projectId = $this->selectedProject->id;

        $statuses->each(function ($status) use ($projectId) {
            $tickets = Ticket::query()
                ->where('project_id', $projectId)
                ->where('ticket_status_id', $status->id)
                ->with([
                    'assignees:id,name',
                    'status:id,name,color,is_completed',
                    'priority:id,name,color',
                    'creator:id,name',
                ])
                ->select('id', 'project_id', 'ticket_status_id', 'priority_id', 'name', 'description', 'uuid', 'due_date', 'created_at', 'updated_at', 'created_by')
                ->when(! empty($this->selectedPriorityIds), function ($query) {
                    $query->whereIn('priority_id', $this->selectedPriorityIds);
                })
                ->when(! empty($this->selectedUserIds), function ($query) {
                    $query->whereHas('assignees', function ($assigneeQuery) {
                        $assigneeQuery->whereIn('users.id', $this->selectedUserIds);
                    });
                })
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            $sortOrder = $this->sortOrders[$status->id] ?? 'date_created_newest';
            $status->setRelation('tickets', $this->applySorting($tickets, $sortOrder));
        });

        return $statuses;
    }

    public function loadTicketStatuses(): void
    {
        // Force recompute of ticketStatuses by clearing cache
        unset($this->ticketStatuses);
    }

    public function loadProjectUsers(): void
    {
        if (! $this->selectedProject) {
            $this->projectUsers = collect();

            return;
        }

        // Get only users who are assigned to tickets in this project
        $ticketAssigneeIds = $this->selectedProject->tickets()
            ->with('assignees')
            ->get()
            ->flatMap(function ($ticket) {
                return $ticket->assignees->pluck('id');
            })
            ->unique()
            ->filter();

        $this->projectUsers = User::whereIn('id', $ticketAssigneeIds)
            ->orderBy('name')
            ->get();
    }

    public function updatedSelectedUserIds(): void
    {
        $this->loadTicketStatuses();
    }

    public function clearUserFilter(): void
    {
        $this->selectedUserIds = [];
        $this->loadTicketStatuses();
    }

    public function setSortOrder($statusId, $sortOrder)
    {
        $this->sortOrders[$statusId] = $sortOrder;
        $this->loadTicketStatuses();
    }

    private function applySorting($tickets, $sortOrder)
    {
        switch ($sortOrder) {
            case 'date_created_newest':
                // Query already ordered by created_at DESC, id DESC - just reset keys
                return $tickets->values();
            case 'date_created_oldest':
                return $tickets->sortBy(function ($ticket) {
                    return $ticket->created_at->timestamp.'_'.str_pad($ticket->id, 10, '0', STR_PAD_LEFT);
                })->values();
            case 'card_name_alphabetical':
                return $tickets->sortBy('name')->values();
            case 'due_date':
                return $tickets->sortBy(function ($ticket) {
                    return $ticket->due_date ?? '9999-12-31';
                })->values();
            case 'priority':
                return $tickets->sortBy(function ($ticket) {
                    return $ticket->priority ? $ticket->priority->id : 999;
                })->values();
            default:
                // Default is same as date_created_newest - query already sorted
                return $tickets->values();
        }
    }

    #[On('ticket-moved')]
    public function moveTicket($ticketId, $newStatusId): void
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket && $ticket->project_id === $this->selectedProject?->id) {
            if (! $this->canManageTicket($ticket)) {
                Notification::make()
                    ->title(__('app.permission_denied'))
                    ->body(__('app.no_perm_move'))
                    ->danger()
                    ->send();

                return;
            }

            $ticket->update([
                'ticket_status_id' => $newStatusId,
            ]);

            $this->loadTicketStatuses();

            $this->dispatch('ticket-updated');

            Notification::make()
                ->title(__('app.ticket_updated'))
                ->success()
                ->send();
        }
    }

    #[On('refresh-board')]
    public function refreshBoard(): void
    {
        $this->loadTicketStatuses();
        $this->dispatch('ticket-updated');
    }

    public function showTicketDetails(int $ticketId): void
    {
        $ticket = Ticket::with(['assignees', 'status', 'project', 'priority'])->find($ticketId);

        if (! $ticket) {
            Notification::make()
                ->title(__('app.ticket_not_found'))
                ->danger()
                ->send();

            return;
        }

        $url = TicketResource::getUrl('view', ['record' => $ticketId]);
        $this->js("window.open('{$url}', '_blank')");
    }

    public function closeTicketDetails(): void
    {
        $this->selectedTicket = null;
    }

    public function editTicket(int $ticketId): void
    {
        $ticket = Ticket::find($ticketId);

        if (! $this->canEditTicket($ticket)) {
            Notification::make()
                ->title(__('app.permission_denied'))
                ->body(__('app.no_perm_edit'))
                ->danger()
                ->send();

            return;
        }

        $this->redirect(TicketResource::getUrl('edit', ['record' => $ticketId]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_ticket')
                ->name('ticket_on_board')
                ->label(__('app.ticket'))
                ->icon('heroicon-m-plus')
                ->visible(fn () => $this->selectedProject !== null && auth()->user()->can('create_ticket'))
                ->schema(fn ($schema) => TicketResource::form($schema)
                    ->columns(3)
                )
                ->model(Ticket::class)
                ->fillForm(function () {
                    $assignees = [];

                    // Auto-assign current user if they're a project member
                    if ($project = $this->selectedProject) {
                        $isCurrentUserMember = $project->members()->where('users.id', auth()->id())->exists();
                        $assignees = $isCurrentUserMember ? [auth()->id()] : [];
                    }

                    return [
                        'project_id' => $this->selectedProject?->id,
                        'ticket_status_id' => $this->ticketStatuses?->first()?->id,
                        'assignees' => $assignees,
                    ];

                })
                ->action(function (array $data, $schema) {
                    $data['created_by'] = auth()->id();

                    $model = $schema->getModel();

                    $record = $model::create($data);

                    $schema->model($record)->saveRelationships();

                    Notification::make()
                        ->title(__('app.ticket_created'))
                        ->body(__('app.ticket_created_body'))
                        ->success()
                        ->send();
                }),

            Action::make('refresh_board')
                ->label(__('app.refresh_board'))
                ->icon('heroicon-m-arrow-path')
                ->action('refreshBoard')
                ->color('warning'),
            ExportTicketsAction::make()
                ->visible(fn () => $this->selectedProject !== null && auth()->user()->hasRole(['super_admin'])),

            Action::make('filters')
                ->label(__('app.filters'))
                ->icon('heroicon-m-funnel')
                ->badge(fn (): ?int => $this->activeFilterCount() ?: null)
                ->visible(fn () => $this->selectedProject !== null)
                ->schema([
                    CheckboxList::make('selectedPriorityIds')
                        ->label(__('app.priority'))
                        ->options(fn () => TicketPriority::orderBy('id')->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->bulkToggleable(),
                    CheckboxList::make('selectedUserIds')
                        ->label(__('app.select_users_filter'))
                        ->options(fn () => $this->projectUsers->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                        ->visible(fn () => $this->projectUsers->isNotEmpty()),
                ])
                ->action(function (array $data) {
                    $this->selectedPriorityIds = $data['selectedPriorityIds'] ?? [];
                    $this->selectedUserIds = $data['selectedUserIds'] ?? [];
                    // refreshBoard() also re-emits the event that re-attaches the
                    // drag&drop listeners to the newly rendered cards.
                    $this->refreshBoard();
                })
                ->fillForm(fn (): array => [
                    'selectedPriorityIds' => $this->selectedPriorityIds,
                    'selectedUserIds' => $this->selectedUserIds,
                ])
                ->modalWidth('lg')
                ->modalSubmitActionLabel(__('app.apply_filters'))
                ->color('info'),

            Action::make('reset_filters')
                ->label(__('app.reset_filters'))
                ->icon('heroicon-m-x-mark')
                ->color('gray')
                ->visible(fn () => $this->selectedProject !== null && $this->activeFilterCount() > 0)
                ->action(function () {
                    $this->selectedPriorityIds = [];
                    $this->selectedUserIds = [];
                    $this->refreshBoard();
                }),
        ];
    }

    /** Number of active filter selections (for the button badge). */
    protected function activeFilterCount(): int
    {
        return count($this->selectedPriorityIds) + count($this->selectedUserIds);
    }

    private function canViewTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        if (! auth()->user()->can('view_ticket')) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canEditTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }

        // Check Filament Shield permission for updating tickets
        if (! auth()->user()->can('update_ticket')) {
            return false;
        }

        // Additional business logic: user can edit if they are:
        // 1. Super admin (already covered by permission above)
        // 2. The ticket creator
        // 3. Assigned to the ticket
        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    private function canManageTicket(?Ticket $ticket): bool
    {
        if (! $ticket) {
            return false;
        }
        if (! auth()->user()->can('update_ticket')) {
            return false;
        }

        return auth()->user()->hasRole(['super_admin'])
            || $ticket->user_id === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    public function exportTickets(array $selectedColumns): void
    {
        if (empty($selectedColumns)) {
            Notification::make()
                ->title(__('app.export_failed'))
                ->body(__('app.export_no_columns'))
                ->danger()
                ->send();

            return;
        }

        $tickets = collect();

        if ($this->selectedProject) {
            $tickets = $this->selectedProject->tickets()
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($this->ticketStatuses->isNotEmpty()) {
            $ticketIds = $this->ticketStatuses->flatMap(function ($status) {
                return $status->tickets->pluck('id');
            });

            $tickets = Ticket::whereIn('id', $ticketIds)
                ->with(['assignees', 'status', 'project', 'epic'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        if ($tickets->isEmpty()) {
            Notification::make()
                ->title(__('app.export_failed'))
                ->body(__('app.export_no_tickets'))
                ->warning()
                ->send();

            return;
        }

        try {
            $fileName = 'tickets_'.($this->selectedProject?->name ?? 'export').'_'.now()->format('Y-m-d_H-i-s').'.xlsx';
            $fileName = Str::slug($fileName, '_').'.xlsx';
            $export = new TicketsExport($tickets, $selectedColumns);
            Excel::store($export, 'exports/'.$fileName, 'public');
            $downloadUrl = asset('storage/exports/'.$fileName);
            $this->js("
                fetch('{$downloadUrl}')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = '{$fileName}';
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    });
            ");

            Notification::make()
                ->title(__('app.export_successful'))
                ->body(__('app.export_downloading'))
                ->success()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title(__('app.export_failed'))
                ->body(__('app.export_error_prefix').$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function canMoveTickets(): bool
    {
        return auth()->user()->can('update_ticket');
    }
}
