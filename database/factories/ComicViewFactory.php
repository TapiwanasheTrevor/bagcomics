<?php

namespace Database\Factories;

use App\Models\Comic;
use App\Models\ComicView;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComicView>
 */
class ComicViewFactory extends Factory
{
    protected $model = ComicView::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $existingComicId = Comic::query()->value('id');

        return [
            'comic_id' => $existingComicId ?? Comic::factory(),
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Laravel Test Suite',
            'session_id' => (string) Str::uuid(),
            'viewed_at' => now()->subDay(),
        ];
    }
}
