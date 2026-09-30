<?php

namespace App\Http\Controllers\Demo;

use App\Http\Controllers\Controller;
use App\Support\Demo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Demo ausprobieren": signs the visitor in as the shared demo account with no credentials (docs/handbuch/demo.md).
 * Answers 404 unless demo mode is on, and never replaces the session of someone signed in as a real account.
 */
class DemoLoginController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless(Demo::enabled(), 404);

        $tour = $request->boolean('tour');

        if (Auth::check() && ! Demo::isDemoUser(Auth::user())) {
            return redirect()->route('projects.mine')->with('toast', ['type' => 'error', 'message' => 'Du bist bereits mit einem eigenen Account eingeloggt.']);
        }

        if (! Auth::check()) {
            $demo = Demo::user() ?? Demo::reset();

            Auth::login($demo);
            $request->session()->regenerate();
        }

        return redirect()->route('projects.mine', $tour ? ['tour' => 1] : []);
    }
}
