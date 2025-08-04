<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComicReview>
 */
class ComicReviewFactory extends Factory
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
            'comic_id' => Comic::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(rand(3, 8)),
            'content' => fake()->paragraphs(rand(2, 5), true),
            'is_spoiler' => fake()->boolean(20), // 20% chance of spoiler
            'helpful_votes' => fake()->numberBetween(0, 50),
            'total_votes' => function (array $attributes) {
                return $attributes['helpful_votes'] + fake()->numberBetween(0, 20);
            },
            'is_approved' => fake()->boolean(90), // 90% chance of being approved
        ];
    }

    /**
     * Indicate that the review is a spoiler.
     */
    public function spoiler(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_spoiler' => true,
        ]);
    }

    /**
     * Indicate that the review is not approved.
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }

    /**
     * Indicate that the review has a high rating.
     */
    public function highRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => fake()->numberBetween(4, 5),
        ]);
    }

    /**
     * Indicate that the review has a low rating.
     */
    public function lowRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => fake()->numberBetween(1, 2),
        ]);
    }
}