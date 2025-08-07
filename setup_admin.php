<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->boot();

use Illuminate\Support\Facades\Schema;
use App\Models\User;

echo "Setting up admin user...\n";

// Add is_admin column if it doesn't exist
if (!Schema::hasColumn('users', 'is_admin')) {
    Schema::table('users', function ($table) {
        $table->boolean('is_admin')->default(false);
    });
    echo "Added is_admin column\n";
} else {
    echo "is_admin column already exists\n";
}

// Create or update admin user
$user = User::firstOrCreate(
    ['email' => 'admin@bagcomics.com'],
    ['name' => 'Admin User', 'password' => bcrypt('password')]
);
$user->is_admin = true;
$user->save();

echo "Admin user created/updated: {$user->email}\n";
echo "Is admin: " . ($user->is_admin ? 'YES' : 'NO') . "\n";
echo "Can access panel: " . (method_exists($user, 'canAccessPanel') ? 'YES' : 'NO') . "\n";

// Also create a sample comic
use App\Models\Comic;

echo "\nSetting up sample comic...\n";

// Clear existing comics
Comic::query()->delete();

$comic = new Comic();
$comic->title = 'Ubuntu Tales: Community Stories';
$comic->slug = 'ubuntu-tales-community';
$comic->author = 'Community Contributors';
$comic->genre = 'sci-fi';
$comic->description = 'A sample comic for testing.';
$comic->page_count = 20;
$comic->language = 'en';
$comic->pdf_file_path = 'sample-comic.pdf';
$comic->pdf_file_name = 'sample-comic.pdf';
$comic->is_pdf_comic = true;
$comic->is_free = true;
$comic->is_visible = true;
$comic->published_at = now();
$comic->tags = ['ubuntu', 'community'];
$comic->average_rating = 4.5;
$comic->save();

echo "Sample comic created: {$comic->title}\n";
echo "PDF path: {$comic->pdf_file_path}\n";
echo "PDF URL: " . $comic->getPdfUrl() . "\n";

echo "\nSetup complete!\n";
echo "You can now:\n";
echo "1. Login to admin at http://localhost:8000/admin with admin@bagcomics.com / password\n";
echo "2. View the comic at http://localhost:8000/comics/ubuntu-tales-community\n";