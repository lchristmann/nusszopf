<?php

namespace App\Livewire\Profile;

use App\Livewire\Newsletter\Concerns\ThrottlesNewsletter;
use App\Models\Lead;
use App\Models\User;
use App\Support\AccountDeleter;
use App\Support\AvatarUploader;
use App\Support\Newsletter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

/**
 * The historical `/user/profile` (`pages/user/profile.js`, docs/design/
 * screen-specs.md "Profile / account settings"): avatar upload/crop, the
 * sponsoring link, the two info cards, and account deletion
 * (docs/rewrite/master-roadmap.md, "Slice 8"), and the newsletter subsection
 * (slice 9).
 *
 * Always "me": there is no route parameter and no way to view another
 * account's settings (docs/security/authorization-matrix.md).
 */
#[Layout('components.layout', [
    'mainClass' => 'bg-steel-100 text-steel-700',
    'footerBg' => 'bg-steel-100',
])]
class Profile extends Component
{
    use ThrottlesNewsletter, WithFileUploads;

    public ?UploadedFile $avatarUpload = null;

    public bool $newsletterPrivacy = false;

    /**
     * `handleSubscribe` in `profile.js`. Historically it inserted the lead
     * already confirmed, straight from the browser; now it requests a pending
     * subscription and sends the confirmation mail like every other path
     * (decision A-1, BUG-011) — hence the public form's toast instead of the
     * historical "Du bist jetzt angemeldet!".
     */
    public function subscribeNewsletter(): void
    {
        $this->validate(
            ['newsletterPrivacy' => ['accepted']],
            ['newsletterPrivacy.accepted' => 'Stimme den Datenschutzbestimmungen zu'],
        );

        $user = Auth::user();

        if (! $this->attemptNewsletterAction()) {
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        try {
            Newsletter::subscribe($user->email, $user->name, Lead::SOURCE_PROFILE);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        $this->newsletterPrivacy = false;
        $this->dispatch('toast', type: 'success', message: 'E-Mail verschickt! Bitte bestätige deine Anmeldung.');
    }

    /**
     * `handleUnsubscribe` in `profile.js`: native `confirm()`, then the lead
     * is deleted at once — the session already proves who owns the address.
     */
    public function unsubscribeNewsletter(): void
    {
        try {
            Newsletter::forget(Auth::user()->email);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Du bist jetzt abgemeldet!');
    }

    /**
     * `AvatarDialog.js`'s `handleSubmit`: the browser crops/compresses the
     * image (resources/js/avatar-cropper.js) and hands the result to
     * Livewire's own temporary-upload mechanism — the Laravel-native
     * equivalent of the historical two-step "get a signed upload URL, then
     * PUT to it" S3 dance, not a reason to reproduce presigned POSTs against
     * a local disk (register B4, B8).
     */
    public function saveAvatar(): void
    {
        Gate::authorize('update', Auth::user());

        $this->validate([
            'avatarUpload' => ['required', 'image', 'max:5120'],
        ]);

        try {
            AvatarUploader::store(Auth::user(), $this->avatarUpload);
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Bild konnte nicht gespeichert werden.');

            return;
        } finally {
            $this->avatarUpload = null;
        }

        $this->dispatch('toast', type: 'success', message: 'Frisches Bild gespeichert.');
        $this->dispatch('avatar-saved');
    }

    /**
     * `handleDelete` in `profile.js`: native `confirm()` (BUG-013, preserved),
     * then delete, then log out, then a success toast — `AccountDeleter`
     * resolves the historical orphaned-external-state risk on the way
     * (docs/rewrite/open-questions.md).
     */
    public function deleteAccount(): void
    {
        $user = Auth::user();

        Gate::authorize('delete', $user);

        try {
            // A *different* model instance than `$user` above: `Model::delete()`
            // flips that instance's `exists` to false, and `Auth::logout()`
            // below cycles the guard's cached user's remember token via
            // `save()` — on an `exists = false` instance that turns into an
            // INSERT, silently resurrecting the very row just deleted. Feeding
            // AccountDeleter a separate instance keeps `$user` (and the
            // guard's own reference to it) untouched for the logout that follows.
            AccountDeleter::delete(User::whereKey($user->getKey())->firstOrFail());
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: 'Sorry, da lief etwas schief.');

            return;
        }

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        session()->flash('toast', ['type' => 'success', 'message' => 'Dein Account wurde gelöscht!']);

        // Not `route('home')`: `/` is itself a redirect to `/search`
        // (routes/web.php, temporary scaffolding until Home exists — slice
        // 10), and a flashed session value only survives *one* subsequent
        // request — routing through that extra hop would lose the toast
        // before anything ever renders it.
        $this->redirectRoute('search');
    }

    public function render(): View
    {
        return view('livewire.profile.profile', [
            'newsletterConfirmed' => (bool) Auth::user()->lead?->isConfirmed(),
        ]);
    }
}
