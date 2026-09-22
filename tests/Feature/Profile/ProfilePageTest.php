<?php

use App\Models\User;

/**
 * docs/design/screen-specs.md, "Profile / account settings". The newsletter
 * subsection is intentional scaffolding (no `Lead` model until slice 9,
 * matching the registration checkbox scaffold from the seventh slice); the
 * "Kontakt speichern" vCard `InfoCard` is deliberately not reproduced
 * (docs/rewrite/intentional-changes.md, extends the sixth-slice mail-footer
 * decision).
 */
it('requires authentication', function () {
    $this->get(route('profile'))->assertRedirect(route('login'));
});

it('shows the profile page to the owner only, always "me"', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile'))
        ->assertOk()
        ->assertSee('Einstellungen')
        ->assertSee($user->name)
        ->assertSee($user->email);
});

it('renders the newsletter subsection as an inert scaffold', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile'))
        ->assertSee('Newsletter')
        ->assertSee('Folgt in Kürze.');
});

it('shows the sponsoring link and the support info card, but not the vCard link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile'))
        ->assertSee('https://steadyhq.com/de/nusszopf', false)
        ->assertSee('mail@nusszopf.org')
        ->assertDontSee('nusszopf-vcard.vcf', false);
});

it('links to the profile page from the nav header "Account" menu item', function () {
    $user = User::factory()->create(['name' => 'gartenfreund']);

    $this->actingAs($user)->get(route('search'))
        ->assertSee('btn_settings_nav-header', false)
        ->assertSee('href="'.route('profile').'"', false)
        ->assertSee('gartenfreund');
});
