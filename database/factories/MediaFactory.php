<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'disk' => 'local',
            'path' => 'posts/1/'.fake()->uuid().'.jpg',
            'thumbnail_path' => null,
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 500_000),
            'width' => fake()->numberBetween(800, 2400),
            'height' => fake()->numberBetween(600, 1800),
            'metadata' => ['extension' => 'jpg'],
            'sort_order' => 0,
        ];
    }
}
