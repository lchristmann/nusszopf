<?php

namespace App\Livewire\Concerns;

use App\Models\ProjectRequest;
use App\Rules\Project\RichTextRule;
use App\Support\RichText;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The state and validation of the historical request dialog
 * (`EditRequestDialog.js`, `RequestForm/*`), shared by the creation wizard
 * (requests are held in the form until the project is created) and the edit
 * screen's "Gesuche" view (each save is a write). The dialog is a form of its
 * own with three fields; as everywhere in these forms an error shows only for
 * a field that lost focus or that a failed submit touched.
 */
trait ManagesRequestDialog
{
    public bool $requestDialogOpen = false;

    /** The request being edited (an index in the wizard, an id on the edit screen); `null` creates. */
    public ?string $requestKey = null;

    public string $requestTitle = '';

    public string $requestCategory = '';

    /** @var array<string, mixed> ProseMirror document, see App\Support\RichText */
    public array $requestDescription = ['type' => 'doc', 'content' => [['type' => 'paragraph']]];

    /**
     * The stored form values of the request `$key`, or `null` when there is none.
     *
     * @return array{title: string, category: string, description: array<string, mixed>}|null
     */
    abstract protected function requestFormValues(string $key): ?array;

    /**
     * Persists a validated request (`$key` null creates) and reports whether it worked.
     *
     * @param  array{title: string, category: string, description: array<string, mixed>}  $values
     */
    abstract protected function storeRequest(?string $key, array $values): bool;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function requestRules(): array
    {
        return [
            'requestTitle' => ['bail', 'required', 'string', 'max:40'],
            'requestCategory' => ['bail', 'required', Rule::in(ProjectRequest::CATEGORIES)],
            'requestDescription' => [new RichTextRule(required: true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function requestMessages(): array
    {
        return [
            'requestTitle.required' => 'Gib einen Titel ein',
            'requestTitle.max' => 'Maximal 40 Zeichen',
            'requestCategory.required' => 'Wähle eine Kategorie aus',
            'requestCategory.in' => 'Wähle eine Kategorie aus',
        ];
    }

    /**
     * Validates `$fields` of `$values` (keyed like the dialog's properties);
     * returns whether all are valid and shows the errors of those that are not.
     *
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $values
     */
    protected function validateRequestFields(array $fields, array $values): bool
    {
        $this->resetErrorBag($fields);

        $validator = Validator::make($values, Arr::only($this->requestRules(), $fields), $this->requestMessages());

        if ($validator->passes()) {
            return true;
        }

        foreach ($validator->errors()->messages() as $field => $messages) {
            $this->addError($field, $messages[0]);
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestFormState(): array
    {
        return [
            'requestTitle' => $this->requestTitle,
            'requestCategory' => $this->requestCategory,
            'requestDescription' => $this->requestDescription,
        ];
    }

    public function openRequestDialog(): void
    {
        $this->fillRequestForm(null, ['title' => '', 'category' => '', 'description' => RichText::empty()]);
    }

    public function editRequest(string $key): void
    {
        $values = $this->requestFormValues($key);

        if ($values === null) {
            return;
        }

        $this->fillRequestForm($key, $values);
    }

    /**
     * @param  array{title: string, category: string, description: array<string, mixed>}  $values
     */
    private function fillRequestForm(?string $key, array $values): void
    {
        $this->requestKey = $key;
        $this->requestTitle = $values['title'];
        $this->requestCategory = $values['category'];
        $this->requestDescription = $values['description'];
        $this->resetErrorBag(['requestTitle', 'requestCategory', 'requestDescription']);
        $this->requestDialogOpen = true;
    }

    public function closeRequestDialog(): void
    {
        $this->requestDialogOpen = false;
        $this->requestKey = null;
        $this->resetErrorBag(['requestTitle', 'requestCategory', 'requestDescription']);
    }

    /** A dialog field lost focus: show its error, if any. */
    public function blurredRequest(string $field): void
    {
        if (in_array($field, ['requestTitle', 'requestCategory', 'requestDescription'], true)) {
            $this->validateRequestFields([$field], $this->requestFormState());
        }
    }

    public function saveRequest(): void
    {
        if (! $this->validateRequestFields(['requestTitle', 'requestCategory', 'requestDescription'], $this->requestFormState())) {
            return;
        }

        $values = [
            'title' => $this->requestTitle,
            'category' => $this->requestCategory,
            'description' => RichText::normalize($this->requestDescription),
        ];

        // Saving what was loaded unchanged just closes the dialog (`isEqual` in `handleSubmit`).
        $unchanged = $this->requestKey !== null
            && ($stored = $this->requestFormValues($this->requestKey)) !== null
            && $this->sameRequestValues($stored, $values);

        if ($unchanged || $this->storeRequest($this->requestKey, $values)) {
            $this->closeRequestDialog();
        }
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function sameRequestValues(array $a, array $b): bool
    {
        return $a['title'] === $b['title']
            && $a['category'] === $b['category']
            && json_encode(RichText::normalize($a['description'])) === json_encode(RichText::normalize($b['description']));
    }
}
