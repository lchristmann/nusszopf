<?php

namespace App\Livewire\Projects;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * docs/design/screen-specs.md, "User Projects" — the authenticated user's
 * own projects, any visibility. This is also login's fixed post-auth
 * destination (BUG-015, preserved as historically observed).
 *
 * `EditProjectCard`'s per-card actions (view, edit, visibility toggle,
 * delete), `WelcomeCard`'s empty state and `ProjectsSkeleton`'s loading
 * state (`pages/user/projects.js`) — deferred by the second slice to this
 * one (docs/rewrite/master-roadmap.md, slice 5).
 */
// `pages/user/projects.js`: `className="bg-white text-steel-700 lg:bg-steel-100"`, `footer.className="bg-steel-100"`.
#[Layout('components.layout', ['mainClass' => 'bg-white text-steel-700 lg:bg-steel-100', 'footerBg' => 'bg-steel-100', 'noindex' => true])]
class MyProjects extends Component
{
    /**
     * Mirrors the Search screen's `wire:init="load"` pattern
     * (`app/Livewire/Search/Search.php`): the historical screen fetched its
     * data client-side after the shell rendered, showing `ProjectsSkeleton`
     * in the meantime. A full-page Livewire component renders synchronously
     * on the first request, so the skeleton would otherwise never be
     * observable — this follow-up round trip reproduces the loading state.
     */
    public bool $ready = false;

    public function load(): void
    {
        $this->ready = true;
    }

    /**
     * `handleVisibility` (`pages/user/projects.js`), throttled to 1/second via
     * plain `lodash.throttle(fn, 1000)` — **no options object**, so lodash's
     * own defaults apply: `{ leading: true, trailing: true }`. A rapid second
     * click therefore does not simply vanish historically — it is queued and
     * re-fires the *last* call's arguments once the window elapses, which
     * would flip visibility a second time, ~1s later, with no further click.
     * That is very unlikely to be a deliberate product behavior (it is what
     * calling `throttle()` without `{ trailing: false }` does by default, not
     * a decision anyone made) and reproducing it server-side would need a
     * delayed, cancellable job with its own races against a concurrent
     * edit/delete — real complexity for an edge case with no evidence of
     * intent. Nusszopf 2 **replaces** it with a hard drop (`RateLimiter`,
     * leading-edge only, extra calls inside the window have no effect at
     * all) — the same class of decision the fourth slice made for the
     * 500 ms search-input throttle (`docs/rewrite/fourth-slice.md`,
     * decision 6). Record kept in `docs/rewrite/fifth-slice.md`.
     *
     * `handleVisibility` calls the same shared `updateProject` service
     * `ProjectEdit::saveSettings()` uses, so it carries the same loading/
     * success/error toasts ("Änderungen speichern..." / "Projekt wurde
     * aktualisiert." / "Sorry, die Änderungen konnten nicht gespeichert
     * werden.", BUG-025's corrected spelling) — not a distinct, unconfirmed
     * toast of its own.
     */
    public function toggleVisibility(string $projectId): void
    {
        $project = Auth::user()->projects()->find($projectId);

        abort_if($project === null, 404);
        Gate::authorize('update', $project);

        $key = "project-visibility-toggle:{$project->id}";

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 1)) {
            return;
        }

        RateLimiter::hit($key, decaySeconds: 1);

        $project->visibility = $project->visibility === 'public' ? 'private' : 'public';

        try {
            $project->save();
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, die Änderungen konnten nicht gespeichert werden.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Projekt wurde aktualisiert.');
    }

    /**
     * Invoked after the browser's own `confirm()` (Möchtest Du das Projekt
     * wirklich löschen?), as historically (BUG-013, preserved) — the same
     * deletion `ProjectEdit::deleteProject()` performs from the settings
     * view, reachable here directly from the grid's card menu.
     */
    public function deleteProject(string $projectId): void
    {
        $project = Auth::user()->projects()->find($projectId);

        abort_if($project === null, 404);
        Gate::authorize('delete', $project);

        try {
            $project->delete();
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, das Projekt konnte nicht gelöscht werden.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Das Projekt wurde gelöscht.');
    }

    public function render(): View
    {
        return view('livewire.projects.my-projects', [
            'projects' => $this->ready
                ? Auth::user()->projects()->with(['requests' => fn ($query) => $query->orderByDesc('created_at')->orderByDesc('id')])->latest()->get()
                : collect(),
        ]);
    }
}
