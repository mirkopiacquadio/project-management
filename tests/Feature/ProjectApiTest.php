<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\DatabaseTransactions;

// DatabaseTransactions: ogni test gira in una transazione e viene annullato.
// NON distrugge i dati esistenti (a differenza di RefreshDatabase) — i test girano
// sullo stesso database MySQL dello sviluppo.
uses(DatabaseTransactions::class);

beforeEach(function () {
    config(['services.gestionale.token' => 'test-token-123']);
});

it('rifiuta senza token (401)', function () {
    $this->getJson('/api/projects')->assertStatus(401);
});

it('rifiuta con token errato (401)', function () {
    $this->withHeader('Authorization', 'Bearer sbagliato')
        ->getJson('/api/projects')
        ->assertStatus(401);
});

it('elenca i progetti con token valido (200 + struttura data)', function () {
    Project::create([
        'name' => 'Progetto API Test',
        'ticket_prefix' => 'PAT',
    ]);

    $this->withHeader('Authorization', 'Bearer test-token-123')
        ->getJson('/api/projects')
        ->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['id', 'name', 'description', 'start_date', 'end_date', 'is_pinned']],
        ])
        ->assertJsonFragment(['name' => 'Progetto API Test']);
});

it('mostra un singolo progetto con token valido (200)', function () {
    $project = Project::create([
        'name' => 'Progetto Singolo',
        'ticket_prefix' => 'PS',
    ]);

    $this->withHeader('Authorization', 'Bearer test-token-123')
        ->getJson("/api/projects/{$project->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $project->id)
        ->assertJsonPath('data.name', 'Progetto Singolo');
});
