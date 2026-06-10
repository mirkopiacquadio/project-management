<?php

namespace App\Filament\Resources\Sprints\RelationManagers;

use App\Models\Project;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'tickets';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.tickets');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->tickets()->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('uuid')->label(__('app.ticket_id'))
                    ->searchable(),
                TextColumn::make('name')->label(__('app.name'))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('project.name')->label(__('app.project'))
                    ->badge()
                    ->color('info'),
                TextColumn::make('sprintStatus.name')->label(__('app.sprint_status'))
                    ->badge(),
                TextColumn::make('status.name')->label(__('app.project_status'))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('assignees.name')->label(__('app.assignees'))
                    ->limit(30),
            ])
            ->headerActions([
                Action::make('add_tickets')
                    ->label(__('app.add_tickets_to_sprint'))
                    ->icon('heroicon-m-plus')
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
                        $sprint = $this->getOwnerRecord();
                        $defaultStatusId = $sprint->statuses()->orderBy('sort_order')->value('id');

                        Ticket::whereIn('id', $data['ticket_ids'])
                            ->whereNull('sprint_id')
                            ->update([
                                'sprint_id' => $sprint->id,
                                'sprint_status_id' => $defaultStatusId,
                            ]);

                        Notification::make()
                            ->title(__('app.tickets_added_to_sprint'))
                            ->success()
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('remove_from_sprint')
                    ->label(__('app.remove_from_sprint'))
                    ->icon('heroicon-m-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Ticket $record) {
                        $record->update([
                            'sprint_id' => null,
                            'sprint_status_id' => null,
                        ]);

                        Notification::make()
                            ->title(__('app.ticket_removed_from_sprint'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
