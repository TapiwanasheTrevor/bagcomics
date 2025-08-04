<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Comic;
use App\Models\UserComicProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserComicProgress>
 */
class UserComicProgressFactory extends Factory
{
    protected $model = UserComicProgress::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pageCount = $this->faker->numberBetween(10, 100);
        $currentPage = $this->faker->numberBetween(1, $pageCount);
        $isCompleted = $this->faker->boolean(30); // 30% chance of being completed

        return [
            'user_id' => User::factory(),
            'comic_id' => Comic::factory(),
            'current_page' => $isCompleted ? $pageCount : $currentPage,
            'total_pages' => $pageCount,
            'progress_percentage' => $isCompleted ? 100 : round(($currentPage / $pageCount) * 100, 2),
            'is_completed' => $isCompleted,
            'last_read_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'reading_time_minutes' => $this->faker->numberBetween(5, 120),
        ];
    }

    /**
     * Indicate that the comic is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'current_page' => $attributes['total_pages'],
                'progress_percentage' => 100,
                'is_completed' => true,
            ];
        });
    }

    /**
     * Indicate that the comic is just started.
     */
    public function justStarted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'current_page' => 1,
                'progress_percentage' => round((1 / $attributes['total_pages']) * 100, 2),
                'is_completed' => false,
            ];
        });
    }

    /**
     * Indicate that the comic is halfway through.
     */
    public function halfway(): static
    {
        return $this->state(function (array $attributes) {
            $halfwayPage = ceil($attributes['total_pages'] / 2);
            return [
                'current_page' => $halfwayPage,
                'progress_percentage' => 50,
                'is_completed' => false,
            ];
        });
    }
}