<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_DEFAULT_EMAIL', 'admin@bagcomics.com');
        $adminName = env('ADMIN_DEFAULT_NAME', 'BagComics Admin');
        $adminPassword = env('ADMIN_DEFAULT_PASSWORD');

        if (empty($adminPassword)) {
            $adminPassword = Str::random(32);
            $this->command?->warn('ADMIN_DEFAULT_PASSWORD is not set. A random admin password was generated.');
        }

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );

        $this->command?->info('Admin user seeded successfully.');
        $this->command?->info("Email: {$adminEmail}");
        $this->command?->info('Use a secure password from ADMIN_DEFAULT_PASSWORD and rotate it after initial login.');
    }
}
