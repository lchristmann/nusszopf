<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * BUG-007 fix (docs/rewrite/bugs.md, docs/rewrite/intentional-changes.md):
 * `projects.visibility` is a real Postgres CHECK-constrained enum, not
 * unconstrained text — a third value must be rejected at the database
 * layer itself, not merely by application-level validation.
 */
it('rejects a third visibility value at the database layer', function () {
    $user = User::factory()->create();

    DB::table('projects')->insert([
        'id' => (string) Str::uuid7(),
        'user_id' => $user->id,
        'title' => 'Titel',
        'goal' => 'Ziel',
        'description' => 'Beschreibung',
        'visibility' => 'unlisted',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
})->throws(QueryException::class);
