<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;

/**
 * docs/security/authorization-matrix.md, "Request" rows. A request has no
 * access rules of its own: it is visible when its project is visible (BUG-002,
 * the `ProjectRequest::visible()` scope is the same rule for list queries) and
 * changed only by its project's owner. Every ability delegates to the project's
 * rule rather than restating it.
 */
class ProjectRequestPolicy
{
    /** View a request: exactly when its project may be viewed. */
    public function view(?User $user, ProjectRequest $request): bool
    {
        return ProjectRequest::visible($user?->id)->whereKey($request->id)->exists();
    }

    /** Create a request: only into a project the user owns. */
    public function create(User $user, Project $project): bool
    {
        return $user->is($project->user);
    }

    /** Update a request: only its project's owner. */
    public function update(User $user, ProjectRequest $request): bool
    {
        return $user->is($request->project->user);
    }

    /** Delete a request: only its project's owner. */
    public function delete(User $user, ProjectRequest $request): bool
    {
        return $user->is($request->project->user);
    }
}
