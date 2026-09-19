<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ManagesProjectFields;
use App\Models\Project;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * The historical four-step project creation wizard (`pages/user/project/
 * create.js`, `useStepper.js`), docs/rewrite/first-slice.md, "The historical
 * 4-step wizard — full mechanics", docs/rewrite/second-slice.md.
 *
 * One form state for the whole wizard (this component's properties); nothing
 * is persisted until the last step's submit, so leaving mid-flow discards
 * everything — there is no draft. The step lives in the `?step=N` query
 * parameter (browser back/forward work). Moving forward validates only the
 * current step's fields and is blocked while they are invalid; moving back
 * never validates. Opening the page always starts at step 0 (the historical
 * stepper re-pushed `?step=0` on mount, discarding a deep-linked step).
 */
#[Layout('components.layout', [
    'goBackUri' => '/user/projects',
    'mainClass' => 'bg-white text-lilac-800 lg:bg-steel-100',
    'footerBg' => 'bg-steel-100',
])]
class ProjectWizard extends Component
{
    use ManagesProjectFields;

    /** `createProjectData.steps` — the progress-bar label per step. */
    public const STEPS = ['Beschreibung 1/2', 'Beschreibung 2/2', 'Gesuche', 'Einstellungen'];

    /** @var int|string an out-of-range/non-numeric browser-history value is ignored, so it is untyped */
    #[Url(as: 'step', history: true)]
    public $step = 0;

    private int|string|null $stepBeforeUpdate = null;

    public function mount(): void
    {
        $this->step = 0;

        // The historical stepper pushed `?step=0` as soon as it mounted, which
        // is what made every entry start at step 0 whatever `?step=` said.
        if ((string) request()->query('step', '') !== '0') {
            $this->redirectRoute('projects.create', ['step' => 0]);
        }
    }

    /**
     * @return list<string>
     */
    protected function stepFields(int $step): array
    {
        return match ($step) {
            0 => ['title', 'goal', 'description', 'location.searchTerm', 'location.data', 'period.from', 'period.to'],
            1 => ['team', 'motto'],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    protected function visibleFields(): array
    {
        return $this->stepFields($this->currentStep());
    }

    public function currentStep(): int
    {
        return (int) $this->step;
    }

    public function updatingStep(mixed $value): void
    {
        $this->stepBeforeUpdate = $this->step;
    }

    /**
     * The step was changed from outside the component (browser back/forward
     * restoring `?step=`). `useStepper` ignores a value that is not a valid
     * step index and keeps the current step; anything valid is followed
     * without validation, exactly as history navigation historically did.
     */
    public function updatedStep(mixed $value): void
    {
        if (! is_numeric($value) || (int) $value != $value || $value < 0 || $value >= count(self::STEPS)) {
            $this->step = $this->stepBeforeUpdate ?? 0;

            return;
        }

        $this->step = (int) $value;
        $this->locationOptions = [];
        $this->resetErrorBag();
    }

    public function next(): void
    {
        $step = $this->currentStep();

        // Forward navigation is gated by the current step's schema; a failed
        // attempt shows that step's errors (Formik touches every field).
        if (! $this->validateFields($this->stepFields($step))) {
            return;
        }

        if ($step < count(self::STEPS) - 1) {
            $this->goToStep($step + 1);

            return;
        }

        $this->create();
    }

    public function back(): void
    {
        $this->goToStep(max(0, $this->currentStep() - 1));
    }

    private function goToStep(int $step): void
    {
        $this->resetErrorBag();
        $this->locationOptions = [];
        $this->step = $step;
        $this->js('window.scrollTo(0, 0)');
    }

    private function create(): void
    {
        // `handleSubmit` re-validates steps 1 and 2 at the point of persistence.
        $valid = $this->validateFields($this->stepFields(0), onScreenOnly: false)
            & $this->validateFields($this->stepFields(1), onScreenOnly: false);

        if (! $valid) {
            $this->dispatch('toast', type: 'error', message: 'Bitte überprüfe deine Eingaben oder versuche es später erneut.');

            return;
        }

        Gate::authorize('create', Project::class);

        $user = Auth::user();

        try {
            // `user_id` comes from the session, never from the form.
            $user->projects()->create($this->descriptionAttributes() + $this->settingsAttributes($user));
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, das Projekt konnte nicht erstellt werden.');

            return;
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Projekt wurde erstellt.']);

        $this->redirectRoute('projects.mine');
    }

    public function render(): View
    {
        return view('livewire.projects.project-wizard', ['email' => Auth::user()->email]);
    }
}
