<?php

namespace App\Filament\Resources\Sprints\Pages;

use App\Filament\Resources\Sprints\SprintResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSprint extends CreateRecord
{
    protected static string $resource = SprintResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $createDefaultStatuses = $this->data['create_default_statuses'] ?? true;

        if ($createDefaultStatuses) {
            $defaultStatuses = [
                ['name' => 'Da Fare', 'color' => '#F59E0B', 'sort_order' => 0],
                ['name' => 'In Corso', 'color' => '#3B82F6', 'sort_order' => 1],
                ['name' => 'Completato', 'color' => '#10B981', 'sort_order' => 2, 'is_completed' => true],
            ];

            foreach ($defaultStatuses as $status) {
                $this->record->statuses()->create($status);
            }
        }

        // Ensure the user who created the sprint is added as a member so they can access it
        if (auth()->check()) {
            $this->record->members()->syncWithoutDetaching(auth()->id());
        }
    }
}
