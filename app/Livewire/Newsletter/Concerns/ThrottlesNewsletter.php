<?php

namespace App\Livewire\Newsletter\Concerns;

use Illuminate\Support\Facades\RateLimiter;

/**
 * The historical `rateLimiter` in front of `/api/newsletter`
 * (`runMiddleware.function.js`): 10 requests per 15 minutes per IP, one
 * budget shared by every newsletter action (Preserve). The mail-link pages are
 * not counted: historically their calls came from the Next.js server's own
 * address, not the visitor's, and an HMAC-signed token cannot be guessed.
 */
trait ThrottlesNewsletter
{
    /** False (and nothing counted) once the budget is used up. */
    protected function attemptNewsletterAction(): bool
    {
        $key = 'newsletter:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 10)) {
            return false;
        }

        RateLimiter::hit($key, decaySeconds: 900);

        return true;
    }
}
