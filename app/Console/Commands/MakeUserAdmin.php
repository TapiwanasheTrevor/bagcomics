<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    protected $signature = 'admin:make-user {email : The email of the user to make admin}';
    protected $description = 'Make a user an admin';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User with email '{$email}' not found.");
            return 1;
        }

        if ($user->is_admin) {
            $this->info("User '{$email}' is already an admin.");
            return 0;
        }

        $user->is_admin = true;
        $user->save();

        $this->info("User '{$email}' has been made an admin.");
        return 0;
    }
}