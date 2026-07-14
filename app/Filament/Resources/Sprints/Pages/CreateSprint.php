<?php

namespace App\Filament\Resources\Sprints\Pages;

use App\Filament\Resources\Sprints\SprintResource;
use App\Models\Sprint;
use App\Models\Ticket;
use Filament\Resources\Pages\CreateRecord;

class CreateSprint extends CreateRecord
{
    protected static string $resource = SprintResource::class;

    public function mount(): void
    {
        // Single-sprint mode: forbid reaching the create page directly while an
        // open sprint already exists.
        abort_if(Sprint::creationBlocked(), 403, __('app.sprint_creation_blocked'));

        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        // Attach the tickets picked in the create form (only those not already
        // in a sprint). Board statuses are global, so the ticket keeps its
        // current status.
        $ticketIds = $this->data['ticket_ids'] ?? [];

        if (! empty($ticketIds)) {
            Ticket::whereIn('id', $ticketIds)
                ->whereNull('sprint_id')
                ->update(['sprint_id' => $this->record->id]);
        }

        // Ensure the user who created the sprint is added as a member so they can access it
        if (auth()->check()) {
            $this->record->members()->syncWithoutDetaching(auth()->id());
        }
    }
}
