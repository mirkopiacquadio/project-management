<?php

namespace App\Filament\Pages;

use App\Models\Epic;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

class EpicsOverview extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';
    protected string $view = 'filament.pages.epics-overview';
    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';
    protected static ?string $navigationLabel = null;
    protected static ?string $title = null;
    protected static ?int $navigationSort = 7;

    public static function getNavigationLabel(): string
    {
        return __('app.epics');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('app.epics');
    }

    public function getSubheading(): ?string
    {
        return __('app.subheading_epics');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createEpic')
                ->label(__('app.create_epic'))
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => $this->selectedProjectId !== null)
                ->modalHeading(__('app.create_epic'))
                ->schema($this->epicFormSchema())
                ->action(fn (array $data) => $this->createEpic($data)),
        ];
    }

    /**
     * Form fields shared by the create and edit epic actions.
     *
     * @return array<int, mixed>
     */
    protected function epicFormSchema(): array
    {
        return [
            TextInput::make('name')
                ->label(__('app.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('sort_order')
                ->label(__('app.sort_order'))
                ->helperText(__('app.lower_numbers_first'))
                ->numeric()
                ->default(0),
            DatePicker::make('start_date')
                ->label(__('app.start_date'))
                ->native(false)
                ->displayFormat('d/m/Y'),
            DatePicker::make('end_date')
                ->label(__('app.end_date'))
                ->native(false)
                ->displayFormat('d/m/Y'),
            RichEditor::make('description')
                ->label(__('app.description'))
                ->columnSpanFull(),
        ];
    }

    public function createEpic(array $data): void
    {
        if (! $this->selectedProjectId) {
            return;
        }

        Epic::create([
            'project_id' => $this->selectedProjectId,
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        $this->loadEpics();
        $this->expandedEpics = $this->epics->pluck('id')->toArray();

        Notification::make()
            ->title(__('app.epic_created'))
            ->success()
            ->send();
    }

    public function editEpicAction(): Action
    {
        return Action::make('editEpic')
            ->label(__('app.edit'))
            ->icon('heroicon-o-pencil-square')
            ->modalHeading(__('app.edit_epic'))
            ->fillForm(function (array $arguments): array {
                $epic = Epic::find($arguments['epic'] ?? null);

                if (! $epic) {
                    return [];
                }

                return [
                    'name' => $epic->name,
                    'sort_order' => $epic->sort_order,
                    'start_date' => $epic->start_date,
                    'end_date' => $epic->end_date,
                    'description' => $epic->description,
                ];
            })
            ->schema($this->epicFormSchema())
            ->action(function (array $arguments, array $data): void {
                $epic = Epic::find($arguments['epic'] ?? null);

                if (! $epic) {
                    return;
                }

                $epic->update([
                    'name' => $data['name'],
                    'sort_order' => $data['sort_order'] ?? 0,
                    'start_date' => $data['start_date'] ?? null,
                    'end_date' => $data['end_date'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);

                $this->loadEpics();

                Notification::make()
                    ->title(__('app.epic_updated'))
                    ->success()
                    ->send();
            });
    }

    public function deleteEpicAction(): Action
    {
        return Action::make('deleteEpic')
            ->label(__('app.delete'))
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('app.delete_epic'))
            ->modalDescription(__('app.delete_epic_confirm'))
            ->action(function (array $arguments): void {
                $epic = Epic::find($arguments['epic'] ?? null);
                $epic?->delete();

                $this->loadEpics();
                $this->expandedEpics = $this->epics->pluck('id')->toArray();

                Notification::make()
                    ->title(__('app.epic_deleted'))
                    ->success()
                    ->send();
            });
    }

    protected static ?string $slug = 'epics-overview/{project_id?}';

    public Collection $epics;

    public array $expandedEpics = [];

    public ?int $selectedProjectId = null;

    public Collection $availableProjects;

    public string $searchProject = '';

    public function mount($project_id = null): void
    {
        $this->loadAvailableProjects();

        if ($project_id && $this->availableProjects->contains('id', $project_id)) {
            $this->selectedProjectId = (int) $project_id;
        } elseif ($project_id && !$this->availableProjects->contains('id', $project_id)) {
            Notification::make()
                ->title(__('app.project_not_found'))
                ->body(__('app.project_not_found_body'))
                ->danger()
                ->send();
            $this->redirect(static::getUrl());
        }

        $this->loadEpics();
        $this->expandedEpics = $this->epics->pluck('id')->toArray();
    }

    public function loadAvailableProjects(): void
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            $this->availableProjects = Project::orderByRaw('pinned_date IS NULL')
                ->orderBy('pinned_date', 'desc')
                ->orderBy('name')
                ->get();
        } else {
            $this->availableProjects = $user->projects()
                ->orderByRaw('pinned_date IS NULL')
                ->orderBy('pinned_date', 'desc')
                ->orderBy('name')
                ->get();
        }
    }

    public function getFilteredProjectsProperty(): Collection
    {
        if (empty($this->searchProject)) {
            return $this->availableProjects;
        }

        return $this->availableProjects->filter(function ($project) {
            return str_contains(strtolower($project->name), strtolower($this->searchProject)) ||
                str_contains(strtolower($project->ticket_prefix ?? ''), strtolower($this->searchProject));
        });
    }

    public function loadEpics(): void
    {
        $query = Epic::with([
            'project',
            'tickets' => function ($query) {
                $query->with(['status', 'assignees', 'creator']);
            },
        ])
            ->orderBy('start_date', 'asc');

        if ($this->selectedProjectId) {
            $query->where('project_id', $this->selectedProjectId);
        }

        $this->epics = $query->get();
    }

    public function updatedSelectedProjectId($value): void
    {
        $this->selectedProjectId = $value ? (int) $value : null;

        if ($this->selectedProjectId) {
            $url = static::getUrl(['project_id' => $this->selectedProjectId]);
            $this->js("Livewire.navigate('{$url}')");
        } else {
            $url = static::getUrl();
            $this->js("Livewire.navigate('{$url}')");
        }

        $this->loadEpics();
        $this->expandedEpics = $this->epics->pluck('id')->toArray();
    }

    public function toggleEpic(int $epicId): void
    {
        if (in_array($epicId, $this->expandedEpics)) {
            $this->expandedEpics = array_diff($this->expandedEpics, [$epicId]);
        } else {
            $this->expandedEpics[] = $epicId;
        }
    }

    public function isExpanded(int $epicId): bool
    {
        return in_array($epicId, $this->expandedEpics);
    }

    public function getEpicStats(Epic $epic): array
    {
        $tickets = $epic->tickets;
        $totalTickets = $tickets->count();

        if ($totalTickets === 0) {
            return [
                'total' => 0,
                'completed' => 0,
                'in_progress' => 0,
                'todo' => 0,
                'progress_percentage' => 0,
            ];
        }

        $completed = $tickets->filter(function ($ticket) {
            return in_array($ticket->status?->name, ['Done', 'Completed', 'Closed']);
        })->count();

        $inProgress = $tickets->filter(function ($ticket) {
            return in_array($ticket->status?->name, ['In Progress', 'Review']);
        })->count();

        $todo = $tickets->filter(function ($ticket) {
            return in_array($ticket->status?->name, ['To Do', 'Open', 'New']);
        })->count();

        return [
            'total' => $totalTickets,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'todo' => $todo,
            'progress_percentage' => $totalTickets > 0 ? round(($completed / $totalTickets) * 100) : 0,
        ];
    }

    public function getTicketAssigneesDisplay($ticket): string
    {
        if ($ticket->assignees->isEmpty()) {
            return __('app.unassigned');
        }

        $names = $ticket->assignees->pluck('name')->toArray();

        if (count($names) <= 2) {
            return implode(', ', $names);
        }

        return $names[0] . ', ' . $names[1] . ' +' . (count($names) - 2) . ' more';
    }

    #[On('epic-created')]
    #[On('epic-updated')]
    #[On('epic-deleted')]
    #[On('ticket-created')]
    #[On('ticket-updated')]
    #[On('ticket-deleted')]
    public function refreshEpics(): void
    {
        $this->loadEpics();

        $currentEpicIds = $this->epics->pluck('id')->toArray();
        $this->expandedEpics = array_intersect($this->expandedEpics, $currentEpicIds);

        Notification::make()
            ->title(__('app.data_refreshed'))
            ->success()
            ->send();
    }
}
