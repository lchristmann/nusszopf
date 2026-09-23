<?php

use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;

/**
 * `components/ErrorPage/ErrorPage.js` for `pages/{404,500,_error}.js`
 * (docs/design/screens.md, "Error screens"; docs/rewrite/tenth-slice.md,
 * decision 7).
 */
beforeEach(function () {
    config(['app.debug' => false, 'nusszopf.contact_email' => 'team@nuss.example']);

    Route::get('/_test/fails', fn () => throw new RuntimeException('boom'));
    Route::get('/_test/abort/{status}', fn (int $status) => abort($status));
});

function assertErrorPage($response, string $status): void
{
    $response
        ->assertSee('data-test="error-page"', false)
        ->assertSee('bg-warning-200', false)
        ->assertSeeInOrder(['<h1', $status.' – Nusszopf verknetet...'], false)
        ->assertSee('Sorry, es ist ein technisches Problem aufgetreten. Falls der Fehler erneut auftritt, melde dich bitte unter')
        ->assertSee('href="mailto:team@nuss.example?subject=Nusszopf verknetet"', false)
        ->assertSee('data-test="btn_home_error-page"', false)
        ->assertSee('href="'.url('/').'"', false)
        ->assertDontSee('data-test="btn_burger_nav-header"', false)
        ->assertDontSee('boom');
}

it('shows the error page for an unknown URL with status 404', function () {
    assertErrorPage($this->get('/gibt-es-nicht')->assertNotFound(), '404');
});

it('shows the error page for an uncaught exception with status 500', function () {
    assertErrorPage($this->get('/_test/fails')->assertStatus(500), '500');
});

it('shows the error page with the status code for any other error', function (int $status) {
    assertErrorPage($this->get('/_test/abort/'.$status)->assertStatus($status), (string) $status);
})->with([403, 419, 429, 503]);

it('uses the warning-coloured footer band', function () {
    $this->get('/gibt-es-nicht')
        ->assertSee('<footer class="px-6 sm:px-16 lg:px-24 xl:px-32 bg-warning-200"', false);
});

it('answers a private project exactly like a missing one', function () {
    $private = Project::factory()->create(['visibility' => 'private']);

    $privateResponse = $this->get(route('projects.show', $private))->assertNotFound();
    // Livewire prints its styles and scripts once per process; a real request starts fresh.
    app(FrontendAssets::class)->hasRenderedScripts = false;
    app(FrontendAssets::class)->hasRenderedStyles = false;
    $missingId = (string) Str::uuid();
    $missingResponse = $this->get('/projects/'.$missingId)->assertNotFound();

    // Identical apart from the canonical URL, which echoes each request's own path.
    expect(str_replace($private->id, '{id}', $privateResponse->getContent()))
        ->toBe(str_replace($missingId, '{id}', $missingResponse->getContent()));
    assertErrorPage($privateResponse, '404');
});

it('shows the error page for an unknown newsletter link', function () {
    assertErrorPage($this->get('/newsletter/subscribe/kaputt')->assertNotFound(), '404');
});
