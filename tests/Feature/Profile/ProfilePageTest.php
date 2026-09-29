<?php

use App\Models\User;

/**
 * docs/design/screen-specs.md, "Profile / account settings" (the newsletter
 * subsection: tests/Feature/Profile/ProfileNewsletterTest.php). The
 * "Kontakt speichern" vCard `InfoCard` is back since slice 10, generated from
 * this instance's identity (tests/Feature/Support/OperatorIdentityTest.php).
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

it('shows both info cards and no funding or membership block', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile'))
        ->assertDontSee('steadyhq.com')
        ->assertDontSee('Fördermitgliedschaft')
        ->assertDontSee('Steady')
        ->assertDontSee('id="sponsoring"', false)
        ->assertSee('mail@nusszopf.org')
        ->assertSee('href="'.route('contact.vcard').'"', false)
        ->assertSee('Füge den Nusszopf zu deinen Kontakten hinzu, damit unsere E-Mails dich sicher erreichen:');
});

it('links to the profile page from the nav header "Account" menu item', function () {
    $user = User::factory()->create(['name' => 'gartenfreund']);

    $this->actingAs($user)->get(route('search'))
        ->assertSee('btn_settings_nav-header', false)
        ->assertSee('href="'.route('profile').'"', false)
        ->assertSee('gartenfreund');
});
