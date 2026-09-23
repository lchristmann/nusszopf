<?php

use App\Livewire\Newsletter\SubscribeForm;
use App\Mail\WelcomeMail;
use App\Models\Project;
use App\Models\User;
use App\Support\Operator;

/**
 * Decision A-5 (slice 10): the operator mailbox and identity are this
 * instance's configuration, not nusszopf.org's literals
 * (docs/rewrite/tenth-slice.md, decisions 3 and 4).
 */
beforeEach(function () {
    config([
        'nusszopf.contact_email' => 'team@nuss.example',
        'mail.from.address' => 'noreply@nuss.example',
        'app.url' => 'https://nuss.example',
    ]);
});

it('shows the configured mailbox wherever the app says "write to us"', function () {
    $user = User::factory()->create();
    $project = Project::factory()->public()->create();

    $this->get(route('projects.show', $project))
        ->assertSee('href="mailto:team@nuss.example?subject=Projekt melden (ID: '.$project->id.')"', false)
        ->assertDontSee('mail@nusszopf.org');

    $this->actingAs($user)->get(route('profile'))
        ->assertSee('mailto:team@nuss.example', false)
        ->assertDontSee('mail@nusszopf.org');

    expect(SubscribeForm::error())->toBe('Sorry, es ist ein Fehler aufgetreten. Bitte versuche es erneut oder melde dich bei team@nuss.example.');

    $mail = new WelcomeMail($user);
    $mail->assertSeeInHtml('mailto:team@nuss.example', false);
    $mail->assertSeeInHtml('href="'.route('contact.vcard').'"', false);
    $mail->assertDontSeeInHtml('mail@nusszopf.org');
});

it('generates the historical vCard with this instance\'s addresses and URL', function () {
    $response = $this->get('/contact/nusszopf-vcard.vcf');

    $response->assertOk()->assertHeader('Content-Type', 'text/vcard; charset=utf-8');

    expect($response->getContent())->toBe(implode("\r\n", [
        'BEGIN:VCARD',
        'VERSION:4.0',
        'N:Team;Nusszopf;;;',
        'FN:Team Nusszopf',
        'ORG:Nusszopf',
        'EMAIL:noreply@nuss.example',
        'EMAIL:team@nuss.example',
        'URL:https://nuss.example',
        'NOTE;CHARSET=UTF-8:Netzwerk für gemeinsame Ideen und Projekte',
        'END:VCARD',
    ])."\r\n");
});

it('lists one address only when sender and contact are the same', function () {
    config(['mail.from.address' => 'team@nuss.example']);

    expect(substr_count(Operator::vcard(), 'EMAIL:'))->toBe(1);
});
