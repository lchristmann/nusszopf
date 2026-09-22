<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Passwort vergessen" (docs/authentication/README.md §4.1) — historically an
 * inline sub-view of the combined login/register screen; a distinct route
 * here, per `docs/design/screen-specs.md`'s own routing description (no
 * historical 1:1 route to cite, the three-app Auth0 split had no equivalent
 * single-page-app view swap to preserve).
 */
#[Layout('components.layout')]
class ForgotPassword extends Component
{
    public string $email = '';

    public bool $sent = false;

    public function send(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Bitte gib eine E-Mail-Adresse ein',
            'email.email' => 'Bitte gib eine valide E-Mail-Adresse ein',
        ]);

        // Enumeration-safe (master-roadmap Slice 7 tests): the historical
        // screen always showed "E-Mail verschickt!" regardless of whether
        // the address matched an account — `Password::sendResetLink` already
        // sends nothing for an unknown address, and the UI never branches on
        // its return status.
        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
