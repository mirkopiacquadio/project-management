<?php

namespace App\Filament\Resources\Tickets;

use App\Models\Epic;
use App\Models\Ticket;
use App\Models\Project;
use Filament\Tables\Table;
use App\Models\TicketStatus;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use App\Models\TicketPriority;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Illuminate\Support\HtmlString;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\CreateTicket;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = null;

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('app.tickets');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public static function getModelLabel(): string
    {
        return __('app.ticket');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.tickets');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (!auth()->user()->hasRole(['super_admin'])) {
            $query->where(function ($query) {
                $query->whereHas('assignees', function ($query) {
                    $query->where('users.id', auth()->id());
                })
                    ->orWhere('created_by', auth()->id())
                    ->orWhereHas('project.members', function ($query) {
                        $query->where('users.id', auth()->id());
                    });
            });
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $projectId = request()->query('project_id') ?? request()->input('project_id');
        $statusId = request()->query('ticket_status_id') ?? request()->input('ticket_status_id');

        return $schema
            ->components([
                Select::make('project_id')
                    ->label(__('app.project'))
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }

                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->default($projectId)
                    ->disabledOn('ticket_on_board')
                    ->dehydrated()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (callable $set) {
                        $set('ticket_status_id', null);
                        $set('assignees', []);
                        $set('epic_id', null);
                    }),

                Select::make('ticket_status_id')
                    ->label(__('app.status'))
                    ->options(function ($get) {
                        $projectId = $get('project_id');
                        if (!$projectId) {
                            return [];
                        }

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->default($statusId)
                    ->required()
                    ->searchable()
                    ->preload(),

                Select::make('priority_id')
                    ->label(__('app.priority'))
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('epic_id')
                    ->label(__('app.epic'))
                    ->options(function (Get $get) {
                        $projectId = $get('project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->hidden(fn(Get $get): bool => !$get('project_id')),

                TextInput::make('name')
                    ->label(__('app.ticket_name'))
                    ->required()
                    ->maxLength(255),

                RichEditor::make('description')
                    ->label(__('app.description'))
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('attachments')
                    ->fileAttachmentsVisibility('public')
                    ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4'])
                    ->columnSpanFull(),

                // // Multi-user assignment
                Select::make('assignees')
                    ->label(__('app.assigned_to'))
                    ->multiple()
                    ->relationship(
                        name: 'assignees',
                        titleAttribute: 'name',
                        modifyQueryUsing: function (Builder $query, Get $get) {
                            $projectId = $get('project_id');
                            if (!$projectId) {
                                return $query->whereRaw('1 = 0');
                            }

                            $project = Project::find($projectId);
                            if (!$project) {
                                return $query->whereRaw('1 = 0');
                            }

                            return $query->whereHas('projects', function ($query) use ($projectId) {
                                $query->where('projects.id', $projectId);
                            });
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->helperText('Select multiple users to assign this ticket to. Only project members can be assigned.')
                    ->hidden(fn(Get $get): bool => !$get('project_id'))
                    ->live(),

                DatePicker::make('start_date')
                    ->label(__('app.start_date'))
                    ->default(now())
                    ->nullable(),

                DatePicker::make('due_date')
                    ->label(__('app.due_date'))
                    ->nullable(),
                Select::make('created_by')
                    ->label(__('app.created_by'))
                    ->relationship('creator', 'name')
                    ->disabled()
                    ->hiddenOn(['create', 'ticket_on_board']),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label(__('app.ticket_id'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('project.name')
                    ->label(__('app.project'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label(__('app.name'))
                    ->searchable()
                    ->limit(30),

                TextColumn::make('status.name')
                    ->label(__('app.status'))
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $color = e($record->status?->color ?? '#6B7280');
                        $name = e($record->status?->name ?? 'Unknown');

                        return new HtmlString(<<<HTML
                            <span class="fi-badge fi-size-sm" style="color: #fff; background-color: {$color};">
                                {$name}
                            </span>
                        HTML);
                    }),

                TextColumn::make('priority.name')
                    ->label(__('app.priority'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'High' => 'danger',
                        'Medium' => 'warning',
                        'Low' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->default('—')
                    ->placeholder(__('app.no_priority')),

                // Display multiple assignees
                TextColumn::make('assignees.name')
                    ->label(__('app.assign_to'))
                    ->badge()
                    ->separator(',')
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable(),

                TextColumn::make('creator.name')
                    ->label(__('app.created_by'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label(__('app.start_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label(__('app.due_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('epic.name')
                    ->label(__('app.epic'))
                    ->sortable()
                    ->searchable()
                    ->default('—')
                    ->placeholder(__('app.no_epic')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label(__('app.project'))
                    ->options(function () {
                        if (auth()->user()->hasRole(['super_admin'])) {
                            return Project::pluck('name', 'id')->toArray();
                        }

                        return auth()->user()->projects()->pluck('name', 'projects.id')->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('ticket_status_id')
                    ->label(__('app.status'))
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return TicketStatus::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('epic_id')
                    ->label(__('app.epic'))
                    ->options(function () {
                        $projectId = request()->input('tableFilters.project_id');

                        if (!$projectId) {
                            return [];
                        }

                        return Epic::where('project_id', $projectId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),

                SelectFilter::make('priority_id')
                    ->label(__('app.priority'))
                    ->options(TicketPriority::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload(),

                // Filter by assignees
                SelectFilter::make('assignees')
                    ->label(__('app.assignee'))
                    ->relationship('assignees', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                // Filter by creator
                SelectFilter::make('created_by')
                    ->label(__('app.created_by'))
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('due_date')
                    ->schema([
                        DatePicker::make('due_from'),
                        DatePicker::make('due_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['due_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['due_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('copy')
                    ->label(__('app.copy'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function ($record, $livewire) {
                        // Redirect ke halaman create, dengan parameter copy_from
                        return $livewire->redirect(
                            static::getUrl('create', [
                                'copy_from' => $record->id,
                            ])
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(auth()->user()->hasRole(['super_admin'])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'create' => CreateTicket::route('/create'),
            'view' => ViewTicket::route('/{record}'),
            'edit' => EditTicket::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = static::getEloquentQuery();

        return $query->count();
    }
}
