<?php

use App\Models\User;

/**
 * Home — `pages/index.js` and `containers/home/*`: the historical structure and design, with the copy
 * describing the current Nusszopf (docs/design/screen-specs.md, "Home"; decision A-5;
 * docs/rewrite/intentional-changes.md, "Home describes the current Nusszopf").
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

it('renders the sections in order, in the historical colours', function () {
    config(['nusszopf.demo' => true]);

    $this->get('/')->assertSeeInOrder([
        'class="px-6 sm:px-16 lg:px-24 xl:px-32 bg-steel-50" data-test="home-header"',
        'bg-livid-300 text-livid-800',
        'bg-yellow-250 sm:pt-16 sm:pb-18" data-test="home-how-to"',
        'bg-white sm:pt-16 sm:pb-18" data-test="home-audiences"',
        'bg-turquoise-300 sm:pt-16',
        'bg-red-300 sm:pt-16',
        'bg-pink-200 sm:pt-16',
        'bg-blue-300 sm:pt-16 sm:pb-18 xl:pt-18 xl:pb-20" id="newsletter"',
    ], false);
});

it('reproduces the header copy and no longer announces a rework', function () {
    $this->get('/')
        ->assertSeeInOrder([
            'title="&lt;3 Nusszopf" aria-label="Nusszopf"',
            'Netzwerk für gemeinsame Ideen und Projekte',
            'Hast Du auch ständig tolle Ideen, die Du verwirklichen möchtest? Hier findest Du die perfekten Zutaten für zopfige Ideenumsetzungen!',
        ], false)
        ->assertDontSee('Kneten')
        ->assertDontSee('veralteten')
        ->assertDontSee('Testzopf')
        ->assertDontSee('Alte Version');
});

it('shows the four how-to steps and the one search CTA, but no create CTA or carousel', function () {
    $this->get('/')
        ->assertSeeInOrder(['How To Nusszopf', 'Idee!', 'Projekt', 'Gesuche', 'Umsetzung', 'Projekte entdecken'])
        ->assertDontSee('How To Nusszopf (Alte Version)')
        ->assertSee('href="'.route('search').'"', false)
        ->assertSee('data-test="route_search-page"', false)
        ->assertDontSee('route_create-project-page', false)
        ->assertDontSee('Projekt starten')
        ->assertDontSee('Frisch gebackene Nusszopf Projekte');
});

it('numbers all four how-to steps, with no icon standing in for one', function () {
    $html = $this->get('/')->getContent();

    expect(substr_count($html, 'flex items-center justify-center w-12 h-12 border-2 rounded-full border-steel-700'))->toBe(4);
    $this->get('/')->assertSeeInOrder(['>1<', '>2<', '>3<', '>4<'], false);
});

it('explains who the software is for, from what it does', function () {
    $this->get('/')->assertSeeInOrder([
        'Wofür ist der Nusszopf gut?',
        'Communities &amp; Initiativen', 'Bildungseinrichtungen', 'Unternehmen &amp; Teams', 'Vereine &amp; Organisationen',
        'Alles läuft auf Deinem eigenen Server.',
    ], false);

    $this->get('/')
        ->assertSeeInOrder(['Vereine &amp; Organisationen', 'Mitglieder bringen Projektideen ein'], false)
        ->assertDontSee('Impressum- und Datenschutztexte')
        ->assertDontSee('Rechtstexte');
});

it('reports the Zukunftspreis as verified by the city\'s own release, with links to it', function () {
    $city = 'https://www.augsburg.de/aktuelles-aus-der-stadt/detail/augsburger-zukunftspreise-2021-verliehen';

    $this->get('/')->assertSeeInOrder([
        'Augsburger Zukunftspreis 2021',
        'Die SchülerInnenjury hat den Nusszopf',
        'Zukunftspreis der Stadt Augsburg geehrt',
        'Die Preisverleihung fand am Montag, den 16. Mai 2022, statt.',
        'href="'.$city.'"',
        'href="https://www.hallo-augsburg.de/zukunftspreis-klimacamp-augsburg-stadt-augsburg-zeichnet-klimacamp-mit-dem-zukunftspreis-aus_DhJ"',
        'title="Zum Augsburger Zukunftspreis"',
    ], false)
        ->assertDontSee('wir sind fest am Daumen drücken')
        ->assertDontSee('eingereicht');
});

it('keeps the Zopfstarke Mitstreiter:innen section without any funding or sponsor story', function () {
    $this->get('/')
        ->assertSeeInOrder([
            'Zopfstarke Mitstreiter:innen',
            'Betreibe Deinen eigenen Nusszopf!', 'Mach mit!', 'Gib uns Feedback!', 'Feedback senden',
        ], false)
        ->assertDontSee('Werde Fördermitglied!')
        ->assertDontSee('Werde Partner:in!')
        ->assertDontSee('steadyhq.com')
        ->assertDontSee('Wir werden unterstützt von')
        ->assertDontSee('Zu Vercel', false)
        ->assertDontSee('Zu Auth0', false)
        ->assertDontSee('Zu Sanity', false)
        ->assertDontSee('Zu LocationIQ', false);
});

it('links the contribution and installation guides of the configured source repository', function () {
    config(['nusszopf.source_url' => 'https://git.example/fork/nusszopf/']);

    $this->get('/')
        ->assertSee('href="https://git.example/fork/nusszopf/blob/main/CONTRIBUTING.md" target="_blank" rel="noopener noreferrer"', false)
        ->assertSee('href="https://git.example/fork/nusszopf/blob/main/docs/handbuch/installation.md" target="_blank" rel="noopener noreferrer"', false);
});

it('points the feedback button at the operator mailbox', function () {
    config(['nusszopf.contact_email' => 'team@nuss.example']);

    $this->get('/')
        ->assertSee('href="mailto:team@nuss.example?subject=Nussiges Feedback"', false)
        ->assertDontSee('Nussige Partnerschaft')
        ->assertDontSee('mail@nusszopf.org');
});

it('opens external links in a new tab and mail links in place', function () {
    $html = $this->get('/')->getContent();

    expect(preg_match_all('/<a[^>]+href="mailto:[^"]*"[^>]*target=/', $html))->toBe(0)
        ->and(preg_match('/<a[^>]+href="https:\/\/github\.com\/lchristmann\/nusszopf\/blob\/main\/CONTRIBUTING\.md"[^>]*target="_blank"/', $html))->toBe(1);
});

it('offers the newsletter sign-up as this installation\'s own, not the project\'s', function () {
    $this->get('/')
        ->assertSeeInOrder(['Newsletter für Deine Community', 'aus dieser Community', 'Füge den Absender zu deinen Kontakten hinzu', 'href="'.route('contact.vcard').'"', 'Kontakt speichern', 'data-test="form_newsletter-subscribe"'], false)
        ->assertSeeLivewire('newsletter.subscribe-form')
        ->assertDontSee('Nussiger Newsletter')
        ->assertDontSee('backfrischen');
});

it('replaces the sign-up form on the public demo with the explanation that the project runs no newsletter', function () {
    config(['nusszopf.demo' => true]);

    $this->get('/')
        ->assertSeeInOrder(['Newsletter für Deine Community', 'Der Nusszopf bringt eine Newsletter-Funktion mit', 'Das Nusszopf-Projekt selbst betreibt keinen Newsletter und plant auch keinen'], false)
        ->assertSee('data-test="home-newsletter-demo"', false)
        ->assertDontSee('data-test="form_newsletter-subscribe"', false)
        ->assertDontSeeLivewire('newsletter.subscribe-form');
});

it('shows the demo card above the how-to only in demo mode, for visitors and the demo account', function () {
    $this->get('/')->assertDontSee('data-test="home-demo"', false)->assertDontSee('btn_demo-login_home', false);

    config(['nusszopf.demo' => true]);

    $this->get('/')
        ->assertSeeInOrder(['data-test="home-demo"', 'Probier den Nusszopf aus!', 'action="'.route('demo.login').'"', 'Demo ausprobieren', 'Geführte Tour starten', 'data-test="home-how-to"'], false);

    $this->actingAs(User::factory()->create())->get('/')->assertDontSee('data-test="home-demo"', false);
});

it('links the German Handbuch below the demo actions, not as a third button', function () {
    config(['nusszopf.demo' => true, 'nusszopf.source_url' => 'https://git.example/fork/nusszopf/']);

    $this->get('/')
        ->assertSeeInOrder(['Geführte Tour starten', 'data-test="home-demo-handbook"', 'href="https://git.example/fork/nusszopf/blob/main/docs/handbuch/README.md"', 'Zum Handbuch', 'data-test="home-how-to"'], false);
});
