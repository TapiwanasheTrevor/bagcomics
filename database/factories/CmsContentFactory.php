<?php

namespace Database\Factories;

use App\Models\CmsContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsContentFactory extends Factory
{
    protected $model = CmsContent::class;

    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'section' => $this->faker->randomElement(['hero', 'about', 'features', 'footer', 'navigation']),
            'type' => $this->faker->randomElement(['text', 'rich_text', 'image', 'json']),
            'title' => $this->faker->sentence(3),
            'content' => $this->faker->paragraphs(2, true),
            'metadata' => [
                'description' => $this->faker->sentence(),
                'keywords' => $this->faker->words(5),
            ],
            'image_path' => $this->faker->optional()->filePath(),
            'is_active' => $this->faker->boolean(80),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'status' => $this->faker->randomElement(['draft', 'published', 'scheduled', 'archived']),
            'published_at' => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'scheduled_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'current_version' => $this->faker->numberBetween(1, 5),
            'change_summary' => $this->faker->optional()->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
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

    public function heroSection(): static
    {
        return $this->state(fn (array $attributes) => [
            'section' => 'hero',
            'key' => 'hero_' . $this->faker->word,
        ]);
    }

    public function aboutSection(): static
    {
        return $this->state(fn (array $attributes) => [
            'section' => 'about',
            'key' => 'about_' . $this->faker->word,
        ]);
    }

    public function imageType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'content' => null,
            'image_path' => 'cms/images/' . $this->faker->uuid . '.jpg',
            'metadata' => [
                'alt' => $this->faker->sentence(3),
                'width' => $this->faker->numberBetween(400, 1200),
                'height' => $this->faker->numberBetween(300, 800),
            ],
        ]);
    }

    public function richTextType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'rich_text',
            'content' => '<h2>' . $this->faker->sentence() . '</h2><p>' . $this->faker->paragraphs(3, true) . '</p>',
        ]);
    }

    public function jsonType(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'json',
            'content' => null,
            'metadata' => [
                'items' => [
                    ['title' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                    ['title' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                    ['title' => $this->faker->sentence(), 'description' => $this->faker->paragraph()],
                ],
            ],
        ]);
    }
}