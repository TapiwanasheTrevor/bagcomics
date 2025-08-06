<?php

namespace Database\Factories;

use App\Models\CmsMediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CmsMediaAssetFactory extends Factory
{
    protected $model = CmsMediaAsset::class;

    public function definition(): array
    {
        $isImage = $this->faker->boolean(70);
        $extension = $isImage ? $this->faker->randomElement(['jpg', 'png', 'gif', 'webp']) : 
                                $this->faker->randomElement(['pdf', 'doc', 'mp4', 'mp3']);
        
        return [
            'filename' => $this->faker->slug() . '_' . time() . '.' . $extension,
            'original_filename' => $this->faker->word() . '.' . $extension,
            'path' => 'cms/media/' . $this->faker->slug() . '.' . $extension,
            'disk' => 'public',
            'mime_type' => $this->getMimeType($extension),
            'size' => $this->faker->numberBetween(1024, 10 * 1024 * 1024), // 1KB to 10MB
            'width' => $isImage ? $this->faker->numberBetween(200, 2000) : null,
            'height' => $isImage ? $this->faker->numberBetween(200, 2000) : null,
            'alt_text' => $isImage ? $this->faker->sentence(3) : null,
            'metadata' => $this->getMetadata($extension, $isImage),
            'is_optimized' => $isImage ? $this->faker->boolean(80) : false,
            'variants' => $isImage ? $this->getImageVariants() : null,
            'uploaded_by' => User::factory(),
        ];
    }

    public function image(): static
    {
        $extension = $this->faker->randomElement(['jpg', 'png', 'gif', 'webp']);
        
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->slug() . '_' . time() . '.' . $extension,
            'original_filename' => $this->faker->word() . '.' . $extension,
            'mime_type' => $this->getMimeType($extension),
            'width' => $this->faker->numberBetween(400, 1920),
            'height' => $this->faker->numberBetween(300, 1080),
            'alt_text' => $this->faker->sentence(3),
            'is_optimized' => true,
            'variants' => $this->getImageVariants(),
            'metadata' => [
                'exif' => [
                    'camera' => $this->faker->word(),
                    'iso' => $this->faker->numberBetween(100, 3200),
                ],
                'colors' => $this->faker->hexColor(),
            ],
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->slug() . '_' . time() . '.mp4',
            'original_filename' => $this->faker->word() . '.mp4',
            'mime_type' => 'video/mp4',
            'size' => $this->faker->numberBetween(5 * 1024 * 1024, 100 * 1024 * 1024), // 5MB to 100MB
            'width' => $this->faker->numberBetween(640, 1920),
            'height' => $this->faker->numberBetween(480, 1080),
            'alt_text' => null,
            'is_optimized' => false,
            'variants' => null,
            'metadata' => [
                'duration' => $this->faker->numberBetween(30, 3600), // 30 seconds to 1 hour
                'bitrate' => $this->faker->numberBetween(500, 5000),
                'codec' => 'h264',
            ],
        ]);
    }

    public function document(): static
    {
        $extension = $this->faker->randomElement(['pdf', 'doc', 'docx', 'txt']);
        
        return $this->state(fn (array $attributes) => [
            'filename' => $this->faker->slug() . '_' . time() . '.' . $extension,
            'original_filename' => $this->faker->word() . '.' . $extension,
            'mime_type' => $this->getMimeType($extension),
            'size' => $this->faker->numberBetween(10 * 1024, 5 * 1024 * 1024), // 10KB to 5MB
            'width' => null,
            'height' => null,
            'alt_text' => null,
            'is_optimized' => false,
            'variants' => null,
            'metadata' => [
                'pages' => $extension === 'pdf' ? $this->faker->numberBetween(1, 100) : null,
                'author' => $this->faker->name(),
            ],
        ]);
    }

    public function optimized(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optimized' => true,
            'variants' => $this->getImageVariants(),
        ]);
    }

    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'size' => $this->faker->numberBetween(10 * 1024 * 1024, 50 * 1024 * 1024), // 10MB to 50MB
            'width' => $this->faker->numberBetween(1920, 4000),
            'height' => $this->faker->numberBetween(1080, 3000),
        ]);
    }

    private function getMimeType(string $extension): string
    {
        return match($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'mp4' => 'video/mp4',
            'mp3' => 'audio/mpeg',
            default => 'application/octet-stream',
        };
    }

    private function getMetadata(string $extension, bool $isImage): array
    {
        if ($isImage) {
            return [
                'exif' => [
                    'camera' => $this->faker->optional()->word(),
                    'iso' => $this->faker->optional()->numberBetween(100, 3200),
                ],
                'colors' => [$this->faker->hexColor(), $this->faker->hexColor()],
            ];
        }

        return [
            'file_info' => [
                'created_at' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
                'modified_at' => $this->faker->dateTime()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    private function getImageVariants(): array
    {
        return [
            'thumbnail' => 'cms/media/thumbnails/' . $this->faker->slug() . '_thumb.jpg',
            'small' => 'cms/media/small/' . $this->faker->slug() . '_small.jpg',
            'medium' => 'cms/media/medium/' . $this->faker->slug() . '_medium.jpg',
            'large' => 'cms/media/large/' . $this->faker->slug() . '_large.jpg',
        ];
    }
}