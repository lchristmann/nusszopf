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
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'email', 'password', 'picture', 'google_id', 'email_verified_at'])]
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
        ];
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
