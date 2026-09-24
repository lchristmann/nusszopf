<?php

use App\Models\Project;
use App\Models\User;

/**
 * `components/Page/Page.js` (next-seo) and `pages/_document.js`
 * (docs/rewrite/tenth-slice.md, decisions 9, 10 and 12; BUG-036, BUG-037).
 */
beforeEach(function () {
    config(['app.url' => 'https://nuss.example']);
});

it('renders the default title, description and Open Graph tags', function () {
    $this->get('/search')
        ->assertSee('<title>Nusszopf – Netzwerk für gemeinsame Ideen und Projekte</title>', false)
        ->assertSee('<meta name="description" content="Setze mehr Ideen mit passenden Mitstreiter:innen, Ressourcen und Wissen um. Mach mit bei spannenden Projekten und werde Teil der Nusszopfgemeinschaft!" />', false)
        ->assertSee('<link rel="canonical" href="https://nuss.example/search" />', false)
        ->assertSee('<meta property="og:url" content="https://nuss.example/search" />', false)
        ->assertSee('<meta property="og:locale" content="de_DE" />', false)
        ->assertSee('<meta property="og:type" content="website" />', false)
        ->assertSee('<meta property="og:image" content="https://nuss.example/images/og-image.png" />', false)
        ->assertSee('<meta property="og:image:width" content="1648" />', false)
        ->assertSee('<meta property="og:image:height" content="863" />', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image" />', false);
});

it('sends the historical viewport, which keeps phones from zooming out below 1 (P-5, DEV-02)', function () {
    $this->get('/search')->assertSee('<meta name="viewport" content="width=device-width,minimum-scale=1,initial-scale=1" />', false);
});

it('uses the bare domain as the canonical URL of Home', function () {
    $this->get('/')->assertSee('<link rel="canonical" href="https://nuss.example" />', false);
});

it('keeps the query string in the canonical URL, as next-seo did with asPath', function () {
    $this->get('/search?q=garten')->assertSee('<link rel="canonical" href="https://nuss.example/search?q=garten" />', false);
});

it('drops the placeholder Twitter handles and the original operator\'s tags (BUG-036)', function () {
    $this->get('/')
        ->assertDontSee('@handle')
        ->assertDontSee('@site')
        ->assertDontSee('google-site-verification', false)
        ->assertDontSee('msvalidate.01', false)
        ->assertDontSee('yandex-verification', false)
        ->assertDontSee('visitor-analytics', false);
});

it('titles a project page with its title and goal, truncated like lodash', function () {
    $project = Project::factory()->public()->create([
        'title' => trim(str_repeat('Gartenprojekt ', 5)),
        'goal' => trim(str_repeat('Wir bauen einen Garten. ', 8)),
    ]);

    $title = trim(str_repeat('Gartenprojekt ', 5));
    expect(mb_strlen($title))->toBe(69);

    $this->get(route('projects.show', $project))
        ->assertSee('<title>'.mb_substr($title, 0, 57).'...</title>', false)
        ->assertSee('<meta property="og:title" content="'.$title.'" />', false)
        ->assertSee('<meta name="description" content="'.mb_substr(trim(str_repeat('Wir bauen einen Garten. ', 8)), 0, 147).'..." />', false);
});

it('does not truncate a title of exactly 60 characters', function () {
    $project = Project::factory()->public()->create(['title' => str_repeat('a', 60)]);

    $this->get(route('projects.show', $project))->assertSee('<title>'.str_repeat('a', 60).'</title>', false);
});

it('marks the account pages noindex even in production', function (string $route) {
    $this->app['env'] = 'production';
    $user = User::factory()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route($route, match ($route) {
            'projects.edit' => $project,
            'projects.create' => ['step' => 0],
            default => [],
        }))
        ->assertSee('<meta name="robots" content="noindex,nofollow" />', false);
})->with(['profile', 'projects.mine', 'projects.create', 'projects.edit']);

it('lets public pages be indexed in production only', function () {
    $this->get('/')->assertSee('<meta name="robots" content="noindex,nofollow" />', false);

    $this->app['env'] = 'production';
    $this->get('/')->assertSee('<meta name="robots" content="index,follow" />', false);
});

it('links the historical favicons, and the manifest names icons that exist (BUG-037)', function () {
    $this->get('/')
        ->assertSee('<link href="/favicons/site.webmanifest" rel="manifest" />', false)
        ->assertSee('<link href="/favicons/apple-touch-icon.png" rel="apple-touch-icon" sizes="180x180" />', false);

    $manifest = json_decode(file_get_contents(public_path('favicons/site.webmanifest')), true);
    foreach ($manifest['icons'] as $icon) {
        expect(public_path(ltrim($icon['src'], '/')))->toBeFile();
    }
    preg_match('/src="([^"]+)"/', file_get_contents(public_path('favicons/browserconfig.xml')), $tile);
    expect(public_path(ltrim($tile[1], '/')))->toBeFile()
        ->and(public_path('images/og-image.png'))->toBeFile();
});

it('marks a project page noindex, as historically, even though the sitemap lists it (BUG-038)', function () {
    $project = Project::factory()->public()->create();
    $this->app['env'] = 'production';

    $this->get(route('projects.show', $project))->assertSee('<meta name="robots" content="noindex,nofollow" />', false);
    $this->get('/search')->assertSee('<meta name="robots" content="index,follow" />', false);
});
