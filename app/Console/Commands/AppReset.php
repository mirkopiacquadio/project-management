<?php

namespace App\Console\Commands;

use App\Services\SystemResetService;
use Illuminate\Console\Command;

class AppReset extends Command
{
    protected $signature = 'app:reset
        {--force : Salta la conferma e usa i valori di default (non interattivo)}
        {--email= : Email del super admin da (ri)creare}
        {--name= : Nome visualizzato del super admin}
        {--password= : Password del super admin}';

    protected $description = 'Azzera tutti i dati e ripristina il database allo stato iniziale, ricreando un super admin.';

    public function handle(SystemResetService $service): int
    {
        // Hard guard: never wipe production unless explicitly forced.
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Ambiente di produzione: usa --force per procedere consapevolmente.');

            return self::FAILURE;
        }

        // Defaults, overridable via options.
        $email = $this->option('email') ?: 'admin@admin.it';
        $name = $this->option('name') ?: 'Administrator';
        $password = $this->option('password') ?: 'password';

        if (! $this->option('force')) {
            $this->warn('Questo ELIMINA TUTTE LE TABELLE: progetti, ticket, commenti e utenti.');

            if (! $this->confirm('Ripristinare il database allo stato iniziale?')) {
                $this->info('Operazione annullata.');

                return self::SUCCESS;
            }

            $email = $this->ask('Email super admin', $email);
            $name = $this->ask('Nome super admin', $name);
            $password = $this->secret('Password super admin (vuoto = "' . $password . '")') ?: $password;
        }

        $this->info('Ripristino in corso...');

        try {
            foreach ($service->restore() as $line) {
                $this->line('  • ' . $line);
            }

            $user = $service->ensureSuperAdmin($name, $email, $password);
        } catch (\Throwable $e) {
            $this->error('Ripristino non riuscito: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Fatto. Super admin pronto:');
        $this->line('  Email:    ' . $user->email);
        $this->line('  Password: ' . $password);

        return self::SUCCESS;
    }
}
