<?php

namespace Database\Seeders;

use App\Models\Comic;
use App\Models\SocialShare;
use App\Models\User;
use App\Models\UserLibrary;
use Illuminate\Database\Seeder;

class SocialShareSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $comics = Comic::all();

        if ($users->isEmpty() || $comics->isEmpty()) {
            $this->command->warn('No users or comics found. Please run UserSeeder and ComicSeeder first.');
            return;
        }

        // Create social shares for random user-comic combinations
        $shareCount = min(50, $users->count() * 2); // Max 2 shares per user on average

        for ($i = 0; $i < $shareCount; $i++) {
            $user = $users->random();
            $comic = $comics->random();

            // Create different types of shares
            $shareTypes = ['comic_discovery', 'reading_achievement', 'recommendation', 'review_share'];
            $platforms = ['facebook', 'twitter', 'instagram', 'linkedin', 'reddit'];

            SocialShare::factory()->create([
                'user_id' => $user->id,
                'comic_id' => $comic->id,
                'platform' => fake()->randomElement($platforms),
                'share_type' => fake()->randomElement($shareTypes),
            ]);
        }

        // Create some specific platform shares
        SocialShare::factory(10)->facebook()->comicDiscovery()->create();
        SocialShare::factory(8)->twitter()->recommendation()->create();
        SocialShare::factory(6)->instagram()->readingAchievement()->create();

        $this->command->info('Created social shares for users.');
    }
}