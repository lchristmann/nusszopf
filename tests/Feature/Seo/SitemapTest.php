<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * `pages/api/sitemap.js` and `public/robots.txt` on this instance's own
 * host (BUG-035).
 */
beforeEach(function () {
    config(['app.url' => 'https://nuss.example']);
});

function sitemapLocs(string $xml): array
{
    return array_map(fn ($url) => (string) $url->loc, iterator_to_array((new SimpleXMLElement($xml))->url, false));
}

it('lists the three static URLs and only public projects', function () {
    $public = Project::factory()->public()->create();
    $private = Project::factory()->create(['visibility' => 'private']);

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'text/xml; charset=utf-8');

    expect(sitemapLocs($response->getContent()))->toBe([
        'https://nuss.example/',
        'https://nuss.example/legalNotice',
        'https://nuss.example/privacy',
        'https://nuss.example/projects/'.$public->id,
    ]);
    $response->assertDontSee($private->id);
});

it('never lists the requesting owner\'s private projects', function () {
    $owner = User::factory()->create();
    $private = Project::factory()->for($owner)->create(['visibility' => 'private']);

    $this->actingAs($owner)->get('/sitemap.xml')->assertOk()->assertDontSee($private->id);
});

it('gives each project its updated_at as lastmod', function () {
    $project = Project::factory()->public()->create();
    $project->forceFill(['updated_at' => '2021-03-04 05:06:07'])->saveQuietly();

    $this->get('/sitemap.xml')->assertSee('<lastmod>'.$project->fresh()->updated_at->utc()->format('Y-m-d\TH:i:s.v\Z').'</lastmod>', false);
});

it('is rate-limited to 10 requests per 15 minutes, as historically', function () {
    foreach (range(1, 10) as $i) {
        $this->get('/sitemap.xml')->assertOk();
    }

    $this->get('/sitemap.xml')->assertStatus(429);
});

it('serves robots.txt naming this instance\'s sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertContent("User-agent: *\nSitemap: https://nuss.example/sitemap.xml\n");
});

it('keeps its own budget, apart from other throttled routes', function () {
    foreach (range(1, 10) as $i) {
        $this->get('/sitemap.xml')->assertOk();
    }

    expect(RateLimiter::attempts('sitemap'.sha1('|127.0.0.1')))->toBe(10)
        ->and(RateLimiter::attempts(sha1('|127.0.0.1')))->toBe(0);
});
