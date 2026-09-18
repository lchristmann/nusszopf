<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
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
            'title' => $this->faker->sentence(4),
            'goal' => $this->faker->sentence(8),
            'description' => $this->faker->paragraphs(3, true),
            'visibility' => 'private',
            'contact' => 'mail@nusszopf.org',
        ];
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => 'public',
        ]);
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => 'private',
        ]);
    }
}
