<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The dataset of the P-6 search-latency measurement (docs/release/parity/P-06-performance.md): 200 owners and 2,000
 * public projects, each with 0–5 requests (about 5,000), a quarter of them tied to a place (Leipzig). How much data the historical
 * instance held is not known; this is a deliberate overestimate for a self-hosted community instance, not evidence.
 *
 * Model events are off while inserting: otherwise every request insert touches its project, whose `saved` hook queues
 * a sync of all its requests. Run `php artisan search:reindex` afterwards, as for any bulk load.
 */
class PerformanceDatasetSeeder extends Seeder
{
    public const PROJECTS = 2000;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The performance dataset is for development only.');
        }

        mt_srand(6);
        $owners = User::factory()->count(200)->create();

        Model::withoutEvents(function () use ($owners): void {
            for ($i = 0; $i < self::PROJECTS; $i++) {
                $factory = Project::factory()->for($owners[$i % $owners->count()])->public();
                $project = ($i % 4 === 0 ? $factory->withLocation() : $factory)->create();
                ProjectRequest::factory()->for($project)->count(mt_rand(0, 5))->create();
            }
        });
    }
}
