<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Project;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use App\Models\Ticket;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function fillForm(): void
    {
        $copyFromId = request()->query('copy_from');

        if ($copyFromId) {
            $ticket = Ticket::find($copyFromId);

            if ($ticket) {
                $data = $ticket->toArray();
                unset(
                    $data['id'],
                    $data['uuid'],
                    $data['created_at'],
                    $data['updated_at'],
                    $data['created_by']
                );

                $data['assignees'] = $ticket->assignees()->pluck('users.id')->toArray();
                $this->form->fill($data);

                return;
            }
        }

        parent::fillForm();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $ticket = parent::handleRecordCreation($data);

        if (!empty($data['assignees']) && !empty($data['project_id'])) {
            $project = Project::find($data['project_id']);
            
            if ($project) {
                $validAssignees = [];
                $invalidAssignees = [];
                
                foreach ($data['assignees'] as $userId) {
                    $isMember = $project->members()->where('users.id', $userId)->exists();
                    
                    if ($isMember) {
                        $validAssignees[] = $userId;
                    } else {
                        $invalidAssignees[] = $userId;
                    }
                }
                
                if (!empty($validAssignees)) {
                    $ticket->assignees()->sync($validAssignees);
                }
                
                if (!empty($invalidAssignees)) {
                    Notification::make()
                        ->warning()
                        ->title(__('app.some_assignees_removed'))
                        ->body(__('app.non_member_removed_body'))
                        ->send();
                }
                
                if (empty($validAssignees)) {
                    $currentUserIsMember = $project->members()->where('users.id', auth()->id())->exists();
                    
                    if ($currentUserIsMember) {
                        $ticket->assignees()->sync([auth()->id()]);
                        
                        Notification::make()
                            ->info()
                            ->title(__('app.auto_assigned'))
                            ->body(__('app.auto_assigned_body'))
                            ->send();
                    }
                }
            }
        } else {
            if (!empty($data['project_id'])) {
                $project = Project::find($data['project_id']);
                $currentUserIsMember = $project?->members()->where('users.id', auth()->id())->exists();
                
                if ($currentUserIsMember) {
                    $ticket->assignees()->sync([auth()->id()]);
                }
            }
        }

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        $referer = request()->header('referer');

        if ($referer && str_contains($referer, 'project-board-page')) {
            return '/admin/project-board-page';
        }

        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title(__('app.ticket_created'))
            ->body(__('app.ticket_created_body'));
    }
}