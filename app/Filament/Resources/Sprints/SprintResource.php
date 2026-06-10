<?php

namespace App\Filament\Resources\Sprints;

use App\Filament\Resources\Sprints\Pages\CreateSprint;
use App\Filament\Resources\Sprints\Pages\EditSprint;
use App\Filament\Resources\Sprints\Pages\ListSprints;
use App\Filament\Resources\Sprints\Pages\ViewSprint;
use App\Filament\Resources\Sprints\RelationManagers\SprintStatusesRelationManager;
use App\Filament\Resources\Sprints\RelationManagers\TicketsRelationManager;
use App\Models\Sprint;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SprintResource extends Resource
{
    protected static ?string $model = Sprint::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public static function getModelLabel(): string
    {
        return __('app.sprint');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.sprints');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('app.name'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('goal')->label(__('app.sprint_goal'))
                    ->rows(3)
                    ->columnSpanFull(),
                DatePicker::make('start_date')
                    ->label(__('app.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('end_date')
                    ->label(__('app.end_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Select::make('members')
                    ->label(__('app.sprint_members'))
                    ->relationship('members', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText(__('app.sprint_members_help')),
                Toggle::make('create_default_statuses')
                    ->label(__('app.use_default_sprint_statuses'))
                    ->helperText(__('app.default_sprint_statuses_help'))
                    ->default(true)
                    ->dehydrated(false)
                    ->visible(fn ($livewire) => $livewire instanceof CreateSprint),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('app.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Sprint::statusOptions()[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        Sprint::STATUS_PLANNING => 'gray',
                        Sprint::STATUS_ACTIVE => 'success',
                        Sprint::STATUS_COMPLETED => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('progress_percentage')
                    ->label(__('app.progress'))
                    ->getStateUsing(fn (Sprint $record): string => $record->progress_percentage . '%')
                    ->badge()
                    ->color(
                        fn (Sprint $record): string => $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 50 ? 'warning' : 'gray')
                    ),
                TextColumn::make('start_date')->label(__('app.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')->label(__('app.end_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('app.members')),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('app.tickets')),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('start_sprint')
                    ->label(__('app.start_sprint'))
                    ->icon('heroicon-m-play')
                    ->color('success')
                    ->visible(fn (Sprint $record): bool => $record->isPlanning() && auth()->user()->can('update_sprint'))
                    ->requiresConfirmation()
                    ->action(function (Sprint $record) {
                        $record->update([
                            'status' => Sprint::STATUS_ACTIVE,
                            'start_date' => $record->start_date ?? now(),
                        ]);

                        Notification::make()
                            ->title(__('app.sprint_started'))
                            ->success()
                            ->send();
                    }),
                Action::make('complete_sprint')
                    ->label(__('app.complete_sprint'))
                    ->icon('heroicon-m-flag')
                    ->color('info')
                    ->visible(fn (Sprint $record): bool => $record->isActive() && auth()->user()->can('update_sprint'))
                    ->requiresConfirmation()
                    ->modalDescription(__('app.complete_sprint_confirm'))
                    ->action(function (Sprint $record) {
                        $record->update([
                            'status' => Sprint::STATUS_COMPLETED,
                            'end_date' => $record->end_date ?? now(),
                        ]);

                        Notification::make()
                            ->title(__('app.sprint_completed'))
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SprintStatusesRelationManager::class,
            TicketsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSprints::route('/'),
            'create' => CreateSprint::route('/create'),
            'view' => ViewSprint::route('/{record}'),
            'edit' => EditSprint::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()?->hasRole('super_admin')) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }
}
