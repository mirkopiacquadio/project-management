<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Pages\ViewProject;
use App\Filament\Resources\Projects\RelationManagers\EpicsRelationManager;
use App\Filament\Resources\Projects\RelationManagers\MembersRelationManager;
use App\Filament\Resources\Projects\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Projects\RelationManagers\TicketsRelationManager;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Project Management';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('app.project_management');
    }

    public static function getModelLabel(): string
    {
        return __('app.project');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.projects');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('app.name'))
                    ->required()
                    ->maxLength(255),
                RichEditor::make('description')->label(__('app.description'))
                    ->columnSpanFull()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('attachments')
                    ->fileAttachmentsAcceptedFileTypes(['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'video/mp4'])
                    ->fileAttachmentsVisibility('public'),
                TextInput::make('ticket_prefix')->label(__('app.ticket_prefix_label'))
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')
                    ->label(__('app.project_color'))
                    ->helperText(__('app.project_color_help'))
                    ->nullable(),
                DatePicker::make('start_date')
                    ->label(__('app.start_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y'),
                DatePicker::make('end_date')
                    ->label(__('app.due_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->afterOrEqual('start_date'),
                Toggle::make('is_pinned')
                    ->label(__('app.pin_project'))
                    ->helperText(__('app.pin_project_help'))
                    ->live()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('pinned_date', now());
                        } else {
                            $set('pinned_date', null);
                        }
                    })
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($component, $state, $get) {
                        $component->state(! is_null($get('pinned_date')));
                    }),
                DateTimePicker::make('pinned_date')
                    ->label(__('app.pinned_date'))
                    ->native(false)
                    ->displayFormat('d/m/Y H:i')
                    ->visible(fn ($get) => $get('is_pinned'))
                    ->dehydrated(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->width('40px')
                    ->default('#6B7280'),
                TextColumn::make('name')->label(__('app.name'))
                    ->searchable(),
                TextColumn::make('ticket_prefix')->label(__('app.ticket_prefix_label'))
                    ->searchable(),
                TextColumn::make('progress_percentage')
                    ->label(__('app.progress'))
                    ->getStateUsing(function (Project $record): string {
                        return $record->progress_percentage.'%';
                    })
                    ->badge()
                    ->color(
                        fn (Project $record): string => $record->progress_percentage >= 100 ? 'success' :
                        ($record->progress_percentage >= 75 ? 'info' :
                            ($record->progress_percentage >= 50 ? 'warning' :
                                ($record->progress_percentage >= 25 ? 'gray' : 'danger')))
                    )
                    ->sortable(),
                TextColumn::make('start_date')->label(__('app.start_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')->label(__('app.end_date'))
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('remaining_days')
                    ->label(__('app.remaining_days'))
                    ->getStateUsing(function (Project $record): ?string {
                        if (! $record->end_date) {
                            return null;
                        }

                        return $record->remaining_days.' days';
                    })
                    ->badge()
                    ->color(
                        fn (Project $record): string => ! $record->end_date ? 'gray' :
                        ($record->remaining_days <= 0 ? 'danger' :
                            ($record->remaining_days <= 7 ? 'warning' : 'success'))
                    ),
                ToggleColumn::make('is_pinned')
                    ->label(__('app.pinned'))
                    ->updateStateUsing(function ($record, $state) {
                        // Gunakan method pin/unpin yang sudah ada di model
                        if ($state) {
                            $record->pin();
                        } else {
                            $record->unpin();
                        }

                        return $state;
                    }),
                TextColumn::make('members_count')
                    ->counts('members')
                    ->label(__('app.members')),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('app.tickets')),
                TextColumn::make('created_at')->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
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
            MembersRelationManager::class,
            EpicsRelationManager::class,
            TicketsRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'view' => ViewProject::route('/{record}'),
            'edit' => EditProject::route('/{record}/edit'),
            // Hapus baris ini: 'gantt-chart' => Pages\ProjectGanttChart::route('/gantt-chart'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $userIsSuperAdmin = auth()->user() && (
            (method_exists(auth()->user(), 'hasRole') && auth()->user()->hasRole('super_admin'))
            || (isset(auth()->user()->role) && auth()->user()->role === 'super_admin')
        );

        if (! $userIsSuperAdmin) {
            $query->whereHas('members', function (Builder $query) {
                $query->where('user_id', auth()->id());
            });
        }

        return $query;
    }
}
