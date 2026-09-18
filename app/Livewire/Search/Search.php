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
 * Visibility is enforced at *indexing* time (Project::shouldBeSearchable()),
 * matching the historical mechanism (docs/search/README.md) — a private
 * project is never in the index for this query to accidentally surface.
 */
#[Layout('components.layout')]
class Search extends Component
{
    #[Url(as: 'q')]
    public string $query = '';

    public function render(): View
    {
        // An empty query is a real, unremarkable state historically — the
        // page browses every public project, not an empty results screen
        // waiting for input (Meilisearch's own empty-query behavior already
        // matches "match everything").
        return view('livewire.search.search', [
            'hits' => Project::search($this->query)->get(),
        ]);
    }
}
