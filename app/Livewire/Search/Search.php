<?php

namespace App\Livewire\Search;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * docs/design/screen-specs.md, "Search" — proves the Scout/Meilisearch
 * query-side integration end to end. Only `Project` is searchable this
 * slice (no `Request` child resource yet, docs/rewrite/first-slice.md), so
 * there is no grouped-by-project HitCard nesting to reproduce yet — that
 * lands with the wizard/requests in the second slice.
 *
 * Query interaction (verified against `SearchInput.js` in this
 * verification pass): historically search fires only on an explicit
 * submit (Enter, blur-after-Enter, or the search-icon click), throttled
 * 500ms against rapid resubmission — never live-as-you-type. `#[Url]`
 * still keeps the query deep-linkable/shareable; only *when* a query
 * triggers a search changed, via `search()` below, not `wire:model.live`.
 *
 * Visibility is enforced at *indexing* time (Project::shouldBeSearchable()),
 * matching the historical mechanism (docs/search/README.md) — a private
 * project is never in the index for this query to accidentally surface.
 * `Project::visible()` is additionally applied to the result-hydration
 * query as defense-in-depth (docs/search/README.md, "Authorization
 * filtering at search time": "should also apply a defense-in-depth
 * query-time scope, since Scout drivers/queries in Laravel are easier to
 * accidentally call without the gate than the historical bespoke webhook
 * was") — a stale/leaked index document is still filtered out here, not
 * just relied upon to never exist.
 */
#[Layout('components.layout')]
class Search extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    /**
     * Explicit-submit trigger (Enter in the search field, or the search
     * button) — no-op body, since the deferred `wire:model` binding already
     * synced `$query` before this action ran; the action's own network
     * round-trip is what re-renders the results.
     */
    public function search(): void {}

    public function render(): View
    {
        // An empty query is a real, unremarkable state historically — the
        // page browses every public project, not an empty results screen
        // waiting for input (Meilisearch's own empty-query behavior already
        // matches "match everything").
        return view('livewire.search.search', [
            'hits' => Project::search($this->query)->query(fn ($query) => $query->visible())->get(),
        ]);
    }
}
