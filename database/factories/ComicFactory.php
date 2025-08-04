<?php

namespace Database\Factories;

use App\Models\ComicSeries;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comic>
 */
class ComicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->words(rand(2, 5), true);
        
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraphs(rand(2, 4), true),
            'author' => fake()->name(),
            'publisher' => fake()->randomElement([
                'Marvel Comics',
                'DC Comics',
                'Image Comics',
                'Dark Horse Comics',
                'IDW Publishing',
                'Boom! Studios',
                'Valiant Entertainment',
                'Dynamite Entertainment'
            ]),
            'genre' => fake()->randomElement([
                'Superhero',
                'Action',
                'Adventure',
                'Horror',
                'Science Fiction',
                'Fantasy',
                'Mystery',
                'Romance',
                'Comedy',
                'Drama',
                'Thriller',
                'Western'
            ]),
            'tags' => fake()->randomElements([
                'action-packed',
                'character-driven',
                'dark',
                'humorous',
                'epic',
                'psychological',
                'supernatural',
                'dystopian',
                'coming-of-age',
                'noir'
            ], rand(2, 4)),
            'page_count' => fake()->numberBetween(20, 200),
            'language' => fake()->randomElement(['en', 'es', 'fr', 'de', 'ja']),
            'isbn' => fake()->isbn13(),
            'publication_year' => fake()->numberBetween(1990, 2025),
            'average_rating' => fake()->randomFloat(2, 1, 5),
            'total_ratings' => fake()->numberBetween(0, 1000),
            'total_readers' => fake()->numberBetween(0, 5000),
            'view_count' => fake()->numberBetween(0, 10000),
            'pdf_file_path' => 'comics/' . fake()->uuid() . '.pdf',
            'cover_image_path' => 'covers/' . fake()->uuid() . '.jpg',
            'preview_pages' => fake()->randomElements(range(1, 10), rand(3, 5)),
            'has_mature_content' => fake()->boolean(20), // 20% chance
            'content_warnings' => fake()->optional(0.3)->randomElements([
                'violence',
                'strong language',
                'sexual content',
                'drug use',
                'disturbing imagery'
            ], rand(1, 3)),
            'is_free' => fake()->boolean(30), // 30% chance of being free
            'price' => function (array $attributes) {
                return $attributes['is_free'] ? null : fake()->randomFloat(2, 0.99, 19.99);
            },
            'is_visible' => fake()->boolean(95), // 95% chance of being visible
            'published_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'pdf_file_name' => fake()->words(3, true) . '.pdf',
            'pdf_file_size' => fake()->numberBetween(1000000, 50000000), // 1MB to 50MB
            'pdf_mime_type' => 'application/pdf',
            'is_pdf_comic' => true,
            'pdf_metadata' => [
                'pages' => fake()->numberBetween(20, 200),
                'file_size' => fake()->numberBetween(1000000, 50000000),
                'created_date' => fake()->dateTime()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Indicate that the comic is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
            'price' => null,
        ]);
    }

    /**
     * Indicate that the comic is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => false,
            'price' => fake()->randomFloat(2, 0.99, 19.99),
        ]);
    }

    /**
     * Indicate that the comic has mature content.
     */
    public function mature(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_mature_content' => true,
            'content_warnings' => fake()->randomElements([
                'violence',
                'strong language',
                'sexual content',
                'drug use',
                'disturbing imagery'
            ], rand(2, 4)),
        ]);
    }

    /**
     * Indicate that the comic is part of a series.
     */
    public function inSeries(?ComicSeries $series = null): static
    {
        return $this->state(fn (array $attributes) => [
            'series_id' => $series?->id ?? ComicSeries::factory(),
            'issue_number' => fake()->numberBetween(1, 50),
        ]);
    }

    /**
     * Indicate that the comic is highly rated.
     */
    public function highlyRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'average_rating' => fake()->randomFloat(2, 4.0, 5.0),
            'total_ratings' => fake()->numberBetween(100, 1000),
        ]);
    }

    /**
     * Indicate that the comic is popular.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'total_readers' => fake()->numberBetween(1000, 10000),
            'view_count' => fake()->numberBetween(5000, 50000),
        ]);
    }
}