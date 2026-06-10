<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
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

        return $this->selectedSprint->statuses()
            ->with([
                'tickets' => function ($query) {
                    $query->with([
                        'assignees:id,name',
                        'priority:id,name,color',
                        'project:id,name,color,ticket_prefix',
                    ])
                        ->select('id', 'project_id', 'ticket_status_id', 'priority_id', 'sprint_id', 'sprint_status_id', 'name', 'description', 'uuid', 'due_date', 'created_at', 'updated_at', 'created_by')
                        ->when(! empty($this->selectedUserIds), function ($query) {
                            $query->whereHas('assignees', function ($assigneeQuery) {
                                $assigneeQuery->whereIn('users.id', $this->selectedUserIds);
                            });
                        })
                        ->orderByDesc('created_at')
                        ->orderByDesc('id');
                },
            ])
            ->orderBy('sort_order')
            ->get();
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

            $validStatus = $this->selectedSprint->statuses()->where('id', $newStatusId)->exists();

            if (! $validStatus) {
                return;
            }

            $ticket->update([
                'sprint_status_id' => $newStatusId,
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
            Action::make('add_tickets')
                ->label(__('app.add_tickets_to_sprint'))
                ->icon('heroicon-m-plus')
                ->visible(fn () => $this->selectedSprint !== null && auth()->user()->can('update_ticket'))
                ->schema([
                    Select::make('project_id')
                        ->label(__('app.project'))
                        ->options(function () {
                            $query = auth()->user()->hasRole('super_admin')
                                ? Project::query()
                                : auth()->user()->projects();

                            return $query->orderBy('name')->pluck('name', 'id');
                        })
                        ->live()
                        ->required(),
                    Select::make('ticket_ids')
                        ->label(__('app.select_tickets'))
                        ->options(function ($get) {
                            if (! $get('project_id')) {
                                return [];
                            }

                            return Ticket::where('project_id', $get('project_id'))
                                ->whereNull('sprint_id')
                                ->orderBy('uuid')
                                ->get()
                                ->mapWithKeys(fn (Ticket $ticket) => [$ticket->id => "{$ticket->uuid} - {$ticket->name}"]);
                        })
                        ->multiple()
                        ->searchable()
                        ->required()
                        ->helperText(__('app.only_unassigned_sprint_tickets')),
                ])
                ->action(function (array $data) {
                    $defaultStatusId = $this->selectedSprint->statuses()->orderBy('sort_order')->value('id');

                    Ticket::whereIn('id', $data['ticket_ids'])
                        ->whereNull('sprint_id')
                        ->update([
                            'sprint_id' => $this->selectedSprint->id,
                            'sprint_status_id' => $defaultStatusId,
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

            Action::make('filter_users')
                ->label(__('app.filter_by_user'))
                ->icon('heroicon-m-user-group')
                ->visible(fn () => $this->selectedSprint !== null && $this->sprintUsers->isNotEmpty())
                ->schema([
                    CheckboxList::make('selectedUserIds')
                        ->label(__('app.select_users_filter'))
                        ->options(fn () => $this->sprintUsers->pluck('name', 'id')->toArray())
                        ->columns(2)
                        ->searchable()
                        ->bulkToggleable(),
                ])
                ->action(function (array $data) {
                    $this->selectedUserIds = $data['selectedUserIds'] ?? [];
                    $this->loadSprintStatuses();
                })
                ->fillForm([
                    'selectedUserIds' => $this->selectedUserIds,
                ])
                ->modalWidth('md')
                ->color('info'),
        ];
    }

    public function showTicketDetails(int $ticketId): void
    {
        $url = TicketResource::getUrl('view', ['record' => $ticketId]);
        $this->js("window.open('{$url}', '_blank')");
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
