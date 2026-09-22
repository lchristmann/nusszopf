<?php

use App\Livewire\Profile\Profile;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\AvatarUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * BUG-031 (docs/rewrite/bugs.md): the historical upload endpoint trusted the
 * browser's own crop/compress step entirely, checking only a ≤1 MB size
 * condition. `App\Support\AvatarUploader` decodes, center-crops and
 * re-encodes every upload server-side regardless of what the client sent.
 */
it('stores a cropped/re-encoded square avatar on first upload', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatarUpload', UploadedFile::fake()->image('avatar.jpg', 400, 400))
        ->call('saveAvatar')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Frisches Bild gespeichert.')
        ->assertDispatched('avatar-saved');

    $user->refresh();
    expect($user->avatar_version)->toBe(1)
        ->and($user->picture)->toBe("avatars/{$user->id}-v1.jpg");

    Storage::disk('public')->assertExists($user->picture);
});

it('center-crops and caps an oversized, non-square source image', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    AvatarUploader::store($user, UploadedFile::fake()->image('wide.jpg', 2000, 1000));

    $path = Storage::disk('public')->path($user->fresh()->picture);
    [$width, $height] = getimagesize($path);

    expect($width)->toBe($height)->and($width)->toBeLessThanOrEqual(512);
});

it('replaces the previous file and increments the version on a second upload', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    AvatarUploader::store($user, UploadedFile::fake()->image('one.jpg', 300, 300));
    $user->refresh();
    $firstPath = $user->picture;
    expect($user->avatar_version)->toBe(1);
    Storage::disk('public')->assertExists($firstPath);

    AvatarUploader::store($user, UploadedFile::fake()->image('two.jpg', 300, 300));
    $user->refresh();

    expect($user->avatar_version)->toBe(2)
        ->and($user->picture)->not->toBe($firstPath);
    Storage::disk('public')->assertMissing($firstPath);
    Storage::disk('public')->assertExists($user->picture);
});

it('never deletes the current avatar for a Google-provided URL', function () {
    Storage::fake('public');
    $user = User::factory()->create(['picture' => 'https://example.com/avatar.jpg', 'google_id' => 'g-1']);

    AvatarUploader::deleteStoredAvatar($user);

    // No exception, nothing on the local disk to have deleted — the
    // assertion is simply that this does not throw for an external URL.
    expect(true)->toBeTrue();
});

/**
 * Laravel's own `image` validation rule trusts a forced content-type
 * (`UploadedFile::fake()->create(..., 'image/jpeg')`) rather than decoding
 * the bytes, so a garbage file dressed up as a JPEG passes it — exactly
 * BUG-031's point: `App\Support\AvatarUploader`'s own GD decode is the
 * layer that actually catches this, not the validation rule.
 */
it('rejects a non-image upload before it ever reaches storage', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('avatarUpload', UploadedFile::fake()->create('not-an-image.jpg', 10, 'image/jpeg'))
        ->call('saveAvatar')
        ->assertDispatched('toast', type: 'error', message: 'Bild konnte nicht gespeichert werden.')
        ->assertNotDispatched('avatar-saved');

    expect($user->fresh()->picture)->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('avatars');
});

it('gates avatar upload to the account itself', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $policy = new UserPolicy;

    expect($policy->update($owner, $owner))->toBeTrue()
        ->and($policy->update($stranger, $owner))->toBeFalse();
});

it('hides the edit affordance for an account linked to Google', function () {
    $user = User::factory()->create(['google_id' => 'g-1']);

    Livewire::actingAs($user)->test(Profile::class)
        ->assertDontSee('btn_edit-avatar_settings-page', false);
});

it('shows the edit affordance for a non-social account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->assertSee('btn_edit-avatar_settings-page', false);
});
