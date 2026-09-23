<?php

use App\Models\User;

/**
 * Home — `pages/index.js` and `containers/home/*` with the CMS copy verbatim
 * (docs/design/screen-specs.md, "Home"; decision A-5;
 * docs/rewrite/tenth-slice.md).
 */
it('renders Home at / for visitors and signed-in users alike', function () {
    $this->get('/')->assertOk()->assertSee('Netzwerk für gemeinsame Ideen und Projekte');

    $this->actingAs(User::factory()->create())->get('/')->assertOk()->assertSee('data-test="home-header"', false);
});

it('has no nav header, the classy footer and steel-700 text', function () {
    $this->get('/')
        ->assertDontSee('data-test="btn_burger_nav-header"', false)
        ->assertSee('<main class="flex flex-col flex-1 text-steel-700">', false)
        ->assertSeeInOrder(['<footer', 'bg-steel-200', 'href="'.route('legal.notice').'"', 'Impressum', 'href="'.route('privacy').'"', 'Datenschutz', 'href="'.route('legal.policy').'"', 'Rechtliches', 'href="https://www.instagram.com/nuss.zopf"'], false)
        ->assertDontSee('Powered by Vercel')
        ->assertDontSee('vercel.com?utm_source=nusszopf&amp;utm_campaign=oss" target="_blank" rel="noopener noreferrer" title="Zu Vercel" aria-label="Zu Vercel" class="flex-shrink-0', false);
});

it('renders the six sections in the historical order and colours', function () {
    $this->get('/')->assertSeeInOrder([
        'class="px-6 sm:px-16 lg:px-24 xl:px-32 bg-steel-50" data-test="home-header"',
        'bg-livid-300 text-livid-800',
        'bg-yellow-250 sm:pt-16 sm:pb-18" data-test="home-how-to"',
        'bg-turquoise-300 sm:pt-16',
        'bg-red-300 sm:pt-16',
        'bg-pink-200 sm:pt-16',
        'bg-blue-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" id="newsletter"',
    ], false);
});

it('reproduces the header copy and the "Wir sind am Kneten" card verbatim', function () {
    $this->get('/')->assertSeeInOrder([
        'title="&lt;3 Nusszopf" aria-label="Nusszopf"',
        'Netzwerk für gemeinsame Ideen und Projekte',
        'Hast Du auch ständig tolle Ideen, die Du verwirklichen möchtest? Hier findest Du die perfekten Zutaten für zopfige Ideenumsetzungen!',
        'Wir sind am Kneten: Der Nusszopf wird grundlegend überarbeitet!',
        'Hier findet ihr aktuell den veralteten ersten Prototyp des Netzwerks.',
        '<ol class="pl-3 list-decimal my-3 lg:w-4/5">',
        'Den aktuellen Nusszopf sorgfältig mit Open Source Prinzipien zu einem offenen, mitgestaltbaren und dezentralen Konzept vermengen.',
        'Das Konzept mit einer bereits bestehenden Community testen und nach deren Bedarfen verfeinern.',
        'Testzopf backen. Genau analysieren und gegebenenfalls das Rezept anpassen.',
        'Nach erfolgreicher Verköstigung: Rezept veröffentlichen und alle können den Nusszopf nach eigenem Geschmack und Bedarf nachbacken!',
        'Wie das alles funktionieren kann?',
    ], false);
});

it('shows the four how-to steps and the one search CTA, but no create CTA or carousel', function () {
    $this->get('/')
        ->assertSeeInOrder(['How To Nusszopf (Alte Version)', 'Idee!', 'Projekt', 'Gesuche', 'Umsetzung', 'Alte Version entdecken'])
        ->assertSee('href="'.route('search').'"', false)
        ->assertSee('data-test="route_search-page"', false)
        ->assertDontSee('route_create-project-page', false)
        ->assertDontSee('Projekt starten')
        ->assertDontSee('Frisch gebackene Nusszopf Projekte');
});

it('reproduces About, Contest and Fellows verbatim', function () {
    $this->get('/')->assertSeeInOrder([
        'Über den Nusszopf', 'Die Nussvision', 'Unsere Werte', 'Über uns',
        'Gestartet wurde das Nusszopfprojekt im August 2019 von Meli und Micha',
        'Augsburger Zukunftspreis 2021',
        'Die Preisverleihung findet statt am Montag, den 16.05.22, wir sind fest am Daumen drücken!',
        'Mehr Informationen:', 'augsburg.de/zukunftspreis', 'title="Zum Augsburger Zukunftspreis"',
        'Zopfstarke Mitstreiter:innen', 'Wir werden unterstützt von:',
        'title="Zu Vercel"', 'title="Zu Auth0"', 'title="Zu Sanity"', 'title="Zu LocationIQ"',
        'Werde Fördermitglied!', 'Herzens&shy;projekt', 'Mehr erfahren',
        'Werde Partner:in!', 'Partner:in werden',
        'Gib uns Feedback!', 'Feedback senden',
    ], false);
});

it('points the Fellows mail buttons at the operator mailbox', function () {
    config(['nusszopf.contact_email' => 'team@nuss.example']);

    $this->get('/')
        ->assertSee('href="mailto:team@nuss.example?subject=Nussige Partnerschaft"', false)
        ->assertSee('href="mailto:team@nuss.example?subject=Nussiges Feedback"', false)
        ->assertSee('href="https://steadyhq.com/de/nusszopf" target="_blank" rel="noopener noreferrer"', false)
        ->assertDontSee('mail@nusszopf.org');
});

it('opens external links in a new tab and mail links in place', function () {
    $html = $this->get('/')->getContent();

    expect(preg_match_all('/<a[^>]+href="mailto:[^"]*"[^>]*target=/', $html))->toBe(0)
        ->and(preg_match('/<a[^>]+href="https:\/\/www\.sanity\.io\/"[^>]*target="_blank"/', $html))->toBe(1);
});

it('embeds the newsletter sign-up form and the vCard link', function () {
    $this->get('/')
        ->assertSeeInOrder(['Nussiger Newsletter', 'Wir versorgen euch mit backfrischen Nusszopf&shy;neuigkeiten', 'Füge den Nusszopf zu deinen Kontakten hinzu', 'href="'.route('contact.vcard').'"', 'Kontakt speichern', 'data-test="form_newsletter-subscribe"'], false)
        ->assertSeeLivewire('newsletter.subscribe-form');
});
