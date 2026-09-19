<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ManagesProjectFields;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * The historical project edit screen (`pages/user/project/[id]/edit.js`):
 * one page with a view selector — "Beschreibung", "Gesuche", "Einstellungen" —
 * each historically its own independent form. "Beschreibung" saves the
 * description-step fields (with the wizard's validation, all at once);
 * "Einstellungen" saves visibility and contact and holds project deletion.
 * Switching views discards unsaved changes (after the historical native
 * `confirm()`, driven from the view) and reloads the stored values.
 * "Gesuche" is intentional scaffolding until ProjectRequests exist.
 *
 * Only the owner reaches this screen: anyone else — including anonymous
 * visitors, who are sent to log in first — gets a 404, never a 403 (BUG-021).
 */
#[Layout('components.layout', [
    'goBackUri' => '/user/projects',
    'mainClass' => 'bg-white text-lilac-800 lg:bg-steel-100',
    'footerBg' => 'bg-steel-100',
])]
class ProjectEdit extends Component
{
    use ManagesProjectFields;

    /** `projectEditData.nav` */
    public const VIEWS = ['Beschreibung', 'Gesuche', 'Einstellungen'];

    public Project $project;

    public string $view = 'Beschreibung';

    public function mount(Project $project): void
    {
        // 404, not 403 (BUG-021, docs/rewrite/bugs.md): a non-owner must
        // never be able to distinguish "this project isn't yours" from
        // "this project doesn't exist" via the HTTP status code.
        abort_if(Gate::denies('update', $project), 404);

        $this->project = $project;
        $this->fillFromProject($project, Auth::user());
    }

    /**
     * @return list<string>
     */
    protected function visibleFields(): array
    {
        return $this->view === self::VIEWS[0]
            ? ['title', 'goal', 'description', 'location.searchTerm', 'location.data', 'period.from', 'period.to', 'team', 'motto']
            : [];
    }

    /**
     * The view selector: a change of view throws away unsaved edits.
     */
    public function selectView(string $view): void
    {
        if (! in_array($view, self::VIEWS, true)) {
            return;
        }

        $this->fillFromProject($this->project->refresh(), Auth::user());
        $this->view = $view;
    }

    public function saveProject(): void
    {
        Gate::authorize('update', $this->project);

        if ($this->view !== self::VIEWS[0] || ! $this->validateFields($this->visibleFields())) {
            return;
        }

        $this->persist($this->descriptionAttributes());
    }

    public function saveSettings(): void
    {
        Gate::authorize('update', $this->project);

        if ($this->view !== self::VIEWS[2]) {
            return;
        }

        // Not user-facing (the form only offers the two values), but the
        // property is client-writable and `visibility` is constrained in the DB (BUG-007).
        if (! $this->validateFields(['visibility'], onScreenOnly: false)) {
            $this->dispatch('toast', type: 'error', message: 'Sorry, die Änderungen konnten nicht gespeichert werden.');

            return;
        }

        $this->persist($this->settingsAttributes(Auth::user()));
    }

    /**
     * `if (!formik.dirty) return`: saving an unchanged form does nothing.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes): void
    {
        $this->project->fill($attributes);

        // Eloquent compares decoded JSON strictly, key order included, and
        // PostgreSQL's jsonb does not keep key order — so an unchanged form
        // would otherwise look changed.
        $changed = collect(array_keys($this->project->getDirty()))
            ->contains(fn (string $key): bool => $this->canonical($this->project->getAttribute($key)) !== $this->canonical($this->project->getOriginal($key)));

        if (! $changed) {
            $this->project->refresh();

            return;
        }

        try {
            $this->project->save();
        } catch (Throwable $e) {
            report($e);
            $this->project->refresh();
            $this->dispatch('toast', type: 'error', message: 'Sorry, die Änderungen konnten nicht gespeichert werden.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Projekt wurde aktualisiert.');
        $this->dispatch('form-saved');
    }

    private function canonical(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
        array_is_list($value) || ksort($value);

        return $value;
    }

    /**
     * Invoked after the browser's own `confirm()` (Möchtest Du das Projekt
     * wirklich löschen?) — deliberately the native dialog, as historically
     * (BUG-013, preserved).
     */
    public function deleteProject(): void
    {
        Gate::authorize('delete', $this->project);

        try {
            $this->project->delete();
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, das Projekt konnte nicht gelöscht werden.');

            return;
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Das Projekt wurde gelöscht.']);

        $this->redirectRoute('projects.mine');
    }

    public function render(): View
    {
        return view('livewire.projects.project-edit', ['email' => Auth::user()->email]);
    }
}
