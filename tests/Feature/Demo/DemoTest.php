<?php

use App\Http\Controllers\Auth\GoogleController;
use App\Livewire\Auth\LoginRegister;
use App\Livewire\Newsletter\SubscribeForm;
use App\Livewire\Profile\Profile;
use App\Livewire\Projects\ProjectDetail;
use App\Mail\ContactMail;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Support\Demo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/**
 * The public demo (docs/deployment/demo.md): a shared, password-less account with fictional data, entered with one
 * button, protected from destructive changes and rebuilt by `demo:reset`.
 */
beforeEach(fn () => config(['nusszopf.demo' => true]));

it('is off by default: no route, no reset', function () {
    config(['nusszopf.demo' => false]);

    $this->post(route('demo.login'))->assertNotFound();
    $this->artisan('demo:reset')->assertFailed();

    expect(Demo::user())->toBeNull();
});

it('builds the demo account with fictional public projects, one private draft and requests in every use', function () {
    $user = Demo::reset();

    expect($user->email)->toBe(Demo::EMAIL)
        ->and($user->password)->toBeNull()
        ->and($user->projects()->where('visibility', 'public')->count())->toBeGreaterThanOrEqual(3)
        ->and($user->projects()->where('visibility', 'private')->count())->toBe(1)
        ->and(ProjectRequest::whereIn('project_id', $user->projects()->pluck('id'))->distinct()->pluck('category')->all())
        ->toEqualCanonicalizing(ProjectRequest::CATEGORIES);
});

it('uses no real address anywhere: every contact is example.org and no project relays through Nusszopf', function () {
    Demo::reset();

    $contacts = Project::where('user_id', Demo::user()->id)->pluck('contact')->unique()->all();

    expect($contacts)->toBe(['kontakt@example.org'])
        ->and($contacts)->not->toContain(Project::NUSSZOPF_CONTACT)
        ->and(Demo::EMAIL)->toEndWith('.example.org');
});

it('resets to the same state and leaves every other account and its projects untouched', function () {
    $other = User::factory()->create();
    $otherProject = Project::factory()->for($other)->public()->create();
    $first = Demo::reset();
    $first->projects()->first()->update(['title' => 'Von einem Besucher verändert']);
    $first->projects()->create(['title' => 'Von einem Besucher angelegt', 'goal' => 'x', 'description' => 'y', 'visibility' => 'public', 'contact' => 'a@example.org']);

    $second = Demo::reset();

    expect($second->projects()->count())->toBe(5)
        ->and($second->projects()->where('title', 'like', 'Von einem Besucher%')->count())->toBe(0)
        ->and(User::where('email', Demo::EMAIL)->count())->toBe(1)
        ->and(User::find($other->id))->not->toBeNull()
        ->and(Project::find($otherProject->id))->not->toBeNull();
});

it('logs a visitor in as the demo account with one button, creating it when missing', function () {
    expect(Demo::user())->toBeNull();

    $this->post(route('demo.login'))->assertRedirect(route('projects.mine'));

    $this->assertAuthenticated();
    expect(auth()->user()->email)->toBe(Demo::EMAIL);
});

it('starts the tour through the same button', function () {
    $this->post(route('demo.login', ['tour' => 1]))->assertRedirect(route('projects.mine', ['tour' => 1]));
});

it('does not replace a signed-in real account with the demo account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('demo.login'))->assertRedirect(route('projects.mine'));

    expect(auth()->id())->toBe($user->id);
});

it('cannot be signed into with a password, by name or by address', function () {
    Demo::reset();

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', Demo::EMAIL)->set('loginPassword', 'anything')->call('login');
    $this->assertGuest();

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', Demo::NAME)->set('loginPassword', '')->call('login');
    $this->assertGuest();
});

it('cannot be deleted, have its avatar changed or subscribe its address to the newsletter', function () {
    $demo = Demo::reset();

    Livewire::actingAs($demo)->test(Profile::class)->call('deleteAccount')->assertForbidden();
    Livewire::actingAs($demo)->test(Profile::class)->call('saveAvatar')->assertForbidden();
    Livewire::actingAs($demo)->test(Profile::class)->set('newsletterPrivacy', true)->call('subscribeNewsletter')
        ->assertDispatched('toast', type: 'error', message: 'In der Demo ist die Newsletter-Anmeldung deaktiviert.');

    expect(User::find($demo->id))->not->toBeNull()
        ->and(Lead::where('email', Demo::EMAIL)->count())->toBe(0);
});

it('explains on the demo profile why the account cannot be deleted, and still lets an ordinary account delete itself', function () {
    $this->actingAs(Demo::reset())->get(route('profile'))
        ->assertSee('data-test="demo-account-note"', false)
        ->assertDontSee('btn_delete-account_settings-page', false)
        ->assertDontSee('btn_edit-avatar_settings-page', false);

    auth()->logout();

    $this->actingAs(User::factory()->create())->get(route('profile'))
        ->assertSee('btn_delete-account_settings-page', false)
        ->assertDontSee('demo-account-note', false);
});

it('offers the guided tour only to the demo account, pointing at a public project', function () {
    $ordinary = User::factory()->create();
    $this->actingAs($ordinary)->get(route('projects.mine'))->assertDontSee('data-tour-enabled', false)->assertDontSee('btn_tour-start', false);

    auth()->logout();
    $demo = Demo::reset();
    $featured = Demo::featuredProject();

    expect($featured->visibility)->toBe('public');

    $this->actingAs($demo)->get(route('projects.mine'))
        ->assertSee('data-tour-enabled', false)
        ->assertSee('data-tour-project="'.route('projects.show', $featured, false).'"', false)
        ->assertSee('data-test="btn_tour-start"', false);
});

it('offers no tour when demo mode is off, even to an account with the demo address', function () {
    $demo = Demo::reset();
    config(['nusszopf.demo' => false]);

    $this->actingAs($demo)->get(route('projects.mine'))->assertDontSee('data-tour-enabled', false);
});

// The task is registered in routes/console.php only when NUSSZOPF_DEMO is on at boot, which the suite never sets.
it('does not schedule the reset on an ordinary installation', function () {
    $names = fn () => collect(app(Schedule::class)->events())->map->description->all();

    expect($names())->not->toContain('demo-reset');
});

it('collects no real address on the demo: no registration form, no registering, no Google, no sign-up, no contact mail', function () {
    Mail::fake();
    config(['services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);

    // The register tab explains instead of showing the form.
    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->assertSee('data-test="demo-register-note"', false)
        ->assertSee('In der Demo ist die Registrierung abgeschaltet')
        ->assertDontSee('data-test="btn_register"', false)
        ->set('username', 'echterMensch')->set('email', 'echt@example.net')->set('registerPassword', 'Str0ng!Passw0rd')->set('privacy', true)->set('newsletter', true)
        ->call('register')
        ->assertDispatched('toast', type: 'error', message: 'In der Demo ist die Registrierung abgeschaltet.');

    // No Google button on the login tab either.
    Livewire::test(LoginRegister::class)->assertDontSee('btn_login-google', false);

    expect(User::where('email', 'echt@example.net')->exists())->toBeFalse()
        ->and(Lead::count())->toBe(0);

    $this->get(route('google.redirect'))->assertNotFound();
    $this->get(route('google.callback'))->assertNotFound();

    // The newsletter form, even if reached, stores nothing.
    Livewire::test(SubscribeForm::class)
        ->set('name', 'Echt')->set('email', 'echt@example.net')->set('privacy', true)->call('subscribe')
        ->assertDispatched('toast', type: 'error', message: 'In der Demo ist die Newsletter-Anmeldung deaktiviert.');
    expect(Lead::count())->toBe(0);

    // A visitor's address is not taken by the project contact form either.
    $project = Project::factory()->for(User::factory()->create())->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);

    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->set('contactEmail', 'echt@example.net')->set('contactMsg', 'Hallo')->call('submitContact')
        ->assertDispatched('toast', type: 'error', message: 'In der Demo werden keine Nachrichten verschickt.');

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('leaves registration, Google sign-in, the sign-up form and the contact form working on a real installation', function () {
    config(['nusszopf.demo' => false, 'services.google.client_id' => 'id', 'services.google.client_secret' => 'secret']);
    Mail::fake();

    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->assertSee('data-test="btn_register"', false)
        ->assertDontSee('demo-register-note', false)
        ->set('username', 'echterMensch')->set('email', 'echt@example.net')->set('registerPassword', 'Str0ng!Passw0rd')->set('privacy', true)
        ->call('register');
    Livewire::test(LoginRegister::class)->assertSee('btn_login-google', false);

    expect(User::where('email', 'echt@example.net')->exists())->toBeTrue()
        ->and(GoogleController::configured())->toBeTrue();

    Livewire::test(SubscribeForm::class)
        ->set('name', 'Echt')->set('email', 'neu@example.net')->set('privacy', true)->call('subscribe')
        ->assertDispatched('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');
    expect(Lead::where('email', 'neu@example.net')->exists())->toBeTrue();

    $project = Project::factory()->for(User::factory()->create())->public()->create(['contact' => Project::NUSSZOPF_CONTACT]);
    Livewire::test(ProjectDetail::class, ['project' => $project])
        ->set('contactEmail', 'echt@example.net')->set('contactMsg', 'Hallo')->call('submitContact');
    Mail::assertQueued(ContactMail::class);
});
