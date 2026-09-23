<?php

namespace App\Livewire\Search;

use App\Services\Search\ProjectSearch;
use App\Services\Search\SearchResults;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * docs/design/screen-specs.md, "Search" (`pages/search.js`, `search.service.js`).
 *
 * Interaction (verified against `SearchInput.js`): a query runs only on an
 * explicit submit — Enter or the search icon — never while typing. The filter
 * popover's checkboxes are picked first and applied by that same submit; until
 * then the icon shows "refresh" (Alpine, in the view, compares the picked
 * options with {@see self::$filter}). Both are deep-linkable (`?q=`, `?f[]=`).
 *
 * The page opens on the skeleton and loads the first results right after
 * (`wire:init`), as the historical page did with its first, empty query.
 * "Mehr laden" asks for one more page of documents ({@see ProjectSearch::pageSize()});
 * the whole result is re-fetched from the start, which keeps it free of
 * duplicates however the index changed in between.
 *
 * Visibility is enforced when documents are indexed and again when they are
 * shown ({@see ProjectSearch}); this component adds nothing of its own to that.
 *
 * @property-read SearchResults $results
 */
// `pages/search.js`: `<Page className="bg-white text-steel-700" footer={{ className: 'bg-white' }}>`.
#[Layout('components.layout', ['mainClass' => 'bg-white text-steel-700', 'footerBg' => 'bg-white'])]
class Search extends Component
{
    /** `SearchInput.js`: `maxLength="30"` */
    public const MAX_QUERY_LENGTH = 30;

    #[Url(as: 'q')]
    public string $query = '';

    /**
     * The applied filter: the checked options of {@see ProjectSearch::CATEGORIES}.
     *
     * @var list<string>
     */
    #[Url(as: 'f')]
    public array $filter = [];

    public int $pages = 1;

    public bool $ready = false;

    public function load(): void
    {
        $this->ready = true;
    }

    /**
     * The submit: applies the query and the picked options and starts over at the first page.
     *
     * @param  list<string>  $filter
     */
    public function search(array $filter = []): void
    {
        $this->query = mb_substr($this->query, 0, self::MAX_QUERY_LENGTH);
        $this->filter = array_values(array_intersect(ProjectSearch::CATEGORIES, $filter));
        $this->pages = 1;
        unset($this->results);
    }

    public function loadMore(): void
    {
        $this->pages++;
        unset($this->results);

        if ($this->results->failed) {
            // `loadMore()`'s catch: a toast, the hits stay as they were.
            $this->pages--;
            unset($this->results);
            $this->dispatch('toast', type: 'error', message: 'Sorry! Das hat gerade nicht geklappt.');
        }
    }

    #[Computed]
    public function results(): SearchResults
    {
        if (! $this->ready) {
            return new SearchResults([], false);
        }

        return app(ProjectSearch::class)->search(mb_substr($this->query, 0, self::MAX_QUERY_LENGTH), $this->filter, $this->pages);
    }

    public function render(): View
    {
        return view('livewire.search.search');
    }
}
