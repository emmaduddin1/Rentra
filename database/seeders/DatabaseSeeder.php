<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * SRS NFR-MNT-003 - migrations and seeders reproduce a development
 * environment. Every demo account uses the password "password".
 *
 * Accounts only: properties, tenancies, records, and applications are created
 * through the application itself, so the seeded state never diverges from what
 * the workflows actually produce.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->owner()->create([
            'name' => 'Demo Owner',
            'email' => 'owner@example.com',
            'phone' => '+923001112233',
        ]);

        User::factory()->renter()->create([
            'name' => 'Demo Renter',
            'email' => 'renter@example.com',
            'phone' => '+923004445566',
        ]);

        $this->command?->info('Seeded demo accounts: owner@example.com / renter@example.com (password: "password")');
    }
}
