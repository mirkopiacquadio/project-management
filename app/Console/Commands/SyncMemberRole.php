<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SyncMemberRole extends Command
{
    protected $signature = 'app:sync-member-role
        {--role=member : Ruolo da allineare}
        {--dry-run : Mostra le differenze senza applicarle}
        {--force : Applica senza chiedere conferma}';

    protected $description = 'Allinea i permessi di un ruolo alla dotazione standard del membro di team.';

    /**
     * Dotazione standard di un membro di team.
     *
     * I nomi con "::" sono quelli che le policy controllano davvero: le varianti con
     * underscore (view_any_ticket_comment, ...) sono residui di una vecchia versione di
     * Shield e non vengono lette da nessuno, quindi questa sync le rimuove dal ruolo.
     */
    private const PERMISSIONS = [
        // Progetti: sola lettura, li crea e li modifica solo chi amministra.
        'view_any_project',
        'view_project',

        // Ticket: puo' aprirli e lavorarli, mai cancellarli.
        'view_any_ticket',
        'view_ticket',
        'create_ticket',
        'update_ticket',

        // Commenti: deve poter partecipare alla discussione.
        'view_any_ticket::comment',
        'view_ticket::comment',
        'create_ticket::comment',
        'update_ticket::comment',

        // Priorita': sola lettura, sono impostazioni globali.
        'view_any_ticket::priority',
        'view_ticket::priority',

        // Sprint: sola lettura, la pianificazione resta a chi amministra.
        'view_any_sprint',
        'view_sprint',

        'view_any_notification',
        'view_notification',

        // Pagine: tutte tranne SystemSettings, che contiene il reset totale del sistema.
        'page_ProjectBoard',
        'page_SprintBoard',
        'page_EpicsOverview',
        'page_ProjectTimeline',
        'page_TicketTimeline',
        'page_Leaderboard',
        'page_UserContributions',

        // Widget: sono tutti di sola lettura.
        'widget_StatsOverview',
        'widget_RecentActivityTable',
        'widget_ProjectTimeline',
        'widget_MonthlyTicketTrendChart',
        'widget_TicketsPerProjectChart',
        'widget_UserStatisticsChart',
    ];

    public function handle(): int
    {
        $roleName = (string) $this->option('role');

        $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

        if (! $role) {
            $this->error("Ruolo \"{$roleName}\" inesistente (guard web).");

            return self::FAILURE;
        }

        $wanted = collect(self::PERMISSIONS);
        $available = Permission::whereIn('name', $wanted)->where('guard_name', 'web')->pluck('name');
        $missing = $wanted->diff($available);

        if ($missing->isNotEmpty()) {
            $this->error('Permessi inesistenti nel database: '.$missing->implode(', '));
            $this->line('Rigenerali con: php artisan shield:generate --all --option=policies');

            return self::FAILURE;
        }

        $current = $role->permissions->pluck('name');
        $toAdd = $available->diff($current)->sort()->values();
        $toRemove = $current->diff($available)->sort()->values();

        $this->info("Ruolo \"{$roleName}\": {$current->count()} permessi attuali, {$available->count()} previsti.");

        if ($toAdd->isEmpty() && $toRemove->isEmpty()) {
            $this->info('Gia\' allineato, nulla da fare.');

            return self::SUCCESS;
        }

        $toAdd->each(fn (string $name) => $this->line("  <fg=green>+</> {$name}"));
        $toRemove->each(fn (string $name) => $this->line("  <fg=red>-</> {$name}"));

        if ($this->option('dry-run')) {
            $this->comment('Dry run: nessuna modifica applicata.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Applicare le modifiche al ruolo \"{$roleName}\"?")) {
            $this->info('Operazione annullata.');

            return self::SUCCESS;
        }

        $role->syncPermissions($available->all());

        // Spatie tiene i permessi in cache: senza questo le modifiche si vedono
        // solo dopo la scadenza della cache o un riavvio.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Fatto: il ruolo \"{$roleName}\" ha ora {$available->count()} permessi.");

        return self::SUCCESS;
    }
}
