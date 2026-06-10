<?php

namespace App\Filament\Resources\Sprints\RelationManagers;

use App\Models\SprintStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SprintStatusesRelationManager extends RelationManager
{
    protected static string $relationship = 'statuses';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.sprint_statuses');
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return $ownerRecord->statuses()->count();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('app.name'))
                    ->required()
                    ->maxLength(255),
                ColorPicker::make('color')->label(__('app.color'))
                    ->required()
                    ->default('#3490dc')
                    ->helperText(__('app.status_color_help')),
                TextInput::make('sort_order')->label(__('app.sort_order'))
                    ->numeric()
                    ->default(0)
                    ->helperText(__('app.status_order_help')),
                Toggle::make('is_completed')
                    ->label(__('app.mark_completed_status'))
                    ->helperText(__('app.one_completed_help'))
                    ->default(false)
                    ->reactive()
                    ->afterStateUpdated(function ($state, $get, $set, $record) {
                        if ($state) {
                            $sprintId = $this->getOwnerRecord()->id;
                            $existingCompleted = SprintStatus::where('sprint_id', $sprintId)
                                ->where('is_completed', true)
                                ->when($record, fn ($query) => $query->where('id', '!=', $record->id))
                                ->first();

                            if ($existingCompleted) {
                                $set('is_completed', false);
                                Notification::make()
                                    ->warning()
                                    ->title(__('app.cannot_mark_completed'))
                                    ->body(__('app.sprint_status_already_completed', ['name' => $existingCompleted->name]))
                                    ->send();
                            }
                        }
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('app.name')),
                ColorColumn::make('color')->label(__('app.color')),
                TextColumn::make('sort_order')->label(__('app.sort_order')),
                IconColumn::make('is_completed')
                    ->label(__('app.completed'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $maxOrder = $this->getRelationship()->max('sort_order') ?? -1;
                        $data['sort_order'] = $maxOrder + 1;

                        if ($data['is_completed'] ?? false) {
                            $sprintId = $this->getOwnerRecord()->id;
                            $existingCompleted = SprintStatus::where('sprint_id', $sprintId)
                                ->where('is_completed', true)
                                ->first();

                            if ($existingCompleted) {
                                $data['is_completed'] = false;
                                Notification::make()
                                    ->warning()
                                    ->title(__('app.cannot_mark_completed'))
                                    ->body(__('app.sprint_status_already_completed', ['name' => $existingCompleted->name]))
                                    ->send();
                            }
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateDataUsing(function (array $data, Model $record): array {
                        if ($data['is_completed'] ?? false) {
                            $sprintId = $this->getOwnerRecord()->id;
                            $existingCompleted = SprintStatus::where('sprint_id', $sprintId)
                                ->where('is_completed', true)
                                ->where('id', '!=', $record->id)
                                ->first();

                            if ($existingCompleted) {
                                $data['is_completed'] = false;
                                Notification::make()
                                    ->warning()
                                    ->title(__('app.cannot_mark_completed'))
                                    ->body(__('app.sprint_status_already_completed', ['name' => $existingCompleted->name]))
                                    ->send();
                            }
                        }

                        return $data;
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
