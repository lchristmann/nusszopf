<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The historical `projects_analytics` table (docs/domain/entities.md) — a
 * 1:1 extension of `Project`, keyed by `project_id` rather than its own `id`.
 *
 * BUG-001's fix (docs/rewrite/bugs.md, docs/rewrite/intentional-changes.md):
 * the historical `views` column was insertable/updatable by any caller, for
 * any project, via an open Hasura permission. `$fillable` here is not the
 * enforcement mechanism — mass assignment is only ever reachable from
 * `ProjectDetail::recordView()`'s own server-side code, never from a public
 * Livewire property or a route accepting request input. The actual fix is
 * the *absence* of such a path: no `ProjectAnalyticsPolicy`, no wire:model,
 * no route.
 */
class ProjectAnalytics extends Model
{
    protected $table = 'project_analytics';

    protected $primaryKey = 'project_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['project_id', 'views'];

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
