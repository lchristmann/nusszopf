# Seventh Vertical Slice — Authentication completion

Status: **implemented and verified** (2026-09-22). This slice completed Laravel-native authentication:
the "Passwort vergessen"/"Neues Passwort erstellen" screens and their mail, Google login via Socialite
(hidden until configured), the welcome mail, a per-account login-lockout notice, social-login avatar
sync (BUG-004), and a new, non-blocking e-mail-verification gate (decision A-3, BUG-030) that only
affects publishing a project's "Persönlich" contact. Scope and sequencing come from
`docs/rewrite/master-roadmap.md`, "Slice 7".

**Out of scope (unchanged)**: `/user/profile` and its own resend-verification UI (slice 8); the
newsletter checkbox at registration stays rendered-but-unwired (slice 9, see "Intentional scaffolding"
below); breached-password detection (register C2, deferred indefinitely); Apple login (BUG-012, dropped).

## Historical mechanics (Confirmed against `web-nusszopf`/`be-nusszopf`/`emails-nusszopf`, read in full)

| Item | Evidence | Behavior |
|---|---|---|
| Forgot password | `ChangePasswordForm.js` | Single `email` field; submit → toast "E-Mail verschickt!", stays on screen; "Abbrechen" returns to login/signup |
| Set new password | `PasswordForm.js`, `auth-password/src/pages/index.js` | Single `password` field, identical 5-rule strength policy as registration; success toast then a 1.5s-delayed hard redirect to login |
| Change-password mail | `auth0/change-password.mjml` | Subject "Nusszopf – Neues Passwort erstellen"; one paragraph, a pill CTA "Neues Passwort erstellen" → `{{ url }}`, the orange "not you?" notice |
| Google login | `LoginForm.js`, `auth-login/src/pages/index.js` `handleGoogleLogin` | `webAuth.authorize({ connection: 'google-oauth2' })`; button on the **login tab only** (`SignUpForm.js` has no social buttons at all) |
| Welcome mail | `auth0/welcome.mjml` | Unconditional on signup; no CTA, purely informational |
| Blocked-account mail | `auth0/blocked-account.mjml` | Subject "Nusszopf – IP-Adresse blockiert"; names the source IP/city/country, a pill CTA "Das bin ich!" → unblock `{{ url }}` |
| Avatar sync | `be-nusszopf/auth0/rules/userPicture.js` | Every social login **unconditionally overwrites** `users.picture` from the provider (BUG-004) |
| Email verification | absent everywhere | No template, no Auth0 rule, no gate; E2E logs straight into `/user/projects` after signup |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Forgot-password/set-new-password become their own routes (`/password/forgot`, `/password/reset/{token}`), not an in-place view swap inside the login `FramedCard` | **Replace** (implementation technique) | `docs/design/screen-specs.md` already describes them as target routes with no historical 1:1 URL to cite (the three-app Auth0 split had no client-side view-swap to preserve); Laravel's password broker is route-shaped anyway |
| 2 | The 1.5s delayed redirect after a successful password reset is not reproduced — the toast is flashed and the redirect happens immediately | **Replace** (implementation technique) | A server-rendered redirect has nothing to delay for; the historical delay only existed so the visitor could read a toast before a *client-side* `window.location.href` hard navigation tore the page down |
| 3 | Login lockout is **per-account**, in addition to the existing per-IP rate limit from the first slice | **New functionality** (register B-7) | Auth0 blocked the *IP*; Nusszopf 2 needs to know *which account* to notify, which a bare IP counter can't tell it. Both limiters run: `login:{ip}` (60s, unchanged since slice 1) and `login-account:{user_id}` (5 attempts / 15 minutes, new) |
| 4 | `BlockedAccountMail` drops the historical `{{ user.city }}`/`{{ user.country }}` clauses | **Replace** (dependency dropped) | No geo-IP lookup service is a dependency this self-hosted app should require by default — same category as the ui-avatars.com and SendGrid-CDN-logo replacements (`docs/rewrite/intentional-changes.md`). The source IP itself is kept |
| 5 | The "Das bin ich!" link really clears the lock (a signed URL to `App\Http\Controllers\Auth\UnblockLoginController`), scoped to exactly the IP/account pair the mail was sent about | **New functionality**, faithful to intent | Not a decorative link — the closest equivalent to Auth0's real unblock action without inventing an admin "unlock" surface that never existed historically |
| 6 | `App\Mail\VerifyEmailMail` has no historical template — new German copy, brand-consistent | **New functionality** (decision A-3) | There is nothing to port; the copy states plainly that nothing about using Nusszopf is blocked by not verifying |
| 7 | E-mail verification gates exactly one thing this slice: the project wizard/edit "Persönlich" contact option | **New functionality** (decision A-3, BUG-030) | The newsletter half of the decision has no code to attach to yet (slice 9); login/registration are never gated, exactly as decided |
| 8 | Google login links an existing account, or creates a new one, **only** when Google's own `email_verified` claim is true; a Google-authenticated account is always created already verified | **New functionality** (decision A-3) | An unverified Google account can't silently take over a Nusszopf account sharing that address, and there is nothing left to confirm for an address Google itself vouches for |
| 9 | New accounts from Google get a generated username (Google's `nickname`/`name`, sanitized to the same no-whitespace/15-char rule as manual registration, numeric suffix on collision) | **New functionality**, Inferred | Auth0's own nickname derivation was platform-internal and unrecoverable; this is a reasonable, documented engineering choice, not a guess presented as fact |
| 10 | A password-visibility (eye/eye-off) toggle (`<x-password-field>`) was added to **every** password field, including the two that already existed from the first slice (login, register) | **Fidelity catch-up**, Inferred positioning | `docs/design/components.md` lists `InputGroup` as "Listed, not Read" — the historical pattern (`LoginForm.js`, `SignUpForm.js`, `PasswordForm.js` all pair every password field with this toggle) is reproduced structurally (Alpine `x-data`, no new JS dependency), not pixel-traced, since no component archaeology pass measured its exact geometry |
| 11 | `NavHeader mode="external"` / `Footer variant="auth0"` (the historical auth-app-only chrome) are **not** reproduced | **Preserve absence** (documented, not silently dropped) | `docs/design/navigation.md` itself already concludes the Auth0 sponsor badge inside that footer variant doesn't need reproducing since Auth0 is gone, and the "Create project" menu item difference `mode="external"` produced is moot here: Nusszopf 2's login screen is `guest`-only, so the shared `NavHeader`'s own authenticated-only items are already absent without a second mode. The `auth-login`/`auth-password` chrome existed only because those were separate deployed apps; the single-monolith rewrite has no second app to give a distinct shell to |
| 12 | The registration "newsletter" checkbox renders but is wired to nothing | **Intentional scaffolding** | The `Lead` model doesn't exist until slice 9; see below |

## Implementation

- **Migration** `database/migrations/2026_09_22_120000_add_auth_completion_columns_to_users_table.php`:
  `users.email_verified_at` (nullable timestamp), `users.google_id` (nullable unique string),
  `users.password` becomes nullable (a Google-only account has none).
- **`App\Models\User`**: implements `MustVerifyEmail` (the trait, for `hasVerifiedEmail()` etc., but no
  route ever applies the `verified` middleware); overrides `sendPasswordResetNotification()` and
  `sendEmailVerificationNotification()` to send the app's own Mailables through the shared mail layout
  instead of Laravel's generic notification mail — the same pattern the sixth slice established for
  the contact form.
- **Mail** (`resources/views/components/mail/button.blade.php` is a new shared pill-button partial,
  reused by all three): `App\Mail\ChangePasswordMail`, `App\Mail\WelcomeMail`, `App\Mail\VerifyEmailMail`,
  `App\Mail\BlockedAccountMail` — all `ShouldQueue`, all queued on the existing `queue-worker`.
- **`App\Livewire\Auth\LoginRegister`**: `login()` now runs the per-account lockout (decision 3) and
  sends `BlockedAccountMail` exactly once per lock; `register()` sends `WelcomeMail` and the
  verification mail; a `newsletter` scaffold property; a `googleConfigured()` check drives the login
  tab's Google button.
- **`App\Livewire\Auth\ForgotPassword`** / **`ResetPassword`** (new components, new routes
  `password.request`/`password.reset`): thin wrappers over `Illuminate\Support\Facades\Password`.
- **`App\Http\Controllers\Auth\GoogleController`**: `redirect()`/`callback()` over Socialite;
  `configured()` gates both routes with a 404 and the Blade button's visibility (register B-6, same
  pattern as `LOCATIONIQ_KEY`); the linking/creation/avatar-sync logic is decisions 8–9 above.
- **`App\Http\Controllers\Auth\VerifyEmailController`** (`verification.verify`, signed, no `auth`
  middleware — see decision 6), **`ResendVerificationController`** (`verification.send`, `auth` +
  throttled), **`UnblockLoginController`** (`login.unblock`, signed).
- **`App\Livewire\Concerns\ManagesProjectFields`**: `contactAllowed(User $user)` (the new gate) and
  `resendVerificationEmail()`, called from `ProjectWizard::create()` and `ProjectEdit::saveSettings()`;
  `resources/views/components/project-form/contact.blade.php` shows the error and a resend button.
- **UI**: `resources/views/components/password-field.blade.php` (decision 10); a "google.svg" icon
  (the historical multi-color mark, `react-feather` LICENSE unaffected since this one isn't a Feather
  icon); `resources/views/livewire/auth/{forgot-password,reset-password}.blade.php`.
- **Config**: `config/services.php` gains `google` (client id/secret/redirect, `GOOGLE_*` env); `.env.example`/
  `.env.production.example` document it and the now-broader mail dependency list.

## Intentional scaffolding (new)

| Item | Why | Ends with |
|---|---|---|
| Registration's "Nussigen Newsletter abonnieren" checkbox renders, records nothing | The `Lead` model, double opt-in and consent record don't exist until slice 9 (register A-1) | Slice 9 |
| The only in-app place to resend a verification e-mail is next to the wizard/edit "Persönlich" error | `/user/profile` (where a "resend" affordance would naturally also live) is slice 8 | Slice 8 |

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Auth/PasswordResetTest.php` | enumeration-safe request, mail contents/URL, reset updates the password with the historical policy, token single-use, tampered/unknown token rejected |
| Feature | `tests/Feature/Auth/GoogleLoginTest.php` | button/route hidden when unconfigured, new-account creation + welcome mail, refusal on an unverified Google e-mail (both linking and creating), linking only when verified, BUG-004 fill-if-empty, repeat login reuses the same account via `google_id` |
| Feature | `tests/Feature/Auth/EmailVerificationTest.php` | welcome + verification mail on registration without gating login, signed-link verification (success, hash mismatch, expiry/tamper), resend |
| Feature | `tests/Feature/Auth/LoginLockoutTest.php` | per-account lock after 5 attempts with exactly one notice mail, no lock/mail for an unknown account, the separate per-IP throttle still applies, the unblock link clears precisely its own IP/account pair |
| Feature | `tests/Feature/Projects/ProjectEditTest.php`, `ProjectWizardTest.php` | "Persönlich" contact allow/deny per verification state, "Über Nusszopf" unaffected |

Manually verified in a browser against the running dev stack (`compose.dev.yaml`): the login screen
(password eye-toggle, "Passwort vergessen" button, Google button correctly absent without
`GOOGLE_CLIENT_ID`/`SECRET`), the register tab (eye-toggle, newsletter checkbox scaffold), and the
forgot-password screen end to end.

## Remaining gaps

- No profile-page resend-verification affordance yet (slice 8) — the only path is the project
  wizard/edit error's own button.
- `BlockedAccountMail`'s dropped city/country clauses (decision 4) are a permanent self-hosting
  simplification, not a temporary gap — no geo-IP service is planned.
- Mail delivery through a real SMTP relay (P-13) is still only verified via `Mail::fake()`/Mailpit, not
  a production relay — same outstanding item the sixth slice already recorded.
- Playwright coverage for these journeys (reset via Mailpit, login lockout, Google via a provider
  stub) is not yet part of the `tests/E2E` suite — tracked for the parity/E2E pass (P-1), not blocking
  this slice's Feature-test-level verification.
