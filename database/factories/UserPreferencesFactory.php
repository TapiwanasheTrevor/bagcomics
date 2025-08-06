<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserPreferences;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPreferences>
 */
class UserPreferencesFactory extends Factory
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
            'reading_view_mode' => $this->faker->randomElement(['single', 'continuous']),
            'reading_direction' => $this->faker->randomElement(['ltr', 'rtl']),
            'reading_zoom_level' => $this->faker->randomFloat(2, 0.5, 3.0),
            'auto_hide_controls' => $this->faker->boolean(),
            'control_hide_delay' => $this->faker->numberBetween(1000, 10000),
            'theme' => $this->faker->randomElement(['light', 'dark', 'auto']),
            'reduce_motion' => $this->faker->boolean(),
            'high_contrast' => $this->faker->boolean(),
            'email_notifications' => $this->faker->boolean(),
            'new_releases_notifications' => $this->faker->boolean(),
            'reading_reminders' => $this->faker->boolean(),
        ];
    }
}