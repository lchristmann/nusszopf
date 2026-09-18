<?php

namespace App\Livewire\Projects;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * docs/design/screen-specs.md, "User Projects" — the authenticated user's
 * own projects, any visibility. This is also login's fixed post-auth
 * destination (BUG-015, preserved as historically observed).
 *
 * Simplified for this slice: no delete/visibility-toggle-from-the-grid
 * interactions yet (docs/design/screen-specs.md documents those, but this
 * slice's acceptance criteria only require create -> view -> edit-to-
 * publish -> view public -> search; per-card actions are second-slice
 * scope alongside the full edit screen).
 */
#[Layout('components.layout')]
class MyProjects extends Component
{
    public function render(): View
    {
        return view('livewire.projects.my-projects', [
            'projects' => Auth::user()->projects()->latest()->get(),
        ]);
    }
}
