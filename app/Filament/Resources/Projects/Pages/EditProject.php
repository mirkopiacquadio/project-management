<?php

namespace App\Filament\Resources\Projects\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;


    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('external_access')
                ->label(__('app.external_dashboard'))
                ->icon('heroicon-o-globe-alt')
                ->color('info')
                ->modalHeading(__('app.external_dashboard_access'))
                ->modalDescription(__('app.external_share_desc'))
                ->modalContent(function () {
                    $record = $this->record;
                    $externalAccess = $record->externalAccess;
                
                    if (!$externalAccess) {
                        $externalAccess = $record->generateExternalAccess();
                    }
                
                    $dashboardUrl = url('/external/' . $externalAccess->access_token);
                
                    return view('filament.components.external-access-modal', [
                        'dashboardUrl' => $dashboardUrl,
                        'password' => $externalAccess->password,
                        'lastAccessed' => $externalAccess->last_accessed_at ? $externalAccess->last_accessed_at->format('d/m/Y H:i') : null,
                        'isActive' => $externalAccess->is_active,
                    ]);
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalFooterActions([
                    Action::make('regenerate_external_access')
                        ->label(__('app.regenerate_access'))
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading(__('app.regenerate_external_access'))
                        ->modalDescription(__('app.regenerate_warning'))
                        ->action(function () {
                            $record = $this->record;
                            $record->externalAccess()?->delete();
                            $newAccess = $record->generateExternalAccess();
                            
                            Log::info('Regenerated external access for project: ' . $record->name, [
                                'project_id' => $record->id,
                                'access_token' => $newAccess->access_token,
                                'password' => $newAccess->password
                            ]);
                            
                            Notification::make()
                                ->title(__('app.external_regenerated'))
                                ->success()
                                ->send();
                        })
                        ->visible(fn () => $this->record->externalAccess !== null),
                ]),
        ];
    }
}
