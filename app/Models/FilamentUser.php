<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser as FilamentUserContract;
use Filament\Panel;

class FilamentUser extends User implements FilamentUserContract
{
    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // Allow access for users with admin emails
        return in_array($this->email, [
            'admin@bagcomics.com',
            'admin@example.com',
        ]);
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return 'users';
    }
}