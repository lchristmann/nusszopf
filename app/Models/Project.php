<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Laravel\Scout\Searchable;

/**
 * The JSON-cast columns follow the shapes in docs/domain/entities.md:
 * `location` = { remote, searchTerm, data }, `period` = { flexible, from, to },
 * `*_template` = a ProseMirror document (App\Support\RichText). All are null
 * for a project created by the first slice's single form.
 *
 * @property string $description
 * @property array<string, mixed>|null $description_template
 * @property array<string, mixed>|null $location
 * @property array<string, mixed>|null $period
 * @property string|null $team
 * @property array<string, mixed>|null $team_template
 * @property string|null $motto
 * @property string $visibility
 * @property string $contact
 */
#[Fillable([
    'title',
    'goal',
    'description',
    'description_template',
    'location',
    'period',
    'team',
    'team_template',
    'motto',
    'visibility',
    'contact',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids, Searchable;

    /**
     * The `contact` value meaning "first contact runs through Nusszopf"
     * (`NZ_EMAIL` in the historical `enums.js`); any other value is the
     * owner's own, publicly shown e-mail address.
     */
    public const NUSSZOPF_CONTACT = 'mail@nusszopf.org';

    /**
     * Search is one shared `items` index holding a document per request, or —
     * for a project without requests — one for the project (search.function.js,
     * docs/search/README.md).
     */
    public const SEARCH_INDEX = 'items';

    /**
     * A change to a project re-syncs its request documents (they carry the
     * project's fields): they follow the project's visibility and refreshed
     * `updated_at`. Requests are removed from the index before the database
     * cascades their rows away, which raises no model events.
     */
    protected static function booted(): void
    {
        static::saved(function (self $project): void {
            $requests = $project->requests()->get()->each->setRelation('project', $project);

            $project->visibility === 'public' ? $requests->searchable() : $requests->unsearchable();
        });

        static::deleting(fn (self $project) => $project->requests()->get()->unsearchable());
    }

    public static function searchIndexName(): string
    {
        return config('scout.prefix').self::SEARCH_INDEX;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'description_template' => 'array',
            'location' => 'array',
            'period' => 'array',
            'team_template' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProjectRequest, $this>
     */
    public function requests(): HasMany
    {
        return $this->hasMany(ProjectRequest::class);
    }

    /**
     * @return HasOne<ProjectAnalytics, $this>
     */
    public function analytics(): HasOne
    {
        return $this->hasOne(ProjectAnalytics::class);
    }

    /**
     * The single enforcement point for "who may see this project" — applied
     * to every read path (direct show via `ProjectPolicy::view()`, which
     * delegates here rather than re-stating the rule; search's query-time
     * defense-in-depth via `Search::render()`), not re-implemented per call
     * site. Closes BUG-002's core mechanism (docs/security/authorization-matrix.md,
     * "Search" row) from the first commit: a project is visible to the
     * public only once published, and always visible to its own owner
     * regardless of visibility.
     *
     * `$viewerId` defaults to the currently authenticated user so ordinary
     * call sites (`Project::visible()`) need no argument, but accepts an
     * explicit override so `ProjectPolicy::view($user, ...)` can evaluate
     * the rule for whatever `$user` the Gate was resolved for — which is
     * not always `Auth::id()` (e.g. `Gate::forUser($otherUser)`).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeVisible(Builder $query, ?string $viewerId = null): Builder
    {
        // No argument at all (the ordinary `Project::visible()` call site) means
        // "use the current session's user"; an explicit `null` means "guest" —
        // distinct cases, since a Policy check for a guest must not silently
        // fall back to whoever happens to be logged into the current session.
        $viewer = func_num_args() > 1 ? $viewerId : Auth::id();

        return $query->where(function (Builder $query) use ($viewer): void {
            $query->where('visibility', 'public');

            if ($viewer !== null) {
                $query->orWhere('user_id', $viewer);
            }
        });
    }

    public function searchableAs(): string
    {
        return self::searchIndexName();
    }

    /**
     * Only public projects are ever written to the search index — the same
     * indexing-time visibility gate the historical indexer enforced
     * (docs/search/README.md). A project flipped private is removed from
     * the index by Scout automatically, since this becomes false.
     *
     * A project with requests has no document of its own: its requests carry
     * its fields instead (`_updateProjectAndRequests` deleted the project's
     * document once request documents existed).
     */
    public function shouldBeSearchable(): bool
    {
        return $this->visibility === 'public' && ! ($this->requests_exists ?? $this->requests()->exists());
    }

    /**
     * A bulk import (`search:reindex`) loads each chunk's owners and "has requests" flags in two queries, instead of
     * two queries per project (P-6, docs/release/parity/P-06-performance.md).
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('user')->withExists('requests');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [...$this->projectDocument(), 'req_type' => 'none', 'group_id' => $this->id];
    }

    /**
     * The fields every document of this project carries, whether it is the
     * project's own or one of its requests'.
     *
     * @return array<string, mixed>
     */
    public function projectDocument(): array
    {
        $remote = (bool) ($this->location['remote'] ?? true);
        $flexible = (bool) ($this->period['flexible'] ?? true);

        // The historical `_parseProjectToDocument` field set
        // (search.function.js): title, goal, description, team, motto, author,
        // location text/remote/geo and the period as timestamps.
        return [
            'title' => $this->title,
            'goal' => $this->goal,
            'description' => $this->description,
            'team' => $this->team,
            'motto' => $this->motto,
            'author' => $this->user?->name,
            'location_text' => $remote ? '' : ($this->location['searchTerm'] ?? ''),
            'location_remote' => $remote,
            'location_geo' => $remote ? new \stdClass : ($this->location['data']['geo'] ?? new \stdClass),
            'period_flexible' => $flexible,
            'period_from' => $flexible ? null : $this->periodTimestamp('from'),
            'period_to' => $flexible ? null : $this->periodTimestamp('to'),
            'updated_at' => $this->updated_at?->timestamp,
        ];
    }

    private function periodTimestamp(string $edge): ?int
    {
        $stored = $this->period[$edge] ?? '';

        return $stored === '' ? null : CarbonImmutable::parse($stored)->getTimestamp();
    }

    /**
     * Whether the owner's own e-mail address is the public contact
     * (`contact = true` in the wizard's form state).
     */
    public function hasPersonalContact(): bool
    {
        return $this->contact !== self::NUSSZOPF_CONTACT;
    }
}
