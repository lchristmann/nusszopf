<?php

namespace App\Support;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

/**
 * Ties a validation message to its field (decision A-7, docs/testing/accessibility.md): the message
 * (`x-input-error for="…"`) gets a stable id, and the control whose `name` or `wire:model` is that field
 * gets `aria-invalid` and `aria-describedby` pointing at it while the field has an error.
 */
class FieldError
{
    public static function id(string $field): string
    {
        return 'error-'.str_replace(['.', '[', ']'], '-', $field);
    }

    /**
     * The field a control stands for: its explicit key, else its `name`, else its `wire:model` target.
     */
    public static function field(ComponentAttributeBag $attributes, ?string $field = null): ?string
    {
        return $field
            ?: $attributes->get('name')
            ?: ($attributes->whereStartsWith('wire:model')->first() ?: null);
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(?string $field, mixed $errors): array
    {
        if ($field === null || ! $errors instanceof ViewErrorBag || ! $errors->has($field)) {
            return [];
        }

        return ['aria-invalid' => 'true', 'aria-describedby' => self::id($field)];
    }
}
