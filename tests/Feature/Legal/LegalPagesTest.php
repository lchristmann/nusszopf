<?php

use Illuminate\Support\Facades\File;

/**
 * `pages/{legalNotice,legalPolicy,privacy}.js` with the operator's own text
 * (decision A-4; docs/rewrite/tenth-slice.md, decision 6).
 */
beforeEach(function () {
    $this->legalPath = storage_path('framework/testing/legal-'.uniqid());
    File::ensureDirectoryExists($this->legalPath);
    config(['nusszopf.legal_path' => $this->legalPath]);
});

afterEach(function () {
    File::deleteDirectory($this->legalPath);
});

dataset('legal pages', [
    'Impressum' => ['/legalNotice', 'legal-notice.md', 'Impressum'],
    'Rechtliches' => ['/legalPolicy', 'legal-policy.md', 'Rechtliches'],
    'Datenschutz' => ['/privacy', 'privacy.md', 'Datenschutz'],
]);

it('says the text is not configured while the operator has not provided it', function (string $url, string $file, string $heading) {
    $this->get($url)
        ->assertOk()
        ->assertSeeInOrder(['<h1', $heading], false)
        ->assertSee('data-test="legal-not-configured"', false)
        ->assertSee('Dieser Text wurde von den Betreiber:innen dieser Nusszopf-Instanz noch nicht hinterlegt.');
})->with('legal pages');

it('treats an empty file as not configured', function (string $url, string $file) {
    File::put($this->legalPath.'/'.$file, "  \n");

    $this->get($url)->assertSee('data-test="legal-not-configured"', false);
})->with('legal pages');

it('renders the operator\'s Markdown under the historical heading', function (string $url, string $file, string $heading) {
    File::put($this->legalPath.'/'.$file, "## Kontakt\n\nMusterweg 1  \n12345 Musterstadt\n\n- eins\n- zwei\n\n[Beispiel](https://example.org)\n");

    $this->get($url)
        ->assertOk()
        ->assertSeeInOrder([$heading, '<h2>Kontakt</h2>', 'Musterweg 1<br />', '<li>eins</li>', '<a href="https://example.org">Beispiel</a>'], false)
        ->assertDontSee('data-test="legal-not-configured"', false);
})->with('legal pages');

it('escapes raw HTML and drops unsafe links in the operator\'s file', function () {
    File::put($this->legalPath.'/privacy.md', "<script>alert(1)</script>\n\n[x](javascript:alert(1))\n");

    $this->get('/privacy')
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('javascript:alert', false);
});

it('uses the steel page and footer colours of the historical pages', function (string $url) {
    $this->get($url)
        ->assertSee('bg-steel-200 text-steel-800', false)
        ->assertSee('<footer class="px-6 sm:px-16 lg:px-24 xl:px-32 bg-steel-200"', false);
})->with('legal pages');

it('sends the header back chevron home from Impressum and Rechtliches', function (string $url) {
    $this->get($url)->assertSee('<a href="/" data-test="btn_go-back_nav-header"', false);
})->with([['/legalNotice'], ['/legalPolicy']]);

it('sends the Privacy back chevron home, or back in history with ?back', function () {
    $this->get('/privacy')
        ->assertSee('<a href="/" data-test="btn_go-back_nav-header"', false)
        ->assertDontSee('history.back()', false);

    $this->get('/privacy?back=history')
        ->assertSee('onclick="history.back(); return false;" data-test="btn_go-back_nav-header"', false);
});

it('links Profile\'s newsletter consent to Privacy with ?back=history, as historically', function () {
    $this->actingAs(App\Models\User::factory()->create())
        ->get(route('profile'))
        ->assertSee('href="'.url('/privacy?back=history').'"', false);
});

it('ships the historical texts only as labelled examples', function (string $file) {
    $markdown = File::get(base_path('docs/deployment/legal-examples/'.$file));
    File::put($this->legalPath.'/'.$file, $markdown);

    expect($markdown)->toStartWith('> **Beispiel, nicht zur Veröffentlichung.**');

    $this->get(match ($file) {
        'legal-notice.md' => '/legalNotice',
        'legal-policy.md' => '/legalPolicy',
        'privacy.md' => '/privacy',
    })->assertOk()->assertSee('Beispiel, nicht zur Veröffentlichung.');
})->with(['legal-notice.md', 'legal-policy.md', 'privacy.md']);

it('names no processor Nusszopf 2 does not use in the Datenschutz example', function () {
    $markdown = File::get(base_path('docs/deployment/legal-examples/privacy.md'));

    expect($markdown)
        ->not->toContain('auth0.com')
        ->not->toContain('sendgrid.com')
        ->not->toContain('visitor-analytics.io')
        ->not->toContain('mit Servern in Europa/Deutschland');
});
