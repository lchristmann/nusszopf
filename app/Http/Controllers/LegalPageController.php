<?php

namespace App\Http\Controllers;

use App\Support\LegalText;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * `pages/{legalNotice,legalPolicy,privacy}.js`: the operator's text
 * (decision A-4). The header's back chevron goes home, except that Privacy
 * reached with `?back` goes back in history (`goBackUri: 'back'`).
 */
class LegalPageController extends Controller
{
    public function notice(): View
    {
        return $this->page('notice', '/');
    }

    public function policy(): View
    {
        return $this->page('policy', '/');
    }

    public function privacy(Request $request): View
    {
        return $this->page('privacy', $request->has('back') ? 'back' : '/');
    }

    private function page(string $page, string $goBackUri): View
    {
        return view('legal.page', [
            'heading' => LegalText::PAGES[$page][1],
            'html' => LegalText::html($page),
            'goBackUri' => $goBackUri,
        ]);
    }
}
