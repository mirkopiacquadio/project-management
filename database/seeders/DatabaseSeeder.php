<?php

namespace Database\Seeders;

use App\Models\TicketStatus;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Use plain create() (not the factory) so seeding does not depend on
        // fakerphp/faker, which is a dev-only dependency absent in the
        // production image (composer install --no-dev). The factory's
        // definition() calls fake(), which would fatal during app:reset.
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password', // hashed via the model cast
                'email_verified_at' => now(),
            ],
        );

        // Fixed, global board statuses shared by every project and sprint board.
        TicketStatus::ensureGlobalDefaults();

        // Jalankan RoleSeeder
        $this->call(RoleSeeder::class);
    }
}
