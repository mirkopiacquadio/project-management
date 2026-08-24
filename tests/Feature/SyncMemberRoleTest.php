<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// DatabaseTransactions: ogni test gira in una transazione e viene annullato.
// NON distrugge i dati esistenti (a differenza di RefreshDatabase) — i test girano
// sullo stesso database MySQL dello sviluppo.
uses(DatabaseTransactions::class);

/** Ruolo usa e getta, cosi' i test non toccano il "member" reale. */
function tempRole(array $permissions = []): Role
{
    $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);

    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    $role->syncPermissions($permissions);

    return $role;
}

it('assegna i permessi mancanti al ruolo', function () {
    $role = tempRole(['view_any_project']);

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--force' => true])
        ->assertSuccessful();

    $names = $role->fresh()->permissions->pluck('name');

    expect($names)->toContain('create_ticket')          // puo' aprire ticket
        ->toContain('view_any_ticket::comment')          // vede i commenti
        ->toContain('page_ProjectBoard')                 // vede la bacheca
        ->toContain('widget_StatsOverview');             // vede la dashboard
});

it('non concede mai la pagina di reset del sistema', function () {
    $role = tempRole();

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--force' => true])
        ->assertSuccessful();

    expect($role->fresh()->permissions->pluck('name'))->not->toContain('page_SystemSettings');
});

it('rimuove i permessi orfani con underscore che nessuna policy legge', function () {
    $role = tempRole(['view_any_ticket_comment', 'view_ticket_priority']);

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--force' => true])
        ->assertSuccessful();

    expect($role->fresh()->permissions->pluck('name'))
        ->not->toContain('view_any_ticket_comment')
        ->not->toContain('view_ticket_priority');
});

it('e\' idempotente: la seconda esecuzione non cambia nulla', function () {
    $role = tempRole();

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--force' => true]);
    $first = $role->fresh()->permissions->pluck('name')->sort()->values();

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--force' => true])
        ->expectsOutputToContain('Gia\' allineato')
        ->assertSuccessful();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe($first->all());
});

it('con --dry-run non applica nulla', function () {
    $role = tempRole(['view_any_project']);

    $this->artisan('app:sync-member-role', ['--role' => $role->name, '--dry-run' => true])
        ->assertSuccessful();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['view_any_project']);
});

it('fallisce se il ruolo non esiste', function () {
    $this->artisan('app:sync-member-role', ['--role' => 'ruolo_inesistente_xyz', '--force' => true])
        ->assertFailed();
});
