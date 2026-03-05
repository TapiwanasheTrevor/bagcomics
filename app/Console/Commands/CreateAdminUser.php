<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:admin-user {--email=} {--name=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin user for Filament';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating Filament Admin User');
        $this->info('==========================');

        // Get user input
        $email = $this->option('email') ?: $this->ask('Email address');
        $name = $this->option('name') ?: $this->ask('Full name', 'BagComics Admin');
        $password = $this->option('password') ?: $this->secret('Password');
        $generatedPassword = null;

        if (empty($password)) {
            $generatedPassword = Str::random(24);
            $password = $generatedPassword;
            $this->warn('No password provided. A random password was generated.');
        }

        // Validate email
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            $this->error('Invalid email address');
            return 1;
        }

        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            if ($this->confirm("User with email {$email} already exists. Update password?")) {
                $user = User::where('email', $email)->first();
                $user->update([
                    'name' => $name,
                    'password' => Hash::make($password),
                ]);
                $this->info("Admin user updated successfully!");
            } else {
                $this->info("Operation cancelled.");
                return 0;
            }
        } else {
            // Create new user
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);
            $this->info("Admin user created successfully!");
        }

        $this->info('');
        $this->info('Login credentials:');
        $this->info("Email: {$email}");
        if ($generatedPassword) {
            $this->warn("Generated password (save it now): {$generatedPassword}");
        } else {
            $this->info('Password: [provided securely]');
        }
        $this->info('');
        $this->info('You can now login to the admin panel at /admin');

        return 0;
    }
}
