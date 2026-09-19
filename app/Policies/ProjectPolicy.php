<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * The Laravel-side authorization primitive for docs/security/authorization-matrix.md's
 * "Project" rows. Every rule here is copied verbatim from that table — do not
 * re-derive it. `Project::scopeVisible()` is the same rule applied to every
 * read *path* (list/search/show), so a private project can never leak via a
 * differently-shaped query than the one this policy was checked against.
 */
class ProjectPolicy
{
    /**
     * View public project: Allowed for anyone. View private project: owner only.
     *
     * `?User` (nullable) so Laravel's Gate still invokes this for guests,
     * matching the historical "anonymous can view public projects" rule.
     *
     * Delegates to `Project::scopeVisible()` — the exact same query scope the
     * search path applies as defense-in-depth (`Search::render()`) — rather
     * than re-stating the "public OR owner" rule a second time in PHP-object
     * space. First-slice acceptance criterion #8 requires this to be one
     * enforcement point, not two independently-maintained checks that could
     * drift apart. Passing `$user?->id` explicitly (not relying on the
     * scope's `Auth::id()` fallback) keeps this correct even when the Gate
     * is evaluating a *different* user than the current session's, e.g.
     * `Gate::forUser($otherUser)->allows(...)`.
     */
    public function view(?User $user, Project $project): bool
    {
        return Project::visible($user?->id)->whereKey($project->id)->exists();
    }

    /**
     * Create project: any authenticated user, always as themselves — the
     * caller can never create on another user's behalf (enforced by the
     * controller/Livewire component setting `user_id` from `Auth::id()`,
     * never from request input, not by this ability alone).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Update project content / toggle visibility: owner only. There is no
     * separate "publish" ability — historically visibility is just another
     * field on the same update permission (authorization-matrix.md).
     */
    public function update(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }

    /**
     * Delete project: owner only.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }
}
