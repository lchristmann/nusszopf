<?php

use App\Livewire\Projects\MyProjects;
use App\Livewire\Projects\ProjectDetail;
use App\Livewire\Projects\ProjectEdit;
use App\Livewire\Search\Search;
use App\Mail\ContactMail;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/**
 * P-4 authorization-matrix coverage (docs/release/parity/P-04-security.md, "Authorization matrix coverage"):
 * the rows the existing per-feature tests did not pin down on their own.
 */

// Project → "Reassign owner", "Update/Delete project": a component bound to one project cannot be pointed at
// another by the client. Livewire ignores a client update of a bound model; the component keeps its own.
it('does not let the client point an owner\'s edit screen at another user\'s project', function () {
    $owner = User::factory()->create();
    $own = Project::factory()->for($owner)->create(['title' => 'Eigen']);
    $foreign = Project::factory()->create(['title' => 'Fremd']);

    $component = Livewire::actingAs($owner)->test(ProjectEdit::class, ['project' => $own])
        ->set('project', $foreign->id)
        ->set('project', $foreign)
        ->call('selectView', 'Einstellungen')
        ->call('deleteProject');

    expect($component->instance()->project->is($own))->toBeTrue()
        ->and(Project::find($own->id))->toBeNull()
        ->and($foreign->fresh()->title)->toBe('Fremd');
});

it('does not let the client point a public project page at a private project to mail its owner', function () {
    Mail::fake();
    $public = Project::factory()->create(['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);
    $private = Project::factory()->create(['visibility' => 'private', 'contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $public])
        ->set('project', $private->id)
        ->call('openContact')
        ->set('contactEmail', 'besucher@example.com')
        ->set('contactMsg', 'Hallo')
        ->call('submitContact');

    Mail::assertQueued(ContactMail::class, 1);
    Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->ownerEmail === $public->user->email);
});

it('never lets a project change its owner, even through a save by the owner', function () {
    $project = Project::factory()->create();
    $other = User::factory()->create();

    $project->update(['user_id' => $other->id]);

    expect($project->fresh()->user_id)->not->toBe($other->id)
        ->and((new Project)->isFillable('user_id'))->toBeFalse()
        ->and((new ProjectRequest)->isFillable('project_id'))->toBeFalse();
});

// User → "View email": never shown to another user or a guest, on any surface they can reach
// (§7.2 item 10 of the finish line).
it('never shows an account\'s e-mail address to anyone else', function () {
    $owner = User::factory()->create(['name' => 'eigner', 'email' => 'geheim-eigner@example.com']);
    $project = Project::factory()->for($owner)->create(['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);
    ProjectRequest::factory()->for($project)->create();
    $stranger = User::factory()->create();

    $surfaces = [
        $this->get(route('projects.show', $project))->getContent(),
        $this->get(route('sitemap'))->getContent(),
        $this->get(route('search'))->getContent(),
        Livewire::test(Search::class)->call('load')->html(),
        $this->actingAs($stranger)->get(route('projects.show', $project))->getContent(),
        $this->actingAs($stranger)->get(route('projects.mine'))->getContent(),
        Livewire::actingAs($stranger)->test(MyProjects::class)->call('load')->html(),
        json_encode($project->fresh()->toArray()),
        json_encode($owner->fresh()->toArray()),
        json_encode($project->toSearchableArray()),
    ];

    foreach ($surfaces as $surface) {
        expect($surface)->not->toContain('geheim-eigner@example.com');
    }
});

it('mails a contact to the owner without showing the owner\'s address to the visitor', function () {
    Mail::fake();
    $owner = User::factory()->create(['email' => 'geheim-eigner@example.com']);
    $project = Project::factory()->for($owner)->create(['visibility' => 'public', 'contact' => Project::NUSSZOPF_CONTACT]);

    $html = Livewire::test(ProjectDetail::class, ['project' => $project])
        ->call('openContact')
        ->set('contactEmail', 'besucher@example.com')
        ->set('contactMsg', 'Hallo')
        ->call('submitContact')
        ->html();

    expect($html)->not->toContain('geheim-eigner@example.com');
    Mail::assertQueued(ContactMail::class, fn (ContactMail $mail) => $mail->ownerEmail === 'geheim-eigner@example.com');
});

// "Direct URLs / mutations / API endpoints": the complete list of HTTP entry points. A new route must be added
// here on purpose, and to docs/security/authorization-matrix.md with it.
it('exposes exactly the known HTTP entry points, each behind its documented gate', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->reject(fn ($route) => str_starts_with($route->uri(), 'livewire-'))
        ->mapWithKeys(function ($route) {
            $gates = collect($route->gatherMiddleware())
                ->map(fn ($middleware) => is_string($middleware) ? strtok($middleware, ':') : null)
                ->filter(fn ($middleware) => in_array($middleware, ['auth', 'guest', 'signed', 'throttle'], true))
                ->unique()->sort()->values()->implode(',');

            return [implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->uri() => $gates];
        })
        ->sortKeys()
        ->all();

    expect($routes)->toBe([
        'GET /' => '',
        'GET auth/google/callback' => 'guest',
        'GET auth/google/redirect' => 'guest',
        'GET auth/unblock' => 'signed',
        'GET contact/nusszopf-vcard.vcf' => '',
        'GET email/verify/{id}/{hash}' => 'signed',
        'GET health' => '',
        'GET legalNotice' => '',
        'GET legalPolicy' => '',
        'GET login' => 'guest',
        'GET newsletter/subscribe/{token}' => '',
        'GET newsletter/unsubscribe/lead' => '',
        'GET newsletter/unsubscribe/{token}' => '',
        'GET password/forgot' => 'guest',
        'GET password/reset/{token}' => 'guest',
        'GET privacy' => '',
        'GET projects/{project}' => '',
        'GET robots.txt' => '',
        'GET search' => '',
        'GET sitemap.xml' => 'throttle',
        'GET up' => '',
        'GET user/profile' => 'auth',
        'GET user/project/create' => 'auth',
        'GET user/project/{project}/edit' => 'auth',
        'GET user/projects' => 'auth',
        'POST email/verification-notification' => 'auth,throttle',
        'POST logout' => 'auth',
    ]);
});
