<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\RelationManagers\ProjectsRelationManager;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('app.users');
    }

    public static function getModelLabel(): string
    {
        return __('app.user');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.users');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('app.name'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')->label(__('app.email'))
                    ->email()
                    ->required()
                    ->unique(
                        ignoreRecord: true
                    )
                    ->maxLength(255),
                DateTimePicker::make('email_verified_at')->label(__('app.email_verified_at')),
                TextInput::make('password')->label(__('app.password'))
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => ! empty($state) ? Hash::make($state) : null
                    )
                    ->dehydrated(fn ($state) => ! empty($state))
                    ->required(fn (string $operation): bool => in_array($operation, ['create', 'attach.createOption']))
                    ->maxLength(255),
                Select::make('roles')->label(__('app.roles'))
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')->label(__('app.email'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label(__('app.roles'))
                    ->badge()
                    ->separator(',')
                    ->tooltip(fn (User $record): string => $record->roles->pluck('name')->join(', ') ?: 'No Roles')
                    ->sortable(),

                TextColumn::make('projects_count')
                    ->label(__('app.projects'))
                    ->counts('projects')
                    ->tooltip(fn (User $record): string => $record->projects->pluck('name')->join(', ') ?: 'No Projects')
                    ->sortable(),

                TextColumn::make('assigned_tickets_count')
                    ->label(__('app.total_assigned_tickets'))
                    ->counts('assignedTickets')
                    ->tooltip(__('app.tickets_assigned_tooltip'))
                    ->sortable(),

                TextColumn::make('created_tickets_count')
                    ->label(__('app.tickets_created'))
                    ->getStateUsing(function (User $record): int {
                        return $record->createdTickets()->count();
                    })
                    ->tooltip(__('app.tickets_created_tooltip'))
                    ->sortable(),

                TextColumn::make('email_verified_at')->label(__('app.email_verified_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')->label(__('app.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('has_projects')
                    ->label(__('app.has_projects'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('projects')),

                Filter::make('has_assigned_tickets')
                    ->label(__('app.has_assigned_tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('assignedTickets')),

                Filter::make('has_created_tickets')
                    ->label(__('app.has_created_tickets'))
                    ->query(fn (Builder $query): Builder => $query->whereHas('createdTickets')),

                // Filter by role
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),

                Filter::make('email_unverified')
                    ->label(__('app.email_unverified'))
                    ->query(fn (Builder $query): Builder => $query->whereNull('email_verified_at')),
            ])
            ->recordActions([
                EditAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    // NEW: Bulk action to assign role
                    BulkAction::make('assignRole')
                        ->label(__('app.assign_role'))
                        ->icon('heroicon-o-shield-check')
                        ->form([
                            Select::make('roles')
                                ->label(__('app.roles'))
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->required(),

                            Radio::make('role_mode')
                                ->label(__('app.assignment_mode'))
                                ->options([
                                    'replace' => 'Replace existing roles',
                                    'add' => 'Add to existing roles',
                                ])
                                ->default('add')
                                ->required(),
                        ])
                        ->action(function (array $data, $records) {
                            foreach ($records as $record) {
                                if ($data['role_mode'] === 'replace') {
                                    $record->roles()->sync($data['roles']);
                                } else {
                                    $record->roles()->syncWithoutDetaching($data['roles']);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            ProjectsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
