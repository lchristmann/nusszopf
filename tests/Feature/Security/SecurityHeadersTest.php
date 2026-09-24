<?php

use App\Http\Middleware\SecurityHeaders;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/**
 * P-4, SEC-06 (docs/release/parity/P-04-security.md): every application response carries the browser-side
 * security headers, and the pages contain nothing the Content-Security-Policy would have to allow inline.
 */
function assertSecurityHeaders(TestResponse $response): void
{
    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy')
        ->assertHeader('Content-Security-Policy', implode('; ', SecurityHeaders::directives()));
}

it('sends the security headers on pages, errors, redirects and the health endpoints', function (string $uri) {
    assertSecurityHeaders($this->get($uri));
})->with([
    'home' => '/',
    'login' => '/login',
    'search' => '/search',
    'not found' => '/does-not-exist',
    'redirect to login' => '/user/profile',
    'liveness' => '/up',
    'health' => '/health',
]);

it('sends them on Livewire updates too', function () {
    $html = $this->get('/login')->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $snapshot);
    preg_match('/data-update-uri="([^"]+)"/', $html, $uri);
    preg_match('/data-csrf="([^"]+)"/', $html, $csrf);

    $response = $this->withHeaders(['X-Livewire' => '1'])->postJson($uri[1], [
        '_token' => $csrf[1],
        'components' => [['snapshot' => html_entity_decode($snapshot[1]), 'updates' => [], 'calls' => []]],
    ]);

    $response->assertOk();
    assertSecurityHeaders($response);
});

it('allows scripts from this origin only, without inline scripts, and no framing by other sites', function () {
    config(['app.url' => 'https://nusszopf.example.org']);
    $policy = collect(SecurityHeaders::directives())->keyBy(fn (string $directive) => strtok($directive, ' '));

    expect($policy['script-src'])->toBe("script-src 'self' 'unsafe-eval'")
        ->and($policy['img-src'])->toBe("img-src 'self' https://nusszopf.example.org data: https://*.googleusercontent.com")
        ->and($policy['object-src'])->toBe("object-src 'none'")
        ->and($policy['frame-ancestors'])->toBe("frame-ancestors 'self'")
        ->and($policy['base-uri'])->toBe("base-uri 'self'");
});

it('renders no inline script and no inline event handler on any page the policy guards', function (string $page) {
    $html = match ($page) {
        'home' => $this->get('/')->getContent(),
        'login' => $this->get('/login')->getContent(),
        'privacy with its back link' => $this->get('/privacy?back')->getContent(),
        'own private project with its banner' => (function () {
            $project = Project::factory()->create(['visibility' => 'private']);

            return $this->actingAs($project->user)->get(route('projects.show', $project))->getContent();
        })(),
        'a flashed toast' => $this->withSession(['toast' => ['type' => 'success', 'message' => 'x']])->get('/')->getContent(),
        'profile' => $this->actingAs(User::factory()->create())->get('/user/profile')->getContent(),
    };

    // Every <script> has a src, and no element carries an on…= attribute (the policy would block both).
    preg_match_all('/<script\b[^>]*>/i', $html, $scripts);
    expect($scripts[0])->not->toBeEmpty();
    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain(' src=');
    }

    expect($html)->not->toMatch('/<[a-z][^>]*\son[a-z]+\s*=/i');
})->with(['home', 'login', 'privacy with its back link', 'own private project with its banner', 'a flashed toast', 'profile']);

it('hands a flashed toast to the page as escaped data, not as a script', function () {
    $html = $this->withSession(['toast' => ['type' => 'success', 'message' => '</script><script>alert(1)</script>']])
        ->get('/')->getContent();

    expect($html)->toContain('data-flash-toast="')
        ->and($html)->not->toContain('<script>alert(1)</script>');
});

it('exposes no download or upload route for the private disk (SEC-07)', function () {
    expect(Route::has('storage.local'))->toBeFalse()
        ->and(Route::has('storage.local.upload'))->toBeFalse();
});
