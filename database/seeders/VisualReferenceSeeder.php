<?php

namespace Database\Seeders;

use App\Support\ProjectDate;
use App\Support\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * The fixed dataset of the visual-regression suite
 * (docs/testing/visual-regression.md): the same users, projects and requests
 * as `tests/Visual/historical-harness/seed.mjs` writes into the historical
 * app, with fixed ids and dates so every screenshot is reproducible.
 *
 * Written with plain inserts, not models: no search-sync jobs and no
 * `updated_at` bumps. Run on a fresh database, then `search:reindex`.
 */
class VisualReferenceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The visual reference dataset is for development and CI only.');
        }

        $data = json_decode(File::get(base_path('tests/Visual/reference-data.json')), true, flags: JSON_THROW_ON_ERROR);
        $at = CarbonImmutable::parse($data['timestamp']);
        $users = [];

        foreach ($data['users'] as $user) {
            DB::table('users')->insert([
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'email_verified_at' => $at,
                'password' => Hash::make($user['password']),
                'created_at' => $at,
                'updated_at' => $at,
            ]);
            $users[$user['name']] = $user['id'];
        }

        foreach ($data['projects'] as $project) {
            $description = implode("\n", $project['description']);
            $team = implode("\n", $project['team']);

            DB::table('projects')->insert([
                'id' => $project['id'],
                'user_id' => $users[$project['user']],
                'title' => $project['title'],
                'goal' => $project['goal'],
                'description' => $description,
                'description_template' => json_encode(RichText::fromPlainText($description)),
                'team' => $team === '' ? null : $team,
                'team_template' => $team === '' ? null : json_encode(RichText::fromPlainText($team)),
                'motto' => $project['motto'] === '' ? null : $project['motto'],
                'location' => json_encode($project['location']),
                'period' => json_encode([
                    'flexible' => $project['period']['flexible'],
                    'from' => $this->date($project['period']['from']),
                    'to' => $this->date($project['period']['to']),
                ]),
                'visibility' => $project['visibility'],
                'contact' => $project['contact'],
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            DB::table('project_analytics')->insert(['project_id' => $project['id'], 'views' => $project['views']]);

            foreach ($project['requests'] as $index => $request) {
                $text = implode("\n", $request['description']);

                DB::table('project_requests')->insert([
                    'id' => $request['id'],
                    'project_id' => $project['id'],
                    'title' => $request['title'],
                    'category' => $request['category'],
                    'description' => $text,
                    'description_template' => json_encode(RichText::fromPlainText($text)),
                    // Distinct, fixed creation times keep "newest first" stable.
                    'created_at' => $at->addMinutes($index),
                    'updated_at' => $at->addMinutes($index),
                ]);
            }
        }
    }

    private function date(string $iso): string
    {
        return $iso === '' ? '' : ProjectDate::toStored(CarbonImmutable::parse($iso)->format('j.n.Y'));
    }
}
