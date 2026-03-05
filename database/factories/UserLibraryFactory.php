<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserLibrary>
 */
class UserLibraryFactory extends Factory
{
    protected $model = UserLibrary::class;

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
            'access_type' => 'purchased',
            'purchase_price' => $this->faker->randomFloat(2, 0.99, 19.99),
            'purchased_at' => now(),
            'is_favorite' => false,
            'rating' => null,
            'review' => null,
        ];
    }

    /**
     * Indicate that the comic is a favorite.
     */
    public function favorite(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_favorite' => true,
        ]);
    }

    /**
     * Indicate that the comic has a high rating.
     */
    public function highRating(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $this->faker->numberBetween(4, 5),
        ]);
    }

    /**
     * Indicate that the comic was purchased.
     */
    public function purchased(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'purchased',
            'purchase_price' => $this->faker->randomFloat(2, 0.99, 19.99),
            'purchased_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the comic is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'access_type' => 'free',
            'purchase_price' => 0.00,
            'purchased_at' => now(),
        ]);
    }
}
