<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SocialShare>
 */
class SocialShareFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $platform = fake()->randomElement(['facebook', 'twitter', 'instagram', 'linkedin', 'reddit']);
        $shareType = fake()->randomElement(['comic_discovery', 'reading_achievement', 'recommendation', 'review_share']);
        
        return [
            'user_id' => User::factory(),
            'comic_id' => Comic::factory(),
            'platform' => $platform,
            'share_type' => $shareType,
            'metadata' => [
                'share_url' => fake()->url(),
                'share_text' => fake()->sentence(rand(10, 20)),
                'platform_post_id' => fake()->uuid(),
                'engagement_metrics' => [
                    'likes' => fake()->numberBetween(0, 100),
                    'shares' => fake()->numberBetween(0, 50),
                    'comments' => fake()->numberBetween(0, 25),
                ],
            ],
        ];
    }

    /**
     * Indicate that the share is for Facebook.
     */
    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'facebook',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'facebook_post_id' => fake()->numerify('##########'),
            ]),
        ]);
    }

    /**
     * Indicate that the share is for Twitter.
     */
    public function twitter(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'twitter',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'tweet_id' => fake()->numerify('##########'),
                'hashtags' => fake()->randomElements(['#comics', '#reading', '#graphicnovels', '#manga'], rand(1, 3)),
            ]),
        ]);
    }

    /**
     * Indicate that the share is for Instagram.
     */
    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'instagram',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'instagram_post_id' => fake()->uuid(),
                'image_url' => fake()->imageUrl(640, 640),
            ]),
        ]);
    }

    /**
     * Indicate that the share is a comic discovery.
     */
    public function comicDiscovery(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'comic_discovery',
        ]);
    }

    /**
     * Indicate that the share is a reading achievement.
     */
    public function readingAchievement(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'reading_achievement',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'achievement_type' => fake()->randomElement(['completed_series', 'milestone_reached', 'first_read']),
            ]),
        ]);
    }

    /**
     * Indicate that the share is a recommendation.
     */
    public function recommendation(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'recommendation',
        ]);
    }
}