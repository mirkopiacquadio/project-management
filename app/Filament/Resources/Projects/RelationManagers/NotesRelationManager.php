<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\ProjectNote;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\StaticAction;
use Illuminate\Database\Eloquent\Model;

class NotesRelationManager extends RelationManager
{
    protected static string $relationship = 'notes';

    protected static ?string $title = null;

    protected static ?string $modelLabel = null;

    protected static ?string $pluralModelLabel = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.notes');
    }

    public static function getModelLabel(): string
    {
        return __('app.notes');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->label(__('app.title'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                
                DatePicker::make('note_date')
                    ->label(__('app.note_date'))
                    ->default(now())
                    ->required(),
                
                RichEditor::make('content')->label(__('app.content'))
                    ->required()
                    ->columnSpanFull()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('attachments')
                    ->fileAttachmentsVisibility('public')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'underline',
                        'undo',
                    ])
                    ->helperText(__('app.note_help')),
                
                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label(__('app.title'))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium),
                
                TextColumn::make('note_date')->label(__('app.note_date'))
                    ->date('M d, Y')
                    ->sortable(),
                
                TextColumn::make('creator.name')
                    ->label(__('app.created_by'))
                    ->sortable(),
                
                TextColumn::make('created_at')->label(__('app.created_at'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('recent')
                    ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
                    ->label(__('app.recent_30_days')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-o-plus')
                    ->label(__('app.add_note'))
                    ->modalWidth('2xl')
                    ->closeModalByClickingAway(false)
                    ,
            ])
            ->recordActions([
                ViewAction::make()
                    ->closeModalByClickingAway(false),
                EditAction::make()
                    ->closeModalByClickingAway(false),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('note_date', 'desc')
            ->emptyStateHeading(__('app.no_notes_heading'))
            ->emptyStateDescription(__('app.no_notes_desc'))
            ->emptyStateIcon('heroicon-o-document-text');
    }
}