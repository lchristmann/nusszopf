<?php

use App\Livewire\Auth\LoginRegister;
use App\Models\User;
use Livewire\Livewire;

/**
 * docs/authentication/README.md §3: login accepts either username or
 * email in one field; every login lands on /user/projects (BUG-015,
 * preserved as historically observed).
 */
it('logs in with an email address', function () {
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login')
        ->assertRedirect(route('projects.mine'));

    $this->assertAuthenticatedAs($user);
});

it('logs in with the username instead of the email', function () {
    $user = User::factory()->create(['name' => 'nussknacker', 'password' => 'Str0ng!Pass']);

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', 'nussknacker')
        ->set('loginPassword', 'Str0ng!Pass')
        ->call('login');

    $this->assertAuthenticatedAs($user);
});

it('rejects an incorrect password with the generic error toast, never a field message', function () {
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'totally-wrong')
        ->call('login')
        ->assertDispatched('toast', type: 'error', message: 'Sorry, da lief etwas schief.')
        ->assertHasNoErrors();

    $this->assertGuest();
});

it('rejects login for an unknown account', function () {
    Livewire::test(LoginRegister::class)
        ->set('emailOrName', 'nobody@example.com')
        ->set('loginPassword', 'whatever123')
        ->call('login')
        ->assertDispatched('toast', type: 'error', message: 'Sorry, da lief etwas schief.')
        ->assertHasNoErrors();

    $this->assertGuest();
});

it('matches the address before a username that looks like the same address (SEC-09)', function () {
    // Registered first, so a single "email = x OR name = x" query finds it first.
    $squatter = User::factory()->create(['name' => 'a@b.de', 'email' => 'squatter@example.com', 'password' => 'Squ4tter!Pass']);
    $owner = User::factory()->create(['name' => 'owner', 'email' => 'a@b.de', 'password' => 'Str0ng!Passwort']);

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', 'a@b.de')
        ->set('loginPassword', 'Str0ng!Passwort')
        ->call('login')
        ->assertRedirect(route('projects.mine'));

    expect(auth()->id())->toBe($owner->id)->and(auth()->id())->not->toBe($squatter->id);
});
