<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Pages\ProjectBoard;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public ?int $editingCommentId = null;

    public function editCommentAction(): Action
    {
        return Action::make('editComment')
            ->form([
                Hidden::make('comment_id'),
                RichEditor::make('comment')
                    ->label(__('app.edit_comment'))
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('attachments')
                    ->fileAttachmentsVisibility('public')
                    ->extraInputAttributes(['style' => 'min-height: 10rem;']),
            ])
            ->fillForm(function (array $arguments): array {
                $comment = TicketComment::find($arguments['commentId']);

                if (!$comment) {
                    return [];
                }

                return [
                    'comment_id' => $comment->id,
                    'comment' => $comment->comment,
                ];
            })
            ->action(function (array $data): void {
                $comment = TicketComment::find($data['comment_id']);

                if (!$comment) {
                    Notification::make()
                        ->title(__('app.comment_not_found'))
                        ->danger()
                        ->send();

                    return;
                }

                if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
                    Notification::make()
                        ->title(__('app.no_permission_edit_comment'))
                        ->danger()
                        ->send();

                    return;
                }

                $comment->update([
                    'comment' => $data['comment'],
                ]);

                Notification::make()
                    ->title(__('app.comment_updated_successfully'))
                    ->success()
                    ->send();

                $this->dispatch('comment-updated');
            })
            ->modalHeading(__('app.edit_comment'))
            ->modalSubmitActionLabel(__('app.update'))
            ->modalWidth('2xl');
    }

    public function deleteCommentAction(): Action
    {
        return Action::make('deleteComment')
            ->requiresConfirmation()
            ->modalHeading(__('app.delete_comment'))
            ->modalDescription(__('app.delete_comment_confirm'))
            ->modalSubmitActionLabel(__('app.delete_comment'))
            ->color('danger')
            ->icon('heroicon-o-trash')
            ->action(function (array $arguments): void {
                $comment = TicketComment::find($arguments['commentId']);

                if (!$comment) {
                    Notification::make()
                        ->title(__('app.comment_not_found'))
                        ->danger()
                        ->send();

                    return;
                }

                if ($comment->user_id !== auth()->id() && !auth()->user()->hasRole(['super_admin'])) {
                    Notification::make()
                        ->title(__('app.no_permission_delete_comment'))
                        ->danger()
                        ->send();

                    return;
                }

                $comment->delete();

                Notification::make()
                    ->title(__('app.comment_deleted_successfully'))
                    ->success()
                    ->send();

                $this->dispatch('comment-deleted');
            });
    }

    protected function convertVideoImgsToVideoTags($html)
    {
        // Pattern to match img tags with video file extensions
        $pattern = '/<img\s+[^>]*src=["\']([^"\']*\.(mp4|webm|mov|avi|mkv))["\'][^>]*\/?>/i';

        return preg_replace_callback($pattern, function ($matches) {
            $videoUrl = $matches[1];
            return '<video controls class="max-w-full rounded-lg my-2" style="max-height: 400px;">
                    <source src="' . $videoUrl . '" type="video/' . pathinfo($videoUrl, PATHINFO_EXTENSION) . '">
                    Your browser does not support the video tag.
                </video>';
        }, $html);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(function () {
                    $ticket = $this->getRecord();

                    return auth()->user()->hasRole(['super_admin'])
                        || $ticket->created_by === auth()->id()
                        || $ticket->assignees()->where('users.id', auth()->id())->exists();
                }),

            Action::make('back')
                ->label(__('app.back_to_board'))
                ->color('gray')
                ->url(fn() => ProjectBoard::getUrl(['project_id' => $this->record->project_id])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make(__('app.ticket_information'))
                    ->icon('heroicon-o-ticket')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 3])
                            ->schema([
                                TextEntry::make('uuid')
                                    ->label(__('app.ticket_id'))
                                    ->copyable()
                                    ->icon('heroicon-o-hashtag'),

                                TextEntry::make('name')
                                    ->label(__('app.ticket_name'))
                                    ->icon('heroicon-o-document-text')
                                    ->weight('bold'),

                                TextEntry::make('project.name')
                                    ->label(__('app.project'))
                                    ->icon('heroicon-o-folder'),
                            ]),
                    ]),

                Section::make(__('app.status_assignment'))
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 2, 'lg' => 4])
                            ->schema([
                                TextEntry::make('status.name')
                                    ->label(__('app.status'))
                                    ->formatStateUsing(function ($record) {
                                        $color = e($record->status?->color ?? '#6B7280');
                                        $name = e($record->status?->name ?? 'Unknown');

                                        return new HtmlString(<<<HTML
                                        <span class="fi-badge fi-size-sm" style="color: #fff; background-color: {$color};">
                                            {$name}
                                        </span>
                                    HTML);
                                    }),

                                TextEntry::make('assignees.name')
                                    ->label(__('app.assigned_to'))
                                    ->badge()
                                    ->separator(',')
                                    ->default('Unassigned')
                                    ->color('info'),

                                TextEntry::make('creator.name')
                                    ->label(__('app.created_by'))
                                    ->default('Unknown')
                                    ->icon('heroicon-o-user'),

                                TextEntry::make('due_date')
                                    ->label(__('app.due_date'))
                                    ->date('d M Y')
                                    ->icon('heroicon-o-calendar')
                                    ->color(fn($record) => $record->due_date && $record->due_date->isPast() ? 'danger' : 'success'),
                            ]),
                    ]),

                Section::make(__('app.description'))
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextEntry::make('description')->label(__('app.description'))
                            ->hiddenLabel()
                            ->html()
                            ->prose()
                            ->getStateUsing(function (Ticket $record) {
                                return $this->convertVideoImgsToVideoTags($record->description);
                            })
                            ->columnSpanFull()
                            ->placeholder(__('app.no_description_provided')),
                    ])
                    ->columnSpanFull(),

                Section::make(__('app.comments_section'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->description(__('app.ticket_discussion'))
                    ->schema([
                        TextEntry::make('comments_list')
                            ->hiddenLabel()
                            ->state(function (Ticket $record) {
                                if (method_exists($record, 'comments')) {
                                    return $record->comments()->with('user')->oldest()->get();
                                }

                                return collect();
                            })
                            ->view('filament.resources.ticket-resource.comments-section')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make(__('app.metadata'))
                            ->icon('heroicon-o-information-circle')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('app.created_at'))
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-o-clock'),

                                TextEntry::make('updated_at')
                                    ->label(__('app.updated_at'))
                                    ->dateTime('d M Y H:i')
                                    ->icon('heroicon-o-arrow-path'),

                                TextEntry::make('epic.name')
                                    ->label(__('app.epic'))
                                    ->default(__('app.no_epic'))
                                    ->badge()
                                    ->color('warning')
                                    ->icon('heroicon-o-flag'),
                            ]),

                        Section::make(__('app.status_history'))
                            ->icon('heroicon-o-clock')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('histories')
                                    ->hiddenLabel()
                                    ->view('filament.resources.ticket-resource.timeline-history')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
