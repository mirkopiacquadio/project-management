<?php

namespace App\Filament\Resources\Sprints\Pages;

use App\Filament\Pages\SprintBoard;
use App\Filament\Resources\Sprints\SprintResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSprint extends ViewRecord
{
    protected static string $resource = SprintResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('open_board')
                ->label(__('app.sprint_board'))
                ->icon('heroicon-m-view-columns')
                ->url(fn () => SprintBoard::getUrl(['sprint_id' => $this->record->id])),
            EditAction::make(),
        ];
    }
}
