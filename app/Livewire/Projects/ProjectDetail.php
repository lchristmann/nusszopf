<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * docs/design/screen-specs.md, "Project detail". Public, but a private
 * project must 404 for anyone but its owner — never a 403 (which would
 * reveal the project's existence to a non-owner), matching the confirmed
 * historical SSR behavior (docs/rewrite/open-questions.md, "Does
 * /projects/{id}'s SSR enforce visibility, or only existence?").
 */
#[Layout('components.layout')]
class ProjectDetail extends Component
{
    public Project $project;

    public function mount(Project $project): void
    {
        if (Gate::denies('view', $project)) {
            abort(404);
        }

        $this->project = $project;
    }

    public function render(): View
    {
        return view('livewire.projects.project-detail');
    }
}
