<?php

namespace App\Models;

use App\Mail\ChangePasswordMail;
use App\Mail\VerifyEmailMail;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'picture', 'avatar_version', 'google_id', 'email_verified_at'])]
#[Hidden(['password', 'remember_token', 'email', 'google_id'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, MustVerifyEmail, Notifiable;

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * `User.lead` (docs/domain/relationships.md): the newsletter subscription
     * for the same address — matched by e-mail, not a foreign key, as
     * historically.
     *
     * @return HasOne<Lead, $this>
     */
    public function lead(): HasOne
    {
        return $this->hasOne(Lead::class, 'email', 'email');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'avatar_version' => 'integer',
        ];
    }

    /**
     * `picture` holds either a Google-provided absolute URL (BUG-004's sync,
     * `App\Http\Controllers\Auth\GoogleController`) or a path on the local
     * `public` disk (a manual upload, `App\Support\AvatarUploader`) — the same
     * duality the historical `users.picture` column held (a Spaces CDN URL
     * either way, so the distinction was invisible there). `null` here (never
     * ui-avatars.com, docs/rewrite/intentional-changes.md) means the caller
     * renders the initial-on-grey fallback instead.
     */
    public function avatarUrl(): ?string
    {
        if (blank($this->picture)) {
            return null;
        }

        return Str::startsWith($this->picture, ['http://', 'https://'])
            ? $this->picture
            : Storage::disk('public')->url($this->picture);
    }

    /**
     * Gates the Profile page's avatar-edit affordance (`Avatar.molecule.js`'s
     * `isSocialAccount`, Confirmed): an account linked to Google has its
     * picture kept in sync from there (BUG-004) and was never offered a manual
     * "replace avatar" control historically — preserved as-is, not a bug.
     */
    public function isSocialAccount(): bool
    {
        return $this->google_id !== null;
    }

    /**
     * Reproduces the historical `change-password.mjml` (docs/email/README.md
     * item 2) instead of Laravel's own generic notification mail — the same
     * "own Mailable through the shared mail layout" pattern the sixth slice
     * established for the contact form.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->email]);

        Mail::send(new ChangePasswordMail($this->email, $url));
    }

    /**
     * Overrides `MustVerifyEmail`'s default (`Illuminate\Auth\Notifications\
     * VerifyEmail`) with the app's own Mailable/mail-layout pattern (decision
     * A-3, docs/rewrite/decisions-register.md — there is no historical
     * template, see App\Mail\VerifyEmailMail). A 7-day expiry: this gates two
     * optional actions, not login, so there is no reason for the link to be
     * as short-lived as a password-reset ticket.
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addDays(7),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        Mail::send(new VerifyEmailMail($this, $url));
    }
}
