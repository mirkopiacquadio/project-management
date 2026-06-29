<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Forma JSON esposta del progetto. Solo campi realmente presenti su projects.
 * (La tabella non ha "status" né un cliente/owner: i partecipanti sono i "members".)
 */
class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'ticket_prefix' => $this->ticket_prefix,
            'color' => $this->color,
            'start_date' => optional($this->start_date)->toDateString(),
            'end_date' => optional($this->end_date)->toDateString(),
            'is_pinned' => $this->is_pinned,
            'members' => $this->whenLoaded('members', fn () => $this->members
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
                ->values()),
        ];
    }
}
