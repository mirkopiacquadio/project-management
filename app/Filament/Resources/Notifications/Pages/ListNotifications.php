<?php

namespace App\Filament\Resources\Notifications\Pages;

use Filament\Schemas\Components\Tabs\Tab;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Models\Notification;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNotifications extends ListRecords
{
    protected static string $resource = NotificationResource::class;

    public function getTabs(): array
    {
        $tabs = [];
        
        if (auth()->user()->hasRole('super_admin')) {
            $tabs['all_notifications'] = Tab::make(__('app.all_notifications'))
                ->modifyQueryUsing(fn (Builder $query) => $query->with(['user', 'ticket.project']))
                ->badge(Notification::count())
                ->badgeColor('primary');
        }
        
        $tabs['all'] = Tab::make(auth()->user()->hasRole('super_admin') ? __('app.my_notifications') : __('app.notifications'))
            ->modifyQueryUsing(fn (Builder $query) => $query->where('user_id', auth()->id()))
            ->badge(Notification::where('user_id', auth()->id())->count());
            
        $tabs['unread'] = Tab::make(__('app.unread'))
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('user_id', auth()->id())->unread()
            )
            ->badge(Notification::where('user_id', auth()->id())->unread()->count())
            ->badgeColor('danger');
            
        $tabs['read'] = Tab::make(__('app.read'))
            ->modifyQueryUsing(fn (Builder $query) => 
                $query->where('user_id', auth()->id())->read()
            )
            ->badge(Notification::where('user_id', auth()->id())->read()->count())
            ->badgeColor('success');
            
        return $tabs;
    }
}
