<?php

use App\Models\Project;
use App\Models\User;
use App\Support\RichText;

/**
 * The Slice 2 content of the project detail screen (`pages/projects/[id].js`):
 * location, period, rich text, team, motto, contact and the owner banner.
 * Visibility/404 behavior stays in ProjectDetailTest.
 */
function richDoc(): array
{
    return ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'Gemeinsam', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => ' ', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'Garten', 'marks' => [['type' => 'underline']]],
            ['type' => 'text', 'text' => ' ansehen', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'http://beispiel.example/seite']]]],
        ]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Beete']]]]]]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Erster']]]]]]],
    ]];
}

it('shows the location as an OpenStreetMap link, the period and the section headings', function () {
    $project = Project::factory()->public()->withLocation('Leipzig')->withPeriod('1.3.2027', '31.5.2027')->create();

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('href="https://www.openstreetmap.org/relation/62649"', false)
        ->assertSee('title="Zu OpenStreetMap"', false)
        ->assertSeeInOrder(['Leipzig', '1.3.2027 - 31.5.2027'])
        ->assertSee('Projektbeschreibung')
        ->assertSee('Projektgesuche')
        ->assertSee('Alles zopfig! Derzeit gibt es keine Gesuche.');
});

it('reads Ortsunabhängig and Flexibler Projektzeitraum for a remote, flexible project', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Ortsunabhängig')
        ->assertSee('Flexibler Projektzeitraum')
        ->assertDontSee('openstreetmap.org');
});

it('renders a first-slice project that has no structured fields', function () {
    $project = Project::factory()->public()->create([
        'description' => 'Nur Klartext',
        'description_template' => null,
        'location' => null,
        'period' => null,
    ]);

    $this->get(route('projects.show', $project))->assertOk()->assertSee('Nur Klartext')->assertSee('Ortsunabhängig')->assertSee('Flexibler Projektzeitraum');
});

it('shows the period exactly as stored, whatever the offset it was stored with (BUG-023)', function () {
    $project = Project::factory()->public()->create([
        'period' => ['flexible' => false, 'from' => '2027-03-01T00:00:00-08:00', 'to' => '2027-05-31T00:00:00+09:00'],
    ]);

    $this->get(route('projects.show', $project))->assertSee('1.3.2027 - 31.5.2027');
});

it('renders the rich-text description with the historical markup', function () {
    $project = Project::factory()->public()->create(['description_template' => richDoc()]);

    $this->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('<span class="font-medium">Gemeinsam</span>', false)
        ->assertSee('<i> </i>', false)
        ->assertSee('<u>Garten</u>', false)
        ->assertSee('<ul class="ml-8 list-disc"><li>Beete</li></ul>', false)
        ->assertSee('<ol class="ml-8 list-decimal"><li>Erster</li></ol>', false)
        ->assertSee('href="https://beispiel.example/seite"', false)
        ->assertSee('rel="noopener noreferrer"', false);
});

it('never renders markup smuggled into a stored document', function () {
    $project = Project::factory()->public()->create(['description_template' => ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => '<script>alert("x")</script>'],
            ['type' => 'text', 'text' => 'klick', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
        ]],
        ['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'raus']]],
    ]]]);

    $response = $this->get(route('projects.show', $project))->assertOk();

    $response->assertDontSee('<script>alert', false)
        ->assertDontSee('href="javascript:', false)
        ->assertSee('href="https://javascript:alert(1)"', false)
        ->assertDontSee('raus');
});

it('shows the team and motto sections only when they have content', function () {
    $without = Project::factory()->public()->create(['team' => null, 'motto' => null]);
    $with = Project::factory()->public()->create([
        'team' => 'Anna und Ben',
        'team_template' => RichText::fromPlainText('Anna und Ben'),
        'motto' => 'Gemeinsam wächst mehr.',
    ]);

    $this->get(route('projects.show', $without))->assertDontSee('Projektteam')->assertDontSee('Projektmotto');
    $this->get(route('projects.show', $with))->assertSee('Projektteam')->assertSee('Anna und Ben')->assertSee('Projektmotto')->assertSee('Gemeinsam wächst mehr.');
});

it('links the contact button to the owner\'s address for a personal contact', function () {
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $project = Project::factory()->for($owner)->public()->create(['contact' => 'owner@example.test']);

    $this->get(route('projects.show', $project))
        ->assertSee('href="mailto:owner@example.test?subject=Nusszopf%20%E2%80%93%20Nussige%20Nachricht"', false)
        ->assertSee('Kontaktieren')
        ->assertSee('Teilen');
});

it('opens the contact dialog for a contact through Nusszopf, and never shows the owner\'s e-mail', function () {
    $owner = User::factory()->create(['email' => 'geheim@example.test']);
    $project = Project::factory()->for($owner)->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    $this->get(route('projects.show', $project))
        ->assertSee('wire:click="openContact" ', false)
        ->assertSee('data-test="btn_contact_project-detail"', false)
        ->assertSee('data-test="contact-dialog"', false)
        ->assertDontSee('geheim@example.test');
});

it('shows the author and the update date', function () {
    $owner = User::factory()->create(['name' => 'gartenfreund']);
    $project = Project::factory()->for($owner)->public()->create();

    $this->get(route('projects.show', $project))->assertSee('gartenfreund')->assertSee('Aktualisiert am '.$project->updated_at->format('j.n.Y'));
});

it('shows the owner banner, in both variants, to the owner', function () {
    $owner = User::factory()->create();
    $public = Project::factory()->for($owner)->public()->create();
    $private = Project::factory()->for($owner)->private()->create();

    $this->actingAs($owner)->get(route('projects.show', $public))
        ->assertSee('So sieht das Projekt für andere Nusszopfer:innen aus.')
        ->assertSee('href="'.route('projects.edit', $public).'"', false);
    $this->actingAs($owner)->get(route('projects.show', $private))->assertSee('Das Projekt ist gerade nur für dich sichtbar!');
});

it('shows no owner banner to visitors or other users', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project))->assertDontSee('So sieht das Projekt für andere Nusszopfer:innen aus.');
});

it('shows no owner banner to another authenticated user', function () {
    $project = Project::factory()->public()->create();

    $this->actingAs(User::factory()->create())->get(route('projects.show', $project))->assertDontSee('So sieht das Projekt für andere Nusszopfer:innen aus.');
});

// --- Visitor counter (BUG-001) and report link -----------------------------------------------

/**
 * `<x-text variant="textXs" class="font-medium">{{ $digit }}</x-text>` is the
 * one place on this screen using exactly this class combination — a direct
 * substring/order assertion on lone digit characters like "0" would be
 * meaningless noise against the rest of the page.
 */
function visitorCounterDigits(string $html): array
{
    preg_match_all('/<p class="nz-text-xs font-medium">([^<]*)<\/p>/', $html, $matches);

    return $matches[1];
}

it('shows the visitor counter as four zero-padded digits, incremented by this visit', function () {
    $project = Project::factory()->public()->create();

    $response = $this->get(route('projects.show', $project))->assertSeeHtml('data-test="visitor-counter_project-detail"');

    expect(visitorCounterDigits($response->getContent()))->toBe(['0', '0', '0', '1']);
});

it('shows the visitor counter capped at "+999 9" past 9999 views', function () {
    $project = Project::factory()->public()->create();
    $project->analytics()->create(['views' => 10000]);

    $response = $this->actingAs($project->user)->get(route('projects.show', $project));

    expect(visitorCounterDigits($response->getContent()))->toBe(['+', '9', '9', '9', '9']);
});

it('shows a "Projekt melden" mailto link carrying the project id in the subject', function () {
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project))
        ->assertSee('Projekt melden')
        ->assertSee('href="mailto:mail@nusszopf.org?subject=Projekt melden (ID: '.$project->id.')"', false);
});
