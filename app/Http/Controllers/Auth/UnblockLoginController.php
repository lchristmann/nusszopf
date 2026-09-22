<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The "Das bin ich!" link in `App\Mail\BlockedAccountMail`
 * (`auth0/blocked-account.mjml`, docs/email/README.md item 3): historically
 * unblocks the IP address at Auth0. Here it clears exactly the two
 * `RateLimiter` keys `App\Livewire\Auth\LoginRegister::login()` set for the
 * IP/account pair the e-mail was actually sent about — never a bare "clear
 * every lock" endpoint.
 */
class UnblockLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $ip = (string) $request->query('ip');
        $userId = (string) $request->query('user');

        RateLimiter::clear('login:'.$ip);
        RateLimiter::clear('login-account:'.$userId);

        return redirect()->route('login')->with('toast', ['type' => 'success', 'message' => 'IP-Adresse freigeschaltet. Du kannst dich jetzt wieder einloggen.']);
    }
}
