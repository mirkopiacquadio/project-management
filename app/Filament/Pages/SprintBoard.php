<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class SprintBoard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected string $view = 'filament.pages.sprint-board';

    protected static ?string $title = null;

    protected static ?string $navigationLabel = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'sprint-board/{sprint_id?}';

    public ?Sprint $selectedSprint = null;

    public Collection $sprints;

    public ?int $selectedSprintId = null;

    public array $selectedUserIds = [];

    public array $selectedProjectIds = [];

    public array $selectedPriorityIds = [];

    public Collection $sprintUsers;

    public static function getNavigationLabel(): string
    {
        return __('app.sprint_board');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('app.sprint_board');
    }

    public function getSubheading(): ?string
    {
        return __('app.subheading_sprint_board');
    }

    public function mount($sprint_id = null): void
    {
        $this->sprints = $this->visibleSprintsQuery()
            ->orderByRaw("FIELD(status, 'active', 'planning', 'completed')")
            ->orderByDesc('created_at')
            ->get();

        $this->sprintUsers = collect();

        // Single-sprint mode: skip the picker and open the current sprint directly.
        if (! $sprint_id && ! Sprint::allowsMultiple()) {
            $primary = $this->sprints->first();

            if ($primary) {
                $this->redirect(static::getUrl(['sprint_id' => $primary->id]));

                return;
            }
        }

        if ($sprint_id) {
            $this->selectedSprintId = (int) $sprint_id;
            $this->selectedSprint = $this->visibleSprintsQuery()->find($sprint_id);
            $this->loadSprintUsers();
        }
    }

    protected function visibleSprintsQuery()
    {
        $query = Sprint::query();

        if (! auth()->user()->hasRole('super_admin')) {
            $query->whereHas('members', function ($membersQuery) {
                $membersQuery->where('users.id', auth()->id());
            });
        }

        return $query;
    }

    public function selectSprint(int $sprintId): void
    {
        $sprint = $this->visibleSprintsQuery()->find($sprintId);

        if ($sprint) {
            // Full page load (not Livewire.navigate): the board's drag&drop JS
            // needs a fresh Livewire snapshot, otherwise the first drag fails.
            $url = static::getUrl(['sprint_id' => $sprintId]);
            $this->js("window.location.href = '{$url}'");
        }
    }

    #[Computed]
    public function sprintStatuses(): Collection
    {
        if (! $this->selectedSprint) {
            return collect();
        }

        // Sprint board columns are the same global statuses as the project
        // board; a ticket has a single, shared status.
        $statuses = TicketStatus::query()->global()
            ->select('id', 'name', 'color', 'sort_order', 'is_completed')
            ->orderBy('sort_order')
            ->get();

        $sprintId = $this->selectedSprint->id;

        $statuses->each(function ($status) use ($sprintId) {
            $tickets = Ticket::query()
                ->where('sprint_id', $sprintId)
                ->where('ticket_status_id', $status->id)
                ->with([
                    'assignees:id,name',
                    'priority:id,name,color',
                    'project:id,name,color,ticket_prefix',
                ])
                ->select('id', 'project_id', 'ticket_status_id', 'priority_id', 'sprint_id', 'name', 'description', 'uuid', 'due_date', 'created_at', 'updated_at', 'created_by')
                ->when(! empty($this->selectedProjectIds), function ($query) {
                    $query->whereIn('project_id', $this->selectedProjectIds);
                })
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

            $status->setRelation('tickets', $tickets);
        });

        return $statuses;
    }

    public function loadSprintStatuses(): void
    {
        unset($this->sprintStatuses);
    }

    public function loadSprintUsers(): void
    {
        if (! $this->selectedSprint) {
            $this->sprintUsers = collect();

            return;
        }

        $this->sprintUsers = $this->selectedSprint->members()->orderBy('name')->get();
    }

    public function updatedSelectedUserIds(): void
    {
        $this->loadSprintStatuses();
    }

    #[On('ticket-moved')]
    public function moveTicket($ticketId, $newStatusId): void
    {
        $ticket = Ticket::find($ticketId);

        if ($ticket && $ticket->sprint_id === $this->selectedSprint?->id) {
            if (! $this->canManageTicket($ticket)) {
                Notification::make()
                    ->title(__('app.permission_denied'))
                    ->body(__('app.no_perm_move'))
                    ->danger()
                    ->send();

                return;
            }

            $validStatus = TicketStatus::query()->global()->whereKey($newStatusId)->exists();

            if (! $validStatus) {
                return;
            }

            // Statuses are shared: moving here updates the ticket's single
            // status, so the project board reflects the change too.
            $ticket->update([
                'ticket_status_id' => $newStatusId,
            ]);

            $this->loadSprintStatuses();

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
        $this->loadSprintStatuses();
        $this->dispatch('ticket-updated');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_ticket')
                ->label(__('app.ticket'))
                ->icon('heroicon-m-plus')
                ->visible(fn () => $this->selectedSprint !== null && auth()->user()->can('create_ticket'))
                ->schema(fn ($schema) => TicketResource::form($schema)->columns(3))
                ->model(Ticket::class)
                ->fillForm(fn (): array => [
                    'ticket_status_id' => TicketStatus::defaultStatus()?->id,
                ])
                ->action(function (array $data, $schema) {
                    // New tickets created here belong to the current sprint and
                    // keep the shared global status chosen in the form.
                    $data['created_by'] = auth()->id();
                    $data['sprint_id'] = $this->selectedSprint->id;

                    $model = $schema->getModel();

                    $record = $model::create($data);

                    $schema->model($record)->saveRelationships();

                    $this->refreshBoard();

                    Notification::make()
                        ->title(__('app.ticket_created'))
                        ->body(__('app.ticket_created_body'))
                        ->success()
                        ->send();
                }),

            Action::make('add_tickets')
                ->label(__('app.add_tickets_to_sprint'))
                ->icon('heroicon-m-plus')
                ->visible(fn () => $this->selectedSprint !== null && auth()->user()->can('update_ticket'))
                ->schema([
                    Select::make('project_id')
                        ->label(__('app.project'))
                        ->placeholder(__('app.all_projects'))
                        ->options(function () {
                            $query = auth()->user()->hasRole('super_admin')
                                ? Project::query()
                                : auth()->user()->projects();

                            return $query->orderBy('name')->pluck('name', 'id');
                        })
                        ->helperText(__('app.filter_tickets_by_project'))
                        ->live(),
                    CheckboxList::make('ticket_ids')
                        ->label(__('app.select_tickets'))
                        ->options(fn ($get) => $this->unassignedTicketOptions($get('project_id')))
                        ->searchable()
                        ->bulkToggleable()
                        ->required()
                        ->helperText(__('app.only_unassigned_sprint_tickets')),
                ])
                ->action(function (array $data) {
                    // Keep each ticket's current (shared) status; only attach it
                    // to the sprint.
                    Ticket::whereIn('id', $data['ticket_ids'])
                        ->whereNull('sprint_id')
                        ->update([
                            'sprint_id' => $this->selectedSprint->id,
                        ]);

                    $this->refreshBoard();

                    Notification::make()
                        ->title(__('app.tickets_added_to_sprint'))
                        ->success()
                        ->send();
                }),

            Action::make('refresh_board')
                ->label(__('app.refresh_board'))
                ->icon('heroicon-m-arrow-path')
                ->action('refreshBoard')
                ->color('warning'),

            Action::make('filters')
                ->label(__('app.filters'))
                ->icon('heroicon-m-funnel')
                ->badge(fn (): ?int => $this->activeFilterCount() ?: null)
                ->visible(fn () => $this->selectedSprint !== null)
                ->schema([
                    CheckboxList::make('selectedProjectIds')
                        ->label(__('app.project'))
                        ->options(fn () => $this->sprintProjectOptions())
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                        ->visible(fn () => ! empty($this->sprintProjectOptions())),
                    CheckboxList::make('selectedPriorityIds')
                        ->label(__('app.priority'))
                        ->options(fn () => TicketPriority::orderBy('id')->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->bulkToggleable(),
                    CheckboxList::make('selectedUserIds')
                        ->label(__('app.select_users_filter'))
                        ->options(fn () => $this->sprintUsers->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable()
                        ->visible(fn () => $this->sprintUsers->isNotEmpty()),
                ])
                ->action(function (array $data) {
                    $this->selectedProjectIds = $data['selectedProjectIds'] ?? [];
                    $this->selectedPriorityIds = $data['selectedPriorityIds'] ?? [];
                    $this->selectedUserIds = $data['selectedUserIds'] ?? [];
                    // refreshBoard() also re-emits the event that re-attaches the
                    // drag&drop listeners to the newly rendered cards.
                    $this->refreshBoard();
                })
                ->fillForm(fn (): array => [
                    'selectedProjectIds' => $this->selectedProjectIds,
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
                ->visible(fn () => $this->selectedSprint !== null && $this->activeFilterCount() > 0)
                ->action(function () {
                    $this->selectedProjectIds = [];
                    $this->selectedPriorityIds = [];
                    $this->selectedUserIds = [];
                    $this->refreshBoard();
                }),
        ];
    }

    /** Number of active filter groups (for the button badge). */
    protected function activeFilterCount(): int
    {
        return count($this->selectedProjectIds)
            + count($this->selectedPriorityIds)
            + count($this->selectedUserIds);
    }

    /** Projects that actually have tickets in the current sprint. */
    protected function sprintProjectOptions(): array
    {
        if (! $this->selectedSprint) {
            return [];
        }

        return Project::whereIn(
            'id',
            Ticket::where('sprint_id', $this->selectedSprint->id)->distinct()->pluck('project_id')
        )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function showTicketDetails(int $ticketId): void
    {
        $url = TicketResource::getUrl('view', ['record' => $ticketId]);
        $this->js("window.open('{$url}', '_blank')");
    }

    /**
     * Tickets not yet assigned to any sprint, across every project the user can
     * see (optionally narrowed to one project). Used by the "Add tickets" modal
     * so all available tickets can be added at once.
     *
     * @return array<int, string>
     */
    protected function unassignedTicketOptions($projectId = null): array
    {
        $query = Ticket::query()->whereNull('sprint_id');

        if (! auth()->user()->hasRole('super_admin')) {
            $query->whereIn('project_id', auth()->user()->projects()->pluck('projects.id'));
        }

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return $query->with('project:id,ticket_prefix')
            ->orderBy('project_id')
            ->orderBy('uuid')
            ->get()
            ->mapWithKeys(function (Ticket $ticket) {
                $prefix = $ticket->project?->ticket_prefix ? $ticket->project->ticket_prefix.' · ' : '';

                return [$ticket->id => "{$prefix}{$ticket->uuid} - {$ticket->name}"];
            })
            ->toArray();
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
            || $ticket->created_by === auth()->id()
            || $ticket->assignees()->where('users.id', auth()->id())->exists();
    }

    public function canMoveTickets(): bool
    {
        return auth()->user()->can('update_ticket');
    }
}
