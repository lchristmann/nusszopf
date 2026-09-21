<?php

namespace App\Models;

use Database\Factories\ProjectRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\Searchable;

/**
 * A "Gesuch": something a project is still looking for (docs/domain/entities.md,
 * `Request`; the model is `ProjectRequest`, register B7). Its visibility is
 * always its project's (BUG-002): every read goes through {@see self::scopeVisible()}
 * or `ProjectRequestPolicy::view()`, both of which inherit {@see Project::scopeVisible()}.
 *
 * @property string $project_id
 * @property string $title
 * @property string $category
 * @property string $description
 * @property array<string, mixed> $description_template
 */
#[Fillable(['title', 'category', 'description', 'description_template'])]
class ProjectRequest extends Model
{
    /** @use HasFactory<ProjectRequestFactory> */
    use HasFactory, HasUuids, Searchable;

    /** `REQUEST_CATEGORY` (enums.js) — `none` is "nothing chosen yet", not a category. */
    public const CATEGORIES = ['companions', 'rooms', 'materials', 'financials', 'others'];

    /** `request-form.data.js`, `category.options` */
    public const CATEGORY_LABELS = [
        'companions' => 'Mitstreiter:innen',
        'rooms' => 'Räume',
        'materials' => 'Materialien',
        'financials' => 'Finanzielles',
        'others' => 'Sonstiges',
    ];

    /**
     * Whatever changes a request of a public project — create, edit, delete —
     * bumps that project's `updated_at` (the historical indexer's
     * `_syncProject`, only for a public project), which is what the detail
     * page's "Aktualisiert am" and the search ranking's recency tie-break show.
     * The project's own save then re-syncs its search documents.
     */
    protected static function booted(): void
    {
        $touchPublicProject = function (self $request): void {
            // Loaded afresh: the relation may have been cached before the project was last written.
            $project = Project::find($request->project_id);

            if ($project?->visibility === 'public') {
                $project->touch();
            }
        };

        static::saved($touchPublicProject);
        static::deleted($touchPublicProject);
    }

    protected function casts(): array
    {
        return ['description_template' => 'array'];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The single "who may see this request" rule (BUG-002): a request is
     * visible exactly when its project is. Applied to every read path.
     *
     * @param  Builder<ProjectRequest>  $query
     * @return Builder<ProjectRequest>
     */
    public function scopeVisible(Builder $query, ?string $viewerId = null): Builder
    {
        $viewer = func_num_args() > 1 ? $viewerId : Auth::id();
        $viewer = $viewer === null ? null : (string) $viewer;

        return $query->whereHas('project', fn (Builder $project) => $project->visible($viewer));
    }

    /**
     * The index is one shared `items` collection of project and request
     * documents (search.function.js; docs/search/README.md).
     */
    public function searchableAs(): string
    {
        return Project::searchIndexName();
    }

    /**
     * A bulk import (`search:reindex`) builds every document from the request's
     * project and its author; load them in one query each instead of per request.
     *
     * @param  Builder<ProjectRequest>  $query
     * @return Builder<ProjectRequest>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('project.user');
    }

    /**
     * Only a public project's requests are indexed — the historical indexer's
     * gate, independent of the request's own permissions.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->project?->visibility === 'public';
    }

    /**
     * `_parseRequestToDocument`: the project's own document fields, the
     * request's title/description/category, and `group_id` (the project's id)
     * for the grouped hit. The project's `updated_at` is the document's.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            ...$this->project->projectDocument(),
            'req_title' => $this->title,
            'req_description' => $this->description,
            'req_type' => $this->category,
            'group_id' => $this->project_id,
        ];
    }
}
