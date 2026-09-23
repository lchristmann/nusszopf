<?php

namespace App\Http\Controllers\Newsletter;

use App\Http\Controllers\Controller;
use App\Support\Newsletter;
use Illuminate\Contracts\View\View;

/**
 * `pages/newsletter/subscribe/[token].js` and `pages/newsletter/unsubscribe/[token].js`
 * (docs/design/screen-specs.md): the link from the mail does the work before
 * anything renders. An invalid, expired, tampered or unknown link is a 404 —
 * rendered in place instead of the historical 307 to `/404`
 * (docs/rewrite/intentional-changes.md, "Newsletter mechanics … are replaced").
 */
class ConfirmationController extends Controller
{
    public function subscribe(string $token): View
    {
        $lead = Newsletter::confirm($token) ?? abort(404);

        return view('newsletter.subscribe-confirm', ['email' => $lead->email]);
    }

    public function unsubscribe(string $token): View
    {
        $email = Newsletter::confirmUnsubscribe($token) ?? abort(404);

        return view('newsletter.unsubscribe-confirm', ['email' => $email]);
    }
}
