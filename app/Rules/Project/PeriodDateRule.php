<?php

namespace App\Rules\Project;

use App\Support\ProjectDate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `PeriodField.js`'s `period.from` / `period.to` validation, in the
 * historical order and with the historical copy: required, then the
 * `dd.mm.yyyy` format, then (for `to`) "not before `from`". Nothing applies
 * while the period is flexible — including the ordering test, which
 * historically still fired against the disabled inputs' stale values
 * (BUG-022).
 */
final class PeriodDateRule implements ValidationRule
{
    public bool $implicit = true;

    /**
     * @param  Closure(): array{flexible?: bool, from?: string, to?: string}  $period
     */
    public function __construct(
        private readonly Closure $period,
        private readonly string $field,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $period = ($this->period)();

        if ($period['flexible'] ?? false) {
            return;
        }

        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            $fail($this->field === 'from' ? 'Gib ein Startdatum ein' : 'Gib ein Enddatum ein');

            return;
        }

        if (! ProjectDate::isValid($value)) {
            $fail('Nicht im Format dd.mm.yyyy');

            return;
        }

        if ($this->field === 'to') {
            $from = ProjectDate::parse((string) ($period['from'] ?? ''));

            if ($from !== null && ProjectDate::parse($value)?->lt($from)) {
                $fail('Enddatum vor Startdatum');
            }
        }
    }
}
