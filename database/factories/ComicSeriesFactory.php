<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComicSeries>
 */
class ComicSeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seriesNames = [
            'The Amazing Adventures',
            'Dark Chronicles',
            'Mystic Tales',
            'Hero Squad',
            'Shadow Warriors',
            'Cosmic Legends',
            'Steel Guardians',
            'Fire Storm',
            'Ice Phoenix',
            'Thunder Strike',
            'Quantum Heroes',
            'Stellar Knights',
            'Void Runners',
            'Crystal Defenders',
            'Storm Riders'
        ];
        
        $publishers = [
            'Marvel Comics',
            'DC Comics',
            'Image Comics',
            'Dark Horse Comics',
            'IDW Publishing',
            'Boom! Studios',
            'Valiant Entertainment',
            'Dynamite Entertainment'
        ];
        
        $statuses = ['ongoing', 'completed', 'hiatus', 'cancelled'];
        
        $name = $seriesNames[array_rand($seriesNames)] . ' ' . ucfirst($this->faker->word);
        
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => $this->faker->paragraph(4),
            'publisher' => $publishers[array_rand($publishers)],
            'total_issues' => rand(1, 50),
            'status' => $statuses[array_rand($statuses)],
        ];
    }

    /**
     * Indicate that the series is ongoing.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'ongoing',
        ]);
    }

    /**
     * Indicate that the series is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the series is on hiatus.
     */
    public function hiatus(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'hiatus',
        ]);
    }

    /**
     * Indicate that the series is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }
}