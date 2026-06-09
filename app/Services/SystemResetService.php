<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Wipes the database and restores it to the project's initial state.
 *
 * Shared by the System Settings page (reset button) and the `app:reset`
 * console command so both behave identically.
 */
class SystemResetService
{
    /**
     * Drop every table and restore the initial state: fresh migrations +
     * seeders, regenerated Shield permissions, and a super_admin role that
     * holds every permission.
     *
     * @return array<int, string> Human-readable log lines describing each step.
     */
    public function restore(): array
    {
        $log = [];

        // Drop all tables, re-run migrations and seeders.
        Artisan::call('migrate:fresh', [
            '--seed' => true,
            '--force' => true,
        ]);
        $log[] = 'Database azzerato e ri-migrato con i seeder.';

        // Regenerate Shield permissions for ALL entities (resources, pages and
        // widgets). The RoleSeeder only creates resource permissions, so without
        // this the dashboard widgets have no permission and stay hidden. Policies
        // already exist as files, so generate permissions only.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--option' => 'permissions',
            '--panel' => 'admin',
            '--no-interaction' => true,
        ]);
        $log[] = 'Permessi Shield rigenerati (risorse, pagine, widget).';

        // super_admin is a real role (define_via_gate = false in
        // config/filament-shield.php), so it must hold every permission
        // explicitly. Re-sync after generation so nothing is hidden.
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());
        $log[] = 'Ruolo super_admin sincronizzato con ' . $superAdmin->permissions()->count() . ' permessi.';

        // Permissions were re-seeded; drop the cached map so role checks are fresh.
        if (class_exists(PermissionRegistrar::class)) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $log;
    }

    /**
     * Ensure a super_admin user exists with the given credentials.
     *
     * Inserts via the query builder to bypass the User model's "hashed" cast,
     * so an already-hashed password can be preserved verbatim.
     *
     * @param bool $passwordIsHashed Pass true when $password is already a hash.
     */
    public function ensureSuperAdmin(string $name, string $email, string $password, bool $passwordIsHashed = false): User
    {
        $hash = $passwordIsHashed ? $password : Hash::make($password);

        DB::table('users')->updateOrInsert(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $hash,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        $user = User::where('email', $email)->firstOrFail();
        $user->assignRole('super_admin');

        return $user;
    }
}
