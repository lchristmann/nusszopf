<?php

namespace App\Livewire\Auth;

use App\Http\Controllers\Auth\GoogleController;
use App\Mail\BlockedAccountMail;
use App\Mail\WelcomeMail;
use App\Models\Lead;
use App\Models\User;
use App\Rules\PasswordPolicy;
use App\Support\Demo;
use App\Support\Newsletter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * The historical combined Login/Register screen (docs/authentication/README.md
 * §2-3) — one screen, tab-switched, not two separate routes.
 */
// The Auth0-hosted apps' own `Page`: `bg-white sm:bg-steel-100`, footer alike.
#[Layout('components.layout', ['mainClass' => 'bg-white sm:bg-steel-100', 'footerBg' => 'bg-white sm:bg-steel-100'])]
class LoginRegister extends Component
{
    /**
     * Register B-12 (docs/rewrite/decisions-register.md): a documented,
     * reasonable default — Auth0's own threshold is unrecoverable.
     */
    private const int MAX_ACCOUNT_ATTEMPTS = 5;

    private const int ACCOUNT_LOCK_DECAY_SECONDS = 900;

    public string $tab = 'login';

    public string $emailOrName = '';

    public string $loginPassword = '';

    public string $username = '';

    public string $email = '';

    public string $registerPassword = '';

    public bool $privacy = false;

    /**
     * "Nussigen Newsletter abonnieren" (inventory item 27). Checked, it
     * requests a *pending* subscription with the same confirmation mail as
     * every other path — decision A-1, BUG-011 (historically the lead was
     * created already confirmed, with no mail).
     */
    public bool $newsletter = false;

    public function mount(string $tab = 'login'): void
    {
        $this->tab = $tab === 'register' ? 'register' : 'login';
    }

    public function login(): void
    {
        $ip = request()->ip();
        $ipKey = 'login:'.$ip;

        if (RateLimiter::tooManyAttempts($ipKey, maxAttempts: 5)) {
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

        // auth-login `handleLogin`: a loading toast once the form is valid; any failure is the generic
        // error toast (`cms.notify.error[0]`), never a field message that would tell the fields apart.
        $this->dispatch('toast', type: 'loading', message: 'Du wirst einloggt.');

        // The address first: a username may itself look like an address (it only has to be free of
        // whitespace), and one query for either would then pick an arbitrary account of the two (P-4, SEC-09).
        $user = User::where('email', $this->emailOrName)->first()
            ?? User::where('name', $this->emailOrName)->first();

        $accountKey = $user ? 'login-account:'.$user->id : null;

        if ($accountKey && RateLimiter::tooManyAttempts($accountKey, maxAttempts: self::MAX_ACCOUNT_ATTEMPTS)) {
            $this->addError('loginPassword', 'Dieser Account ist vorübergehend gesperrt. Wir haben dir eine E-Mail geschickt.');

            return;
        }

        if (! $user || ! $user->password || ! Hash::check($this->loginPassword, $user->password)) {
            RateLimiter::hit($ipKey, decaySeconds: 60);

            if ($user && $accountKey) {
                $attemptsBefore = RateLimiter::attempts($accountKey);
                RateLimiter::hit($accountKey, decaySeconds: self::ACCOUNT_LOCK_DECAY_SECONDS);

                // B-7 (register): send the "blocked" notice only to the
                // account owner, and only once — right as the lock trips,
                // not on every further attempt while it holds.
                if ($attemptsBefore + 1 === self::MAX_ACCOUNT_ATTEMPTS) {
                    $this->sendBlockedAccountNotice($user, $ip);
                }
            }

            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        RateLimiter::clear($ipKey);

        if ($accountKey) {
            RateLimiter::clear($accountKey);
        }

        Auth::login($user);
        session()->regenerate();

        // BUG-015 (docs/rewrite/bugs.md): preserved as historically observed
        // — every login lands on /user/projects, regardless of referrer.
        $this->redirectRoute('projects.mine', navigate: false);
    }

    public function register(): void
    {
        // A public demo must not collect real addresses (docs/deployment/demo.md); the form is not shown either.
        if (Demo::enabled()) {
            $this->dispatch('toast', type: 'error', message: 'In der Demo ist die Registrierung abgeschaltet.');

            return;
        }

        // Replaces Auth0's invisible bot-detection captcha (docs/authentication/README.md §7,
        // docs/rewrite/intentional-changes.md): each IP may create `nusszopf.register_limit`
        // (10) accounts per 15 minutes — the historical budget of the other public forms.
        $ipKey = 'register:'.request()->ip();

        if (RateLimiter::tooManyAttempts($ipKey, maxAttempts: config('nusszopf.register_limit'))) {
            $this->addError('username', 'Zu viele Versuche. Bitte warte kurz.');

            return;
        }

        $this->validate([
            // 'bail': the historical Yup schema shows only the first
            // failing rule at a time (verified against SignUpForm.js in
            // this pass) — without it, a username failing multiple rules
            // at once would show every message simultaneously.
            'username' => [
                'bail',
                'required',
                'string',
                'max:15',
                'regex:/^\S*$/',
            ],
            'email' => ['required', 'email'],
            'registerPassword' => ['required', new PasswordPolicy],
            'privacy' => ['accepted'],
        ], [
            'username.required' => 'Gib einen Username ein',
            'username.regex' => 'Keine Leerzeichen',
            'username.max' => 'Maximal 15 Zeichen',
            'email.required' => 'Gib eine E-Mail-Adresse ein',
            'email.email' => 'Keine valide E-Mail-Adresse',
            'privacy.accepted' => 'Stimme den Datenschutzbestimmungen zu',
        ]);

        // auth-login `handleSignup`: the loading toast, then Auth0's answer. A taken username was its one
        // distinguished error (HTTP 400, `cms.notify.error[1]`); anything else, a taken address included,
        // the generic one.
        $this->dispatch('toast', type: 'loading', message: 'Du wirst registriert und eingeloggt.');

        if (User::where('name', $this->username)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Der Username existiert leider schon.');

            return;
        }

        if (User::where('email', $this->email)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        RateLimiter::hit($ipKey, decaySeconds: 900);

        $user = User::create([
            'name' => $this->username,
            'email' => $this->email,
            'password' => $this->registerPassword,
        ]);

        // Welcome (docs/email/README.md item 1) sends unconditionally, exactly
        // as historically. The verification e-mail (decision A-3) is new —
        // login/registration are never gated by it.
        Mail::send(new WelcomeMail($user));
        $user->sendEmailVerificationNotification();

        // Fail-open, as historically: the Auth0 rule swallowed any newsletter
        // error so registration itself could never fail because of it.
        if ($this->newsletter) {
            try {
                Newsletter::subscribe($user->email, $user->name, Lead::SOURCE_REGISTRATION);
            } catch (Throwable $e) {
                report($e);
            }
        }

        Auth::login($user);
        session()->regenerate();

        $this->redirectRoute('projects.mine', navigate: false);
    }

    public function demo(): bool
    {
        return Demo::enabled();
    }

    public function googleConfigured(): bool
    {
        return GoogleController::configured();
    }

    private function sendBlockedAccountNotice(User $user, string $ip): void
    {
        $url = URL::temporarySignedRoute(
            'login.unblock',
            now()->addDay(),
            ['ip' => $ip, 'user' => $user->id],
        );

        Mail::send(new BlockedAccountMail($user, $ip, $url));
    }

    public function render(): View
    {
        return view('livewire.auth.login-register', [
            'googleConfigured' => $this->googleConfigured(),
        ]);
    }
}
