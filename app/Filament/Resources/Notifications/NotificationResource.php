<?php

namespace App\Filament\Resources\Notifications;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use App\Services\NotificationService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Database\Eloquent\Builder;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bell';
    
    protected static ?string $navigationLabel = null;
    
    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('app.notifications');
    }

    public static function getModelLabel(): string
    {
        return __('app.notification');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.notifications');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn () => 
                auth()->user()->hasRole('super_admin') 
                    ? Notification::with(['user', 'ticket.project'])
                    : Notification::where('user_id', auth()->id())->with(['ticket.project'])
            )
            ->columns([
                IconColumn::make('read_status')
                    ->label('')
                    ->icon(fn (Notification $record) => $record->isUnread() ? 'heroicon-o-bell' : 'heroicon-o-bell-slash')
                    ->color(fn (Notification $record) => $record->isUnread() ? 'warning' : 'gray')
                    ->size('sm'),
                    
                TextColumn::make('user.name')
                    ->label(__('app.user'))
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                    
                TextColumn::make('message')->label(__('app.message_field'))
                    ->limit(50)
                    ->weight(fn (Notification $record) => $record->isUnread() ? 'bold' : 'normal'),

                TextColumn::make('ticket.name')
                    ->label(__('app.ticket'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->placeholder(__('app.na')),
                    
                TextColumn::make('ticket.project.name')
                    ->label(__('app.project'))
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->placeholder(__('app.na')),
                    
                TextColumn::make('created_at')->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('markAsRead')
                    ->label(__('app.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Notification $record) => $record->isUnread() && (auth()->id() === $record->user_id || auth()->user()->hasRole('super_admin')))
                    ->action(function (Notification $record) {
                        app(NotificationService::class)->markAsRead($record->id, $record->user_id);
                        
                        FilamentNotification::make()
                            ->title(__('app.notification_marked_read'))
                            ->success()
                            ->send();
                    }),
                    
                Action::make('viewTicket')
                    ->label(__('app.view_ticket'))
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->visible(fn (Notification $record) => isset($record->data['ticket_id']))
                    ->url(fn (Notification $record) => 
                        route('filament.admin.resources.tickets.view', ['record' => $record->data['ticket_id']])
                    )
                    ->openUrlInNewTab(),
            ])
            ->headerActions([
                Action::make('markAllAsRead')
                    ->label(__('app.mark_all_read'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn () => !auth()->user()->hasRole('super_admin'))
                    ->action(function () {
                        app(NotificationService::class)->markAllAsRead(auth()->id());
                        
                        FilamentNotification::make()
                            ->title(__('app.all_notifications_read'))
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                Filter::make('unread')
                    ->label(__('app.unread_only'))
                    ->query(fn (Builder $query) => $query->unread()),
                    
                SelectFilter::make('user')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNotifications::route('/'),
        ];
    }
    
    public static function canCreate(): bool
    {
        return false;
    }
    
    public static function getNavigationBadge(): ?string
    {
        return auth()->user()?->unreadNotifications()->count() ?: null;
    }
    
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
