<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The historical Auth0 password policy, as mirrored client-side in
 * `auth-login/src/containers/SignUpForm/SignUpForm.js` (docs/authentication/README.md
 * §2) — the authoritative Auth0 dashboard configuration itself is not
 * recoverable from any repository, so this 5-rule approximation is the best
 * available evidence, adopted as the Nusszopf 2 default
 * (docs/rewrite/open-questions.md, "Auth0 password policy authoritative
 * configuration").
 *
 * Checks are evaluated in the same order as the historical Yup schema so
 * that, like the historical form, only the *first* failing rule's message
 * is shown at a time — not every violated rule simultaneously.
 */
class PasswordPolicy implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || mb_strlen($value) < 8) {
            $fail('Mindestens 8 Zeichen');

            return;
        }

        if (! preg_match('/[a-z]/', $value)) {
            $fail('Mindestens ein Kleinbuchstabe');

            return;
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Mindestens ein Großbuchstabe');

            return;
        }

        if (! preg_match('/\d/', $value)) {
            $fail('Mindestens eine Ziffer');

            return;
        }

        if (! preg_match('/[!@#$%^&*]/', $value)) {
            $fail('Mindestens ein Sonderzeichen (!@#$%^&*)');
        }
    }
}
