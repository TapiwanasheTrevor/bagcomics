<?php

namespace Database\Factories;

use App\Models\CmsAnalytic;
use App\Models\CmsContent;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsAnalyticFactory extends Factory
{
    protected $model = CmsAnalytic::class;

    public function definition(): array
    {
        return [
            'cms_content_id' => CmsContent::factory(),
            'event_type' => $this->faker->randomElement(['view', 'edit', 'publish', 'archive', 'version_created', 'version_published']),
            'user_agent' => $this->faker->userAgent(),
            'ip_address' => $this->faker->ipv4(),
            'referrer' => $this->faker->optional()->url(),
            'metadata' => [
                'user_id' => $this->faker->optional()->randomNumber(),
                'session_id' => $this->faker->uuid(),
                'additional_data' => $this->faker->words(3),
            ],
            'occurred_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ];
    }

    public function view(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'view',
            'metadata' => [
                'page_url' => $this->faker->url(),
                'session_duration' => $this->faker->numberBetween(10, 300),
            ],
        ]);
    }

    public function edit(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'edit',
            'metadata' => [
                'user_id' => $this->faker->randomNumber(),
                'fields_changed' => $this->faker->words(3),
                'change_summary' => $this->faker->sentence(),
            ],
        ]);
    }

    public function publish(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'publish',
            'metadata' => [
                'user_id' => $this->faker->randomNumber(),
                'previous_status' => 'draft',
            ],
        ]);
    }

    public function versionCreated(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'version_created',
            'metadata' => [
                'user_id' => $this->faker->randomNumber(),
                'version_number' => $this->faker->numberBetween(1, 10),
                'change_summary' => $this->faker->sentence(),
            ],
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'occurred_at' => $this->faker->dateTimeBetween('-2 years', '-1 year'),
        ]);
    }
}