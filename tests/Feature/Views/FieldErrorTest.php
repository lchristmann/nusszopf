<?php

use App\Livewire\Auth\LoginRegister;
use App\Livewire\Projects\ProjectWizard;
use App\Models\User;
use App\Support\FieldError;
use Livewire\Livewire;

/**
 * Decision A-7: a validation message is tied to its field — the message has a stable id, and the control
 * carries `aria-invalid` and `aria-describedby` pointing at it while (and only while) the field has an error.
 */
it('ties each registration message to its field', function () {
    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->call('register')
        ->assertSeeHtml('id="'.FieldError::id('username').'"')
        ->assertSeeHtml('aria-invalid="true" aria-describedby="error-username"')
        ->assertSeeHtml('aria-describedby="error-email"')
        ->assertSeeHtml('aria-describedby="error-registerPassword"')
        ->assertSeeHtml('aria-describedby="error-privacy"');
});

it('marks nothing invalid before a field has an error', function () {
    Livewire::test(LoginRegister::class, ['tab' => 'register'])
        ->assertDontSeeHtml('aria-invalid')
        ->assertDontSeeHtml('aria-describedby="error-');
});

it('ties the wizard messages to their fields, dotted keys included', function () {
    Livewire::withQueryParams(['step' => 0])
        ->actingAs(User::factory()->create())
        ->test(ProjectWizard::class)
        ->set('location.remote', false)
        ->set('period.flexible', false)
        ->call('next')
        ->assertSeeHtml('aria-describedby="error-title"')
        ->assertSeeHtml('id="error-title"')
        ->assertSeeHtml('id="error-period-from"')
        ->assertSeeHtml('aria-describedby="error-period-from"');
});

it('turns a dotted or bracketed key into a valid id', function () {
    expect(FieldError::id('location.searchTerm'))->toBe('error-location-searchTerm')
        ->and(FieldError::id('items[0]'))->toBe('error-items-0-');
});
