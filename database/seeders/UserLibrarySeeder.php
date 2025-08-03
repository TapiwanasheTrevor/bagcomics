<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserLibrarySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (or create one if none exists)
        $user = User::first();
        if (!$user) {
            $user = User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]);
        }

        // Get some comics to add to library
        $comics = Comic::where('is_visible', true)->limit(10)->get();

        foreach ($comics as $index => $comic) {
            UserLibrary::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'comic_id' => $comic->id,
                ],
                [
                    'access_type' => $comic->is_free ? 'free' : 'purchased',
                    'purchase_price' => $comic->is_free ? null : $comic->price,
                    'purchased_at' => $comic->is_free ? null : now()->subDays(rand(1, 30)),
                    'is_favorite' => $index < 3, // Make first 3 favorites
                    'rating' => rand(3, 5), // Random rating between 3-5
                    'review' => $index === 0 ? 'Amazing comic with great artwork!' : null,
                ]
            );
        }
    }
}
