<?php

namespace Database\Factories;

use App\Models\CmsContentVersion;
use App\Models\CmsContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsContentVersionFactory extends Factory
{
    protected $model = CmsContentVersion::class;

    public function definition(): array
    {
        return [
            'cms_content_id' => CmsContent::factory(),
            'version_number' => $this->faker->numberBetween(1, 10),
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraphs(2, true),
            'metadata' => [
                'description' => $this->faker->sentence(),
                'keywords' => $this->faker->words(5),
            ],
            'image_path' => $this->faker->optional()->filePath(),
            'is_active' => $this->faker->boolean(20), // Most versions are not active
            'status' => $this->faker->randomElement(['draft', 'published', 'scheduled', 'archived']),
            'published_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'scheduled_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'created_by' => User::factory(),
            'change_summary' => $this->faker->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
            'is_active' => false,
            'published_at' => null,
            'scheduled_at' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'is_active' => true,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'scheduled_at' => null,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'scheduled',
            'is_active' => false,
            'published_at' => null,
            'scheduled_at' => $this->faker->dateTimeBetween('now', '+1 month'),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'archived',
            'is_active' => false,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'status' => 'published',
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function withChangeSummary(string $summary): static
    {
        return $this->state(fn (array $attributes) => [
            'change_summary' => $summary,
        ]);
    }
}