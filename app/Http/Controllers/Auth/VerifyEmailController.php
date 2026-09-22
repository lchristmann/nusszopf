<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The signed link from `App\Mail\VerifyEmailMail`. No historical route
 * exists (decision A-3) — this only ever marks the address verified, it
 * never signs the visitor in, so a leaked/forwarded link cannot be used to
 * take over the account.
 */
class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id): RedirectResponse
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            abort(404);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if (Auth::id() === $user->id) {
            return redirect()->route('projects.mine')->with('toast', ['type' => 'success', 'message' => 'E-Mail-Adresse bestätigt!']);
        }

        return redirect()->route('login')->with('toast', ['type' => 'success', 'message' => 'E-Mail-Adresse bestätigt! Du kannst dich jetzt einloggen.']);
    }
}
