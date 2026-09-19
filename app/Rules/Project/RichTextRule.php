<?php

namespace App\Rules\Project;

use App\Support\RichText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The historical description/team rich-text validation
 * (`ProjectField.js`/`TeamField.js`): the document may not exceed 6000
 * characters once serialized (the historical test measured
 * `JSON.stringify(value).length`), and — for the description only — it may
 * not be a lone empty paragraph. Implicit, so an empty document is still
 * evaluated rather than skipped.
 */
final class RichTextRule implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(
        private readonly bool $required,
        private readonly string $tooLongMessage = 'Maximale Zeichenlänge erreicht',
        private readonly string $requiredMessage = 'Gib eine Beschreibung ein',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $doc = RichText::normalize($value);

        if (strlen((string) json_encode($doc, JSON_UNESCAPED_UNICODE)) > 6000) {
            $fail($this->tooLongMessage);

            return;
        }

        if ($this->required && RichText::isEmpty($doc)) {
            $fail($this->requiredMessage);
        }
    }
}
