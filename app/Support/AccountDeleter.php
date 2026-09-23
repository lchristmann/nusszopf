<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Account deletion (docs/domain/workflows.md, "Workflow: account deletion";
 * docs/rewrite/open-questions.md, "Account deletion and orphaned external
 * state" — resolved here).
 *
 * Historically, the `users` row was deleted immediately (a Hasura mutation,
 * cascading `projects`/`requests`/`projects_analytics` at the DB level) and
 * an async webhook (`clean_up_deleted_user`) was relied on to clean up the
 * *external* state a plain SQL cascade cannot reach: the search index and the
 * stored avatar file. That webhook had only three retries and no dead-letter
 * queue — once it gave up, the row that would have driven a retry was
 * already gone, so a failure there was permanent and undetectable.
 *
 * Nusszopf 2 avoids that shape entirely rather than reproducing it: every
 * project is deleted one at a time through Eloquent (`Project::delete()`),
 * not left to the database's `ON DELETE CASCADE`, because only an Eloquent
 * delete fires the model events `Project::booted()` already uses to
 * de-index the project and its requests (`app/Models/Project.php`) — a raw
 * cascade delete raises no events at all and would silently orphan those
 * search documents. The avatar file, the newsletter lead for the same address
 * (decision A-1) and the `users` row are removed last,
 * inside the same transaction, so a failure before that point leaves the
 * account fully intact (safe to retry) instead of partially deleted.
 */
final class AccountDeleter
{
    public static function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->projects()->get()->each(fn (Project $project) => $project->delete());

            AvatarUploader::deleteStoredAvatar($user);

            // Decision A-1: the newsletter subscription for the same address goes with the account.
            Newsletter::forget($user->email);

            $user->delete();
        });
    }
}
