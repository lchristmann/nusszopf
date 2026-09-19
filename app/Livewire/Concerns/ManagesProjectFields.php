<?php

namespace App\Livewire\Concerns;

use App\Models\Project;
use App\Models\User;
use App\Rules\Project\PeriodDateRule;
use App\Rules\Project\RichTextRule;
use App\Services\LocationSearch;
use App\Support\ProjectDate;
use App\Support\RichText;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The historical project form state and validation, shared by the creation
 * wizard and the edit screen (which historically shared the same field
 * components, `containers/user/ProjectForm/*`, and the same Yup schemas).
 *
 * Validation is deliberately not Livewire's `validate()`: the historical
 * forms validated only the fields of the *current* step/view, showed an
 * error only for a field that had been blurred (Formik's `touched`) or that a
 * failed submit had touched, and never validated anything else. So a
 * component says which keys are on screen ({@see self::visibleFields()}) and
 * this trait validates exactly those, on blur and on submit.
 */
trait ManagesProjectFields
{
    public string $title = '';

    public string $goal = '';

    /** @var array<string, mixed> ProseMirror document, see App\Support\RichText */
    public array $description = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    /** @var array<string, mixed> `{ remote: bool, searchTerm: string, data: object }` */
    public array $location = ['remote' => false, 'searchTerm' => '', 'data' => []];

    /** @var array<string, mixed> `{ flexible: bool, from: string, to: string }`, `d.m.yyyy` strings while editing */
    public array $period = ['flexible' => false, 'from' => '', 'to' => ''];

    /** @var array<string, mixed> */
    public array $team = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    public string $motto = '';

    public string $visibility = 'public';

    /** Whether the owner's own e-mail address is the public contact. */
    public bool $contact = false;

    /** @var array<int, array<string, mixed>> current place-search suggestions */
    public array $locationOptions = [];

    /**
     * The form keys currently on screen — the only ones validated.
     *
     * @return list<string>
     */
    abstract protected function visibleFields(): array;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function fieldRules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'max:40'],
            'goal' => ['bail', 'required', 'string', 'max:150'],
            'description' => [new RichTextRule(required: true)],
            'location.searchTerm' => [Rule::requiredIf(fn (): bool => ! $this->location['remote'])],
            'location.data' => [Rule::requiredIf(fn (): bool => ! $this->location['remote'] && trim($this->location['searchTerm']) !== '')],
            'period.from' => [new PeriodDateRule(fn (): array => $this->period, 'from')],
            'period.to' => [new PeriodDateRule(fn (): array => $this->period, 'to')],
            'team' => [new RichTextRule(required: false)],
            'motto' => ['nullable', 'string', 'max:200'],
            'visibility' => ['required', 'in:private,public'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function fieldMessages(): array
    {
        return [
            'title.required' => 'Gib einen Titel ein',
            'title.max' => 'Nicht mehr als 40 Zeichen',
            'goal.required' => 'Gib ein Ziel ein',
            'goal.max' => 'Nicht mehr als 150 Zeichen',
            'location.searchTerm.required' => 'Gib einen Ort ein',
            'location.data.required' => 'Wähle einen Ort aus der Liste aus',
            'motto.max' => 'Nicht mehr als 200 Zeichen',
        ];
    }

    /**
     * Validates exactly `$keys` (those that are on screen), replacing any
     * previous error for them. Returns whether all of them are valid.
     *
     * @param  list<string>  $keys
     * @param  bool  $onScreenOnly  drop keys that are not currently on screen (false for the wizard's final, defensive re-validation)
     */
    protected function validateFields(array $keys, bool $onScreenOnly = true): bool
    {
        if ($onScreenOnly) {
            $keys = array_values(array_intersect($keys, $this->visibleFields()));
        }

        $rules = Arr::only($this->fieldRules(), $keys);

        $this->resetErrorBag($keys);

        $validator = Validator::make([
            'title' => $this->title,
            'goal' => $this->goal,
            'description' => $this->description,
            'location' => $this->location,
            'period' => $this->period,
            'team' => $this->team,
            'motto' => $this->motto,
            'visibility' => $this->visibility,
        ], $rules, $this->fieldMessages());

        if ($validator->passes()) {
            return true;
        }

        foreach ($validator->errors()->messages() as $key => $messages) {
            $this->addError($key, $messages[0]);
        }

        return false;
    }

    /**
     * A field lost focus: show its error, if any (Formik `touched` + `errors`).
     * The location's "pick a suggestion" error belongs to the search term's
     * blur, and an edited start date re-checks a filled-in end date.
     */
    public function blurred(string $field): void
    {
        $this->validateFields(match ($field) {
            'location.searchTerm' => ['location.searchTerm', 'location.data'],
            'period.from' => $this->period['to'] !== '' ? ['period.from', 'period.to'] : ['period.from'],
            default => [$field],
        });
    }

    public function updatedLocation(): void
    {
        $this->location = [
            'remote' => (bool) ($this->location['remote'] ?? false),
            'searchTerm' => mb_substr(is_string($this->location['searchTerm'] ?? null) ? $this->location['searchTerm'] : '', 0, 200),
            'data' => LocationSearch::sanitize($this->location['data'] ?? []),
        ];
    }

    /**
     * Typing in the place search discards any earlier selection and asks the
     * provider for new suggestions (the field itself debounces 500ms); a
     * provider failure keeps the previous suggestions, as historically.
     */
    public function updatedLocationSearchTerm(string $term): void
    {
        $this->location['data'] = [];
        $term = trim(mb_substr($term, 0, 100));

        if ($term === '') {
            $this->locationOptions = [];

            return;
        }

        $this->locationOptions = app(LocationSearch::class)->find($term) ?? $this->locationOptions;
    }

    public function updatedPeriod(): void
    {
        $this->period = [
            'flexible' => (bool) ($this->period['flexible'] ?? false),
            'from' => mb_substr(is_string($this->period['from'] ?? null) ? $this->period['from'] : '', 0, 10),
            'to' => mb_substr(is_string($this->period['to'] ?? null) ? $this->period['to'] : '', 0, 10),
        ];
    }

    public function selectLocation(string $key): void
    {
        $option = collect($this->locationOptions)->firstWhere('key', $key);

        if ($option === null) {
            return;
        }

        $this->location['searchTerm'] = (string) $option['value'];
        $this->location['data'] = LocationSearch::sanitize(Arr::except($option, ['value']));
        $this->locationOptions = [];
        $this->resetErrorBag(['location.searchTerm', 'location.data']);
    }

    public function clearLocation(): void
    {
        $this->location['searchTerm'] = '';
        $this->location['data'] = [];
        $this->locationOptions = [];
    }

    /**
     * The persisted `description` step fields (`serializeProjectDescription`):
     * the rich-text documents alongside their plain-text projections, the
     * location/period cleared according to their remote/flexible switches, and
     * period dates converted to their stored ISO-8601 form.
     *
     * @return array<string, mixed>
     */
    protected function descriptionAttributes(): array
    {
        $description = RichText::normalize($this->description);
        $team = RichText::normalize($this->team);
        $remote = $this->location['remote'];
        $flexible = $this->period['flexible'];

        return [
            'title' => $this->title,
            'goal' => $this->goal,
            'description_template' => $description,
            'description' => RichText::toPlainText($description),
            'location' => [
                'remote' => $remote,
                'searchTerm' => $remote ? '' : $this->location['searchTerm'],
                'data' => $remote ? (object) [] : (object) LocationSearch::sanitize($this->location['data']),
            ],
            'period' => [
                'flexible' => $flexible,
                'from' => $flexible ? '' : ProjectDate::toStored($this->period['from']),
                'to' => $flexible ? '' : ProjectDate::toStored($this->period['to']),
            ],
            'team_template' => $team,
            'team' => RichText::toPlainText($team),
            'motto' => $this->motto,
        ];
    }

    /**
     * The persisted `settings` fields (`serializeProjectSettings`): visibility,
     * and the owner's own e-mail address or the Nusszopf address as contact.
     *
     * @return array<string, mixed>
     */
    protected function settingsAttributes(User $user): array
    {
        return [
            'visibility' => $this->visibility,
            'contact' => $this->contact ? $user->email : Project::NUSSZOPF_CONTACT,
        ];
    }

    /**
     * Loads the form state from a stored project. Projects created by the
     * first slice's single form predate the structured fields: a missing
     * document is rebuilt from the plain text, a missing location/period reads
     * as location-independent/flexible (what the detail page shows for them).
     */
    protected function fillFromProject(Project $project, User $user): void
    {
        $this->title = $project->title;
        $this->goal = $project->goal;
        $this->description = RichText::normalize($project->description_template ?? RichText::fromPlainText($project->description));
        $this->location = [
            'remote' => (bool) ($project->location['remote'] ?? true),
            'searchTerm' => (string) ($project->location['searchTerm'] ?? ''),
            'data' => LocationSearch::sanitize($project->location['data'] ?? []),
        ];
        $this->period = [
            'flexible' => (bool) ($project->period['flexible'] ?? true),
            'from' => ProjectDate::toDisplay($project->period['from'] ?? null),
            'to' => ProjectDate::toDisplay($project->period['to'] ?? null),
        ];
        $this->team = RichText::normalize($project->team_template ?? RichText::fromPlainText((string) $project->team));
        $this->motto = (string) $project->motto;
        $this->visibility = $project->visibility;
        $this->contact = $project->contact === $user->email;
        $this->locationOptions = [];
        $this->resetErrorBag();
    }
}
