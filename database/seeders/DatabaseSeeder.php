<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with local-development fixtures.
     *
     * Nusszopf has no admin/staff role and no first-user bootstrap step
     * (docs/deployment/README.md, "Installation (operator path)") — this
     * seeder exists purely for a contributor's local environment, not for
     * production installs, so it deliberately does not run in `compose.prod.yaml`.
     */
    public function run(): void
    {
        $demo = User::factory()->create([
            'name' => 'demo',
            'email' => 'demo@nusszopf.test',
        ]);

        Project::factory()->public()->create([
            'user_id' => $demo->id,
            'title' => 'Nachbarschaftsgarten Nusszopf',
            'goal' => 'Eine gemeinsame Grünfläche für die ganze Nachbarschaft schaffen.',
        ]);

        Project::factory()->private()->create([
            'user_id' => $demo->id,
            'title' => 'Privates Entwurfsprojekt',
        ]);
    }
}
