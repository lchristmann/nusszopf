<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRequest>
 */
class ProjectRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $description = $this->faker->sentence(10);

        return [
            'project_id' => Project::factory(),
            'title' => $this->faker->text(30),
            'category' => $this->faker->randomElement(ProjectRequest::CATEGORIES),
            'description' => $description,
            'description_template' => RichText::fromPlainText($description),
        ];
    }

    public function category(string $category): static
    {
        return $this->state(fn (): array => ['category' => $category]);
    }
}
