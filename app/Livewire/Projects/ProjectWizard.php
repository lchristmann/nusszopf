<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\ManagesProjectFields;
use App\Livewire\Concerns\ManagesRequestDialog;
use App\Models\Project;
use App\Support\RichText;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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
 *
 * Step 3 ("Gesuche") builds a list of requests in that same form state
 * (`RequestsStep.js`): the dialog adds, edits and removes entries of
 * {@see self::$requests} locally, nothing is written, and zero requests is valid.
 */
#[Layout('components.layout', [
    'goBackUri' => '/user/projects',
    'mainClass' => 'bg-white text-lilac-800 lg:bg-steel-100',
    'footerBg' => 'bg-steel-100',
])]
class ProjectWizard extends Component
{
    use ManagesProjectFields, ManagesRequestDialog;

    /** `createProjectData.steps` — the progress-bar label per step. */
    public const STEPS = ['Beschreibung 1/2', 'Beschreibung 2/2', 'Gesuche', 'Einstellungen'];

    /** @var int|string an out-of-range/non-numeric browser-history value is ignored, so it is untyped */
    #[Url(as: 'step', history: true)]
    public $step = 0;

    /**
     * The requests created in step 3, in creation order (`requests: []` in
     * the form's initial values); `created_at` is only for the list's date.
     *
     * @var array<int, array<string, mixed>> `title`, `category`, `description` (a document) and `created_at`; client-writable, so only trusted after validation
     */
    public array $requests = [];

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
        $this->closeRequestDialog();
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
        $this->closeRequestDialog();
        $this->step = $step;
        $this->js('window.scrollTo(0, 0)');
    }

    protected function requestFormValues(string $key): ?array
    {
        if (! ctype_digit($key) || ! isset($this->requests[(int) $key])) {
            return null;
        }

        $request = $this->requests[(int) $key];

        return [
            'title' => (string) ($request['title'] ?? ''),
            'category' => (string) ($request['category'] ?? ''),
            'description' => RichText::normalize($request['description'] ?? null),
        ];
    }

    protected function storeRequest(?string $key, array $values): bool
    {
        if ($key === null) {
            $this->requests[] = $values + ['created_at' => now()->toIso8601String()];
        } elseif (ctype_digit($key) && isset($this->requests[(int) $key])) {
            $this->requests[(int) $key] = $values + ['created_at' => $this->requests[(int) $key]['created_at'] ?? now()->toIso8601String()];
        }

        return true;
    }

    /**
     * The list is client-writable: whatever arrives is reduced to well-formed
     * entries (validity of the values is checked when the project is created).
     */
    public function updatedRequests(): void
    {
        $this->requests = collect($this->requests)
            ->filter(fn (mixed $request): bool => is_array($request)) // @phpstan-ignore function.alreadyNarrowedType (client-writable)
            ->map(function (array $request): array {
                $createdAt = is_string($request['created_at'] ?? null) ? strtotime($request['created_at']) : false;

                return [
                    'title' => is_string($request['title'] ?? null) ? $request['title'] : '',
                    'category' => is_string($request['category'] ?? null) ? $request['category'] : '',
                    'description' => RichText::normalize($request['description'] ?? null),
                    'created_at' => $createdAt === false ? now()->toIso8601String() : Carbon::createFromTimestamp($createdAt)->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * `handleDelete` in `RequestsStep.js`: removed from the form, no confirmation.
     */
    public function deleteRequest(string $key): void
    {
        if (ctype_digit($key)) {
            unset($this->requests[(int) $key]);
            $this->requests = array_values($this->requests);
        }
    }

    /**
     * The persisted form of every request created in step 3, or `null` when
     * one of them is invalid (the property is client-writable, and the project
     * is only created if everything in it is valid).
     *
     * @return list<array<string, mixed>>|null
     */
    private function validRequests(): ?array
    {
        $requests = [];

        foreach ($this->requests as $request) {
            $values = [
                'requestTitle' => $request['title'] ?? null,
                'requestCategory' => $request['category'] ?? null,
                'requestDescription' => $request['description'] ?? null,
            ];

            if (Validator::make($values, $this->requestRules(), $this->requestMessages())->fails()) {
                return null;
            }

            $description = RichText::normalize($values['requestDescription']);
            $requests[] = [
                'title' => $values['requestTitle'],
                'category' => $values['requestCategory'],
                'description' => RichText::toPlainText($description),
                'description_template' => $description,
            ];
        }

        return $requests;
    }

    private function create(): void
    {
        // `handleSubmit` re-validates steps 1 and 2 at the point of persistence.
        $valid = $this->validateFields($this->stepFields(0), onScreenOnly: false)
            & $this->validateFields($this->stepFields(1), onScreenOnly: false);

        $requests = $this->validRequests();

        if (! $valid || $requests === null) {
            $this->dispatch('toast', type: 'error', message: 'Bitte überprüfe deine Eingaben oder versuche es später erneut.');

            return;
        }

        Gate::authorize('create', Project::class);

        $user = Auth::user();

        try {
            // `user_id` comes from the session, never from the form. The project
            // and its requests are one unit: historically the requests were a
            // second call, so a failure left a project without them behind (BUG-027).
            DB::transaction(function () use ($user, $requests): void {
                $project = $user->projects()->create($this->descriptionAttributes() + $this->settingsAttributes($user));

                foreach ($requests as $request) {
                    $project->requests()->create($request);
                }
            });
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
