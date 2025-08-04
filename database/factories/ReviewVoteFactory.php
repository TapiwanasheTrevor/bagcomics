<?php

namespace Database\Factories;

use App\Models\ComicReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReviewVote>
 */
class ReviewVoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'review_id' => ComicReview::factory(),
            'is_helpful' => fake()->boolean(70), // 70% chance of being helpful
        ];
    }

    /**
     * Indicate that the vote is helpful.
     */
    public function helpful(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_helpful' => true,
        ]);
    }

    /**
     * Indicate that the vote is not helpful.
     */
    public function notHelpful(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_helpful' => false,
        ]);
    }
}