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

it('rejects an incorrect password without revealing which field was wrong', function () {
    $user = User::factory()->create(['password' => 'Str0ng!Pass']);

    Livewire::test(LoginRegister::class)
        ->set('emailOrName', $user->email)
        ->set('loginPassword', 'totally-wrong')
        ->call('login')
        ->assertHasErrors(['loginPassword']);

    $this->assertGuest();
});

it('rejects login for an unknown account', function () {
    Livewire::test(LoginRegister::class)
        ->set('emailOrName', 'nobody@example.com')
        ->set('loginPassword', 'whatever123')
        ->call('login')
        ->assertHasErrors(['loginPassword']);

    $this->assertGuest();
});
