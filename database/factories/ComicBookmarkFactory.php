<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComicBookmark>
 */
class ComicBookmarkFactory extends Factory
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
            'page_number' => fake()->numberBetween(1, 100),
            'note' => fake()->optional(0.6)->sentence(rand(5, 15)), // 60% chance of having a note
        ];
    }

    /**
     * Indicate that the bookmark has a note.
     */
    public function withNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => fake()->sentence(rand(5, 15)),
        ]);
    }

    /**
     * Indicate that the bookmark has no note.
     */
    public function withoutNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => null,
        ]);
    }
}