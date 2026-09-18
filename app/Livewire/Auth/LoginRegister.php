<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Rules\PasswordPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The historical combined Login/Register screen (docs/authentication/README.md
 * §2-3) — one screen, tab-switched, not two separate routes. Registration
 * fields are deliberately scoped to this slice's acceptance criterion #1
 * only (username + email + password + privacy consent) — no newsletter
 * checkbox, no social login, no password reset (docs/rewrite/first-slice.md).
 */
#[Layout('components.layout')]
class LoginRegister extends Component
{
    public string $tab = 'login';

    public string $emailOrName = '';

    public string $loginPassword = '';

    public string $username = '';

    public string $email = '';

    public string $registerPassword = '';

    public bool $privacy = false;

    public function mount(string $tab = 'login'): void
    {
        $this->tab = $tab === 'register' ? 'register' : 'login';
    }

    public function login(): void
    {
        $key = 'login:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $this->addError('emailOrName', 'Zu viele Versuche. Bitte warte kurz.');

            return;
        }

        $this->validate([
            'emailOrName' => ['required', 'string'],
            'loginPassword' => ['required', 'string'],
        ], [
            'emailOrName.required' => 'Bitte gib einen Username oder eine E-Mail-Adresse ein',
            'loginPassword.required' => 'Bitte gib ein Passwort ein',
        ]);

        $user = User::where('email', $this->emailOrName)
            ->orWhere('name', $this->emailOrName)
            ->first();

        if (! $user || ! Hash::check($this->loginPassword, $user->password)) {
            RateLimiter::hit($key, decaySeconds: 60);
            $this->addError('loginPassword', 'Sorry, da lief etwas schief.');

            return;
        }

        RateLimiter::clear($key);

        Auth::login($user);
        session()->regenerate();

        // BUG-015 (docs/rewrite/bugs.md): preserved as historically observed
        // — every login lands on /user/projects, regardless of referrer.
        $this->redirectRoute('projects.mine', navigate: false);
    }

    public function register(): void
    {
        $this->validate([
            'username' => [
                'required',
                'string',
                'max:15',
                'regex:/^\S*$/',
                'unique:users,name',
            ],
            'email' => ['required', 'email', 'unique:users,email'],
            'registerPassword' => ['required', new PasswordPolicy],
            'privacy' => ['accepted'],
        ], [
            'username.required' => 'Gib einen Username ein',
            'username.regex' => 'Keine Leerzeichen',
            'username.max' => 'Maximal 15 Zeichen',
            'username.unique' => 'Der Username existiert leider schon.',
            'email.required' => 'Gib eine E-Mail-Adresse ein',
            'email.email' => 'Keine valide E-Mail-Adresse',
            'email.unique' => 'Diese E-Mail-Adresse wird bereits verwendet.',
            'privacy.accepted' => 'Stimme den Datenschutzbestimmungen zu',
        ]);

        $user = User::create([
            'name' => $this->username,
            'email' => $this->email,
            'password' => $this->registerPassword,
        ]);

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('projects.mine', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.login-register');
    }
}
