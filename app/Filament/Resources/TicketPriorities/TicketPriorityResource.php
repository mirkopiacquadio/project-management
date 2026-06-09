<?php

namespace App\Filament\Resources\TicketPriorities;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColorColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TicketPriorities\Pages\ListTicketPriorities;
use App\Filament\Resources\TicketPriorities\Pages\CreateTicketPriority;
use App\Filament\Resources\TicketPriorities\Pages\EditTicketPriority;
use App\Filament\Resources\TicketPriorityResource\Pages;
use App\Filament\Resources\TicketPriorityResource\RelationManagers;
use App\Models\TicketPriority;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TicketPriorityResource extends Resource
{
    protected static ?string $model = TicketPriority::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = null;

    protected static string | \UnitEnum | null $navigationGroup = 'Settings';

    public static function getNavigationLabel(): string
    {
        return __('app.ticket_priorities');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    public static function getModelLabel(): string
    {
        return __('app.ticket_priority');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.ticket_priorities');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label(__('app.name'))
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                ColorPicker::make('color')->label(__('app.color'))
                    ->required()
                    ->default('#6B7280'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('app.name'))
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')->label(__('app.color'))
                    ->sortable(),
                TextColumn::make('tickets_count')
                    ->counts('tickets')
                    ->label(__('app.tickets_count_label'))
                    ->sortable(),
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
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketPriorities::route('/'),
            'create' => CreateTicketPriority::route('/create'),
            'edit' => EditTicketPriority::route('/{record}/edit'),
        ];
    }
}
