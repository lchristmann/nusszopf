<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use App\Support\ProjectDate;
use App\Support\RichText;
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
        $description = $this->faker->paragraph();

        // The shape the creation wizard persists for a location-independent,
        // flexible-period project with no team/motto.
        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(4),
            'goal' => $this->faker->sentence(8),
            'description' => $description,
            'description_template' => RichText::fromPlainText($description),
            'location' => ['remote' => true, 'searchTerm' => '', 'data' => (object) []],
            'period' => ['flexible' => true, 'from' => '', 'to' => ''],
            'team' => null,
            'team_template' => null,
            'motto' => null,
            'visibility' => 'private',
            'contact' => 'mail@nusszopf.org',
        ];
    }

    /**
     * A project tied to a place, as the wizard stores a selected suggestion.
     */
    public function withLocation(string $city = 'Leipzig'): static
    {
        return $this->state(fn (array $attributes): array => [
            'location' => [
                'remote' => false,
                'searchTerm' => "{$city}, Sachsen, Deutschland",
                'data' => [
                    'key' => 'stub-1',
                    'postcode' => '04109',
                    'city' => $city,
                    'countryCode' => 'de',
                    'geo' => ['lat' => '51.3406321', 'lon' => '12.3747329'],
                    'osm' => ['id' => '62649', 'type' => 'relation'],
                ],
            ],
        ]);
    }

    /**
     * A fixed period; dates are given as the wizard's `d.m.yyyy` input.
     */
    public function withPeriod(string $from = '1.3.2027', string $to = '31.5.2027'): static
    {
        return $this->state(fn (array $attributes): array => [
            'period' => [
                'flexible' => false,
                'from' => ProjectDate::toStored($from),
                'to' => ProjectDate::toStored($to),
            ],
        ]);
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
