<?php

declare(strict_types=1);

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
    public function event(): static
    {
        return $this->state(fn (): array => ['post_type' => TimelinePostType::EVENT]);
    }

    public function milestone(): static
    {
        return $this->state(fn (): array => ['post_type' => TimelinePostType::MILESTONE]);
    }

    public function memory(): static
    {
        return $this->state(fn (): array => ['post_type' => TimelinePostType::MEMORY]);
    }

    public function letter(): static
    {
        return $this->state(fn (): array => ['post_type' => TimelinePostType::LETTER]);
    }

}
