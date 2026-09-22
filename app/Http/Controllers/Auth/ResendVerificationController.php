<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Lets a signed-in user who missed/lost the first verification e-mail
 * (App\Mail\VerifyEmailMail, decision A-3) get another one — referenced from
 * the project wizard/edit "Persönlich" contact validation error, the only
 * place this slice gates on verification.
 */
class ResendVerificationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Bestätigungs-E-Mail wurde erneut gesendet.']);
    }
}
