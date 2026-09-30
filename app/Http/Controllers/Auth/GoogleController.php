<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeMail;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Google login (docs/authentication/README.md §3): the only functional
 * social connection historically (BUG-012, Apple was never wired up). The
 * three-app Auth0 split (`auth-login`'s `webAuth.authorize({ connection:
 * 'google-oauth2' })`) becomes Socialite, per the migration mapping in that
 * document, §7. Hidden entirely when unconfigured (register B-6, same
 * pattern as `LOCATIONIQ_KEY`).
 */
class GoogleController extends Controller
{
    public function redirect(): SymfonyRedirectResponse
    {
        abort_unless(self::configured(), 404);

        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        abort_unless(self::configured(), 404);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => 'Sorry, da lief etwas schief.']);
        }

        // The `email_verified` claim isn't part of Socialite's own User
        // contract (only the standard OIDC-ish getters are) — it lives in
        // the provider's raw attribute bag, only available on the concrete
        // `AbstractUser` every real and faked provider actually returns.
        $emailVerified = $googleUser instanceof AbstractUser && (bool) ($googleUser->getRaw()['email_verified'] ?? false);

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            $user = User::where('email', $googleUser->getEmail())->first();

            if ($user !== null) {
                // Decision A-3 (docs/rewrite/decisions-register.md): link an
                // existing account by e-mail only when Google itself asserts
                // the address is verified — otherwise anyone who merely
                // controls an unverified Google account could hijack a
                // Nusszopf account sharing that address.
                if (! $emailVerified) {
                    return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => 'Sorry, da lief etwas schief.']);
                }

                $user->google_id = $googleUser->getId();
            } else {
                if (! $emailVerified) {
                    return redirect()->route('login')->with('toast', ['type' => 'error', 'message' => 'Sorry, da lief etwas schief.']);
                }

                $user = User::create([
                    'name' => self::uniqueUsername($googleUser),
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'email_verified_at' => now(),
                ]);

                Mail::send(new WelcomeMail($user));
            }
        }

        // BUG-004 fix (docs/rewrite/intentional-changes.md): fills an empty
        // picture only, never overwrites a manual upload.
        if (empty($user->picture) && $googleUser->getAvatar() !== null) {
            $user->picture = $googleUser->getAvatar();
        }

        if ($user->isDirty()) {
            $user->save();
        }

        Auth::login($user);
        session()->regenerate();

        return redirect()->route('projects.mine');
    }

    public static function configured(): bool
    {
        // Google sign-in creates accounts from real addresses, which a public demo must not (docs/handbuch/demo.md).
        return ! Demo::enabled()
            && filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    /**
     * There is no historical evidence for how Auth0 derived a `nickname`
     * from a Google profile (Auth0-platform internal) — a reasonable,
     * documented engineering choice: the Google display name, sanitized to
     * the same rules as manual registration (no whitespace, max 15 chars),
     * with a numeric suffix on collision.
     */
    private static function uniqueUsername(SocialiteUser $googleUser): string
    {
        $base = Str::of((string) ($googleUser->getNickname() ?: $googleUser->getName() ?: 'nussknacker'))
            ->replaceMatches('/\s+/', '')
            ->substr(0, 15)
            ->toString();

        if ($base === '') {
            $base = 'nussknacker';
        }

        $candidate = $base;
        $suffix = 1;

        while (User::where('name', $candidate)->exists()) {
            $suffixString = (string) $suffix;
            $candidate = Str::substr($base, 0, 15 - strlen($suffixString)).$suffixString;
            $suffix++;
        }

        return $candidate;
    }
}
