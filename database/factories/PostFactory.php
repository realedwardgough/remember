<?php

namespace Database\Factories;

use App\Enum\TimelinePostType;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
            'post_type' => fake()->randomElement(TimelinePostType::cases())->value,
            'published_at' => fake()->dateTimeBetween('-1 year'),
            'visibility' => 'family',
        ];
    }
}
