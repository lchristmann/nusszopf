<?php

use App\Livewire\Projects\ProjectDetail;
use App\Mail\ContactMail;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

/**
 * The "Über Nusszopf" contact dialog (`ContactDialog.js`) — docs/rewrite/sixth-slice.md.
 * BUG-010's fix (server-side validation), BUG-005's fix (`Reply-To`) are exercised end to end
 * here; `tests/Feature/Mail/ContactMailTest.php` covers the Mailable itself in isolation.
 */
it('sends a message to the project owner\'s private e-mail, with Reply-To set to the visitor', function () {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'owner@example.test']);
    $project = Project::factory()->for($owner)->public()->create(['contact' => Project::NUSSZOPF_CONTACT, 'title' => 'Gartenprojekt']);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'visitor@example.test')
        ->set('contactMsg', 'Ich würde gerne mithelfen.')
        ->call('submitContact')
        ->assertHasNoErrors()
        ->assertDispatched('contact-sent')
        ->assertDispatched('toast', type: 'success', message: 'Nachricht versendet!');

    Mail::assertQueued(ContactMail::class, function (ContactMail $mail) use ($owner) {
        return $mail->hasTo($owner->email)
            && $mail->hasReplyTo('visitor@example.test')
            && $mail->envelope()->subject === 'Nusszopf – Kontaktanfrage';
    });
});

it('names the request in the message when opened from a request\'s dialog', function () {
    Mail::fake();
    $owner = User::factory()->create();
    $project = Project::factory()->for($owner)->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);
    $request = ProjectRequest::factory()->for($project)->create(['title' => 'Werkzeug gesucht']);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact', $request->id)
        ->set('contactEmail', 'visitor@example.test')
        ->set('contactMsg', 'Ich habe eine Bohrmaschine.')
        ->call('submitContact')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->requestTitle === 'Werkzeug gesucht');
});

it('rejects a missing or malformed e-mail address with the historical copy, and sends no mail', function () {
    Mail::fake();
    $project = Project::factory()->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', '')
        ->set('contactMsg', 'Eine Nachricht.')
        ->call('submitContact')
        ->assertHasErrors(['contactEmail' => 'required'])
        ->assertSee('Gib eine E-Mail-Adresse ein');

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'nicht-valide')
        ->set('contactMsg', 'Eine Nachricht.')
        ->call('submitContact')
        ->assertHasErrors(['contactEmail' => 'email'])
        ->assertSee('Gib eine valide E-Mail-Adresse ein');

    Mail::assertNothingQueued();
});

it('rejects an empty or over-length message with the historical copy, and sends no mail', function () {
    Mail::fake();
    $project = Project::factory()->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'visitor@example.test')
        ->set('contactMsg', '')
        ->call('submitContact')
        ->assertHasErrors(['contactMsg' => 'required'])
        ->assertSee('Bitte schreibe eine Nachricht');

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'visitor@example.test')
        ->set('contactMsg', str_repeat('x', 2001))
        ->call('submitContact')
        ->assertHasErrors(['contactMsg' => 'max'])
        ->assertSee('Maximal 2000 Zeichen');

    Mail::assertNothingQueued();
});

it('rate-limits contact submissions per IP, like the historical 10 requests per 15 minutes', function () {
    Mail::fake();
    $project = Project::factory()->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);
    RateLimiter::clear('contact:127.0.0.1');

    for ($i = 0; $i < 10; $i++) {
        Livewire::test(ProjectDetail::class, ['project' => $project])
            ->call('openContact')
            ->set('contactEmail', "visitor{$i}@example.test")
            ->set('contactMsg', 'Eine Nachricht.')
            ->call('submitContact')
            ->assertHasNoErrors();
    }

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'visitor11@example.test')
        ->set('contactMsg', 'Eine weitere Nachricht.')
        ->call('submitContact')
        ->assertSee('Zu viele Versuche. Bitte warte kurz.');

    Mail::assertQueuedCount(10);
    RateLimiter::clear('contact:127.0.0.1');
});

it('rejects an e-mail address carrying a header-injection attempt, as an invalid address', function () {
    Mail::fake();
    $project = Project::factory()->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', "visitor@example.test\r\nBcc: evil@example.test")
        ->set('contactMsg', 'Eine Nachricht.')
        ->call('submitContact')
        ->assertHasErrors(['contactEmail' => 'email']);

    Mail::assertNothingQueued();
});

it('ignores a request id that does not belong to the project, falling back to a project-level message', function () {
    Mail::fake();
    $project = Project::factory()->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);
    $foreignRequest = ProjectRequest::factory()->for(Project::factory()->public()->create())->create(['title' => 'Fremdes Gesuch']);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact', $foreignRequest->id)
        ->set('contactEmail', 'visitor@example.test')
        ->set('contactMsg', 'Eine Nachricht.')
        ->call('submitContact')
        ->assertHasNoErrors();

    Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->requestTitle === null);
});

it('never renders the owner\'s e-mail address on the page for a contact through Nusszopf', function () {
    $owner = User::factory()->create(['email' => 'geheim@example.test']);
    $project = Project::factory()->for($owner)->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $project])->assertDontSee('geheim@example.test');
});
