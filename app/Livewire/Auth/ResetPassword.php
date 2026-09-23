<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * "Neues Passwort erstellen" (`PasswordForm.js`, docs/authentication/README.md
 * §4.2). Historically the separate `auth-password` app read Auth0-injected
 * hidden fields (`auth0-ticket`/`auth0-email`) from the DOM; here the token
 * and e-mail travel as ordinary route/query parameters into Laravel's own
 * password broker — same product behavior (a single password field, the
 * identical strength policy as registration), different plumbing per
 * `docs/authentication/README.md` §7's migration mapping.
 */
// The Auth0-hosted apps' own `Page`: `bg-white sm:bg-steel-100`, footer alike.
#[Layout('components.layout', ['mainClass' => 'bg-white sm:bg-steel-100', 'footerBg' => 'bg-white sm:bg-steel-100'])]
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function save(): void
    {
        $this->validate([
            'password' => ['required', new PasswordPolicy],
        ], [], [
            'password' => 'Passwort',
        ]);

        // auth-password `handleSavePassword`: loading toast, then the success or the generic error toast.
        $this->dispatch('toast', type: 'loading', message: 'Dein Passwort wird geändert.');

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'token' => $this->token],
            function (User $user) {
                $user->forceFill(['password' => $this->password])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        session()->flash('toast', ['type' => 'success', 'message' => 'Passwort geändert! Weiterleitung zum Login.']);
        $this->redirectRoute('login', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
