<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * First-slice project creation AND editing, both through this one form.
 *
 * This is deliberately **not** the historical four-step wizard
 * (docs/rewrite/first-slice.md, "Final Nusszopf behavior vs. temporary
 * implementation-slice scaffolding") — it covers only title/goal/
 * description/visibility, the exact field set the first slice's acceptance
 * criteria call for. The full wizard (location/period/team/motto/contact/
 * requests) is second-slice scope; this form's fields become the wizard's
 * step 1 without being rebuilt, not replaced by it.
 *
 * Reusing one component for create and edit is what satisfies acceptance
 * criterion #5: "the owner can toggle visibility to public through the
 * same update path used for editing content" — there is no separate
 * publish action, exactly as historically.
 */
#[Layout('components.layout')]
class ProjectForm extends Component
{
    public ?Project $project = null;

    public string $title = '';

    public string $goal = '';

    public string $description = '';

    public string $visibility = 'private';

    public function mount(?Project $project = null): void
    {
        if ($project !== null) {
            // 404, not 403 (BUG-021, docs/rewrite/bugs.md): a non-owner must
            // never be able to distinguish "this project isn't yours" from
            // "this project doesn't exist" via the HTTP status code, matching
            // ProjectDetail's existing no-existence-leak treatment and the
            // historical edit screen's own redirect target (/404).
            abort_if(Gate::denies('update', $project), 404);

            $this->project = $project;
            $this->title = $project->title;
            $this->goal = $project->goal;
            $this->description = $project->description;
            $this->visibility = $project->visibility;
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:40'],
            'goal' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:6000'],
            'visibility' => ['required', 'in:private,public'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'title.required' => 'Gib einen Titel ein',
            'title.max' => 'Nicht mehr als 40 Zeichen',
            'goal.required' => 'Gib ein Ziel ein',
            'goal.max' => 'Nicht mehr als 150 Zeichen',
            'description.required' => 'Gib eine Beschreibung ein',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->project === null) {
            Gate::authorize('create', Project::class);

            $this->project = Auth::user()->projects()->create($data);
        } else {
            Gate::authorize('update', $this->project);

            $this->project->update($data);
        }

        $this->dispatch('toast', type: 'success', message: 'Projekt gespeichert.');

        $this->redirectRoute('projects.mine', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.projects.project-form');
    }
}
