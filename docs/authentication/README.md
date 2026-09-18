# Authentication Specification

This document reconstructs the historical Nusszopf authentication product behavior from evidence in `../historical/web-nusszopf` (apps `auth-login`, `auth-password`, `webapp`, `e2e`) and `../historical/be-nusszopf` (`auth0/rules`, `docs/auth0`), cross-referenced with `../historical/emails-nusszopf`.

Nusszopf 2 replaces Auth0 with Laravel-native authentication. This document separates **product behavior to reproduce** from **Auth0-specific mechanics to translate**, per `CLAUDE.md` §8.

## 1. Historical architecture (Confirmed)

Historical Nusszopf authentication was split across **four deployable units**, not one app:

| Unit | Repo path | Role |
|---|---|---|
| `webapp` | `web-nusszopf/projects/webapp` | Main Next.js app. Owns `/api/login`, `/api/logout`, `/api/callback`, `/api/me`, `/api/session` (via `@auth0/nextjs-auth0`). Renders protected/public pages. |
| `auth-login` | `web-nusszopf/projects/auth-login` | Standalone Next.js app, deployed separately, used as an Auth0 **Universal Login custom page**. Renders the Login/Register tabs and the "forgot password" sub-view. Talks to Auth0 directly via `auth0-js` (`WebAuth`), not to the Nusszopf backend. |
| `auth-password` | `web-nusszopf/projects/auth-password` | Standalone Next.js app used as Auth0's **password-reset custom page**. Reads hidden `auth0-csrf` / `auth0-ticket` / `auth0-email` fields injected by Auth0 into the page and posts the new password to Auth0's `/lo/reset` endpoint. |
| `be-nusszopf` Auth0 Rules | `be-nusszopf/auth0/rules/*.js` | Server-side hooks that run on every Auth0 login/signup: mint Hasura JWT claims, lazily provision the Postgres `users` row, and copy a social-login avatar. |

Evidence: `web-nusszopf/projects/auth-login/src/pages/index.js` reads Auth0 Universal Login query params (`client`, `audience`, `redirect_uri`, `response_type`, `scope`, `state`) and constructs an `auth0-js` `WebAuth` instance from them — this only makes sense if the app is embedded as an Auth0-hosted custom login page. `web-nusszopf/projects/auth-password/src/pages/index.js` posts to the literal path `/lo/reset`, which is Auth0's classic hosted password-reset submission endpoint.

**Translation for Nusszopf 2 (Inferred):** the three-app split existed only because Auth0 requires separately-hosted custom UI pages. With Laravel-native auth there is no such constraint — login, registration, and password reset become ordinary routes/controllers inside the single Laravel monolith. The **visual layout, copy, validation rules, and UX sequencing** documented below must be preserved; the **multi-app deployment split** must not be.

## 2. Registration (Confirmed)

Screen: combined Login/Register view with a two-tab switcher (`Tab` component, labels "Einloggen" / "Registrieren"), reached from the `webapp` header's login button. Evidence: `auth-login/src/pages/index.js`, `auth-login/src/assets/data/page.data.js` (`tab: ['Einloggen', 'Registrieren']`).

Fields and validation (`auth-login/src/containers/SignUpForm/SignUpForm.js`, `forms.data.js`):

| Field | Rule | Error copy (German) |
|---|---|---|
| `username` | required; no whitespace (`/^\S*$/`); max 15 chars | "Gib einen Username ein" / "Keine Leerzeichen" / "Maximal 15 Zeichen" |
| `email` | required; valid email format | "Gib eine E-Mail-Adresse ein" / "Keine valide E-Mail-Adresse" |
| `password` | required; min 8 chars; ≥1 lowercase; ≥1 uppercase; ≥1 digit; ≥1 of `!@#$%^&*` | one message per rule, e.g. "Mindestens 8 Zeichen" |
| `privacy` (checkbox) | must be checked (`oneOf([true])`) | "Stimme den Datenschutzbestimmungen zu"; links to `https://nusszopf.org/privacy` |
| `newsletter` (checkbox) | optional, unchecked by default | — |

A source comment ties the password rule directly to Auth0 config: `// Auth0 Password Strength: Dashboard/Authentication/Database/PasswordPolicy` (`SignUpForm.js:26`). **This is the actual password policy to reproduce** — it is not implied by app code alone, it mirrors a dashboard setting, so the five composed rules above are the full policy (Confirmed via the client-side mirror; the authoritative Auth0-side policy itself is Unknown since the dashboard config isn't in the repo).

Submit calls `webAuth.redirect.signupAndLogin` with `connection: 'Username-Password-Authentication'`, `username`, `email`, `password`, and `user_metadata: { newsletter, isTestUser }` (`auth-login/src/pages/index.js:137-169`). On success, Auth0 performs the OIDC redirect itself (no explicit client-side redirect code — the comment `// redirect` marks where Auth0 takes over). On failure, a toast error is shown and the invisible captcha (rendered via `webAuth.renderCaptcha`, only visible if Auth0's bot-detection considers the attempt risky) is reloaded. Two distinct error messages exist: a generic failure and, specifically for HTTP 400 (`SignUpForm.js` caller logic in `pages/index.js:157`), "Der Username existiert leider schon" (username already taken) — so **duplicate username at signup is a distinguished, product-visible error state**, distinguished from generic failure.

**E2E evidence** (`web-nusszopf/projects/e2e/cypress/integration/_auth.spec.js`): after successful registration the browser lands on `https://web.dev.nusszopf.org/user/projects` — i.e. straight into the authenticated app, **no interstitial and no email-verification gate**. No email-verification template exists in `emails-nusszopf/src/auth0` (only `welcome`, `change-password`, `blocked-account`, `password-breach-alert`) and no Auth0 rule blocks unverified users. **Confirmed: historical Nusszopf does not require email verification before use.** Registering sends the `welcome` email (see `docs/email/README.md`).

**Side effect (Confirmed via `be-nusszopf/auth0/rules/syncWithHasura.js`):** on first login after signup, a rule lazily inserts the Postgres `users` row (`insert_users ... on_conflict: constraint user_pkey, update_columns: []` — idempotent, no-op if the row already exists) and, only if `user_metadata.newsletter === "true"`, fires a signed-JWT POST to `/api/newsletter` to subscribe the user's email to the newsletter. This means **checking "newsletter" at signup enrolls the user in the newsletter as a side effect of first authentication**, decoupled from the signup submission itself. For Nusszopf 2 this JIT-provisioning mechanic is Auth0-specific plumbing to discard, but the **newsletter opt-in-at-signup product behavior** must be reproduced (as a direct side effect of registration, since there is no more separate identity provider to defer it to).

## 3. Login (Confirmed)

Same screen, first tab. Fields: `emailOrName` (required, no format validation — **login accepts either username or email in one field**, copy: "E-Mail-Adresse / Username") and `password` (required only, no strength check on login). Submit calls `webAuth.login({ realm: 'Username-Password-Authentication', username: emailOrName, password, captcha })`. Same generic-error/captcha-reload behavior as signup; Auth0 owns the success redirect.

Social login buttons: **Google** (enabled, `webAuth.authorize({ connection: 'google-oauth2' })`) and **Apple** (rendered but `disabled`, with a `// todo: create auth0-apple connection` comment in `auth-login/src/pages/index.js:115`). **Confirmed: Apple login was UI-present but never wired up — an incomplete feature, not a supported historical capability.** Password/username login and Google login are the only functional authentication methods.

**E2E evidence** confirms login redirects to `/user/projects`, and logout redirects to `/` (site root), both on `web.dev.nusszopf.org` (the `webapp` origin, not the auth apps) — so **all post-auth navigation lands back on the main webapp**, never on the auth sub-apps.

### Session / protected routes (Confirmed, `webapp`)

- `webapp` uses `@auth0/nextjs-auth0` (`utils/libs/auth0.js`): OIDC session cookie, `rollingDuration: 8 hours`, callback route `/api/callback`, `postLogoutRedirect: '/'`.
- `/api/login` → `auth0.handleLogin(req, res, { returnTo: '/user/projects' })`.
- `/api/logout` → `auth0.handleLogout` (clears session cookie and calls Auth0's logout endpoint).
- `/api/me` → `auth0.handleProfile` (returns the Auth0 profile of the current session; used by `AuthContextProvider.fetchUser`).
- `/api/session` → returns the current session's raw access token.
- Client-side gate: `utils/hoc/withAuth.js` wraps each page with `{ isAuthRequired }`. On mount it calls `initUser`, which calls `/api/me`; if `isAuthRequired` and there is no user, it hard-redirects (`window.location.href`) to `/api/login` — i.e. **route protection is enforced client-side after page load, not via SSR/middleware**, so an unauthenticated visitor briefly sees the page shell before being redirected (an Unknown/likely-flash-of-unstyled-content: the historical implementation is not SSR-gated).

**Confirmed protected pages** (`isAuthRequired: true`, from `grep` of `withAuth(...)` call sites):
- `/user/profile`
- `/user/projects`
- `/user/project/create`
- `/user/project/[id]/edit`

**Confirmed public pages** (`isAuthRequired: false`, still passed through `withAuth` for optional user context): `/`, `/search`, `/projects/[id]`, `/privacy`, `/legalNotice`, `/legalPolicy`, `/newsletter/subscribe/[token]`, `/newsletter/unsubscribe/[token]`, `/newsletter/unsubscribe/lead`.

There is no visible "remember me" control anywhere in the login form; the 8-hour rolling session is the only observed session-length behavior (Confirmed).

## 4. Password reset / change (Confirmed)

Two distinct flows exist and must not be conflated:

1. **"Forgot password" (request a reset link)** — inline sub-view of the `auth-login` app, reached via the "Passwort vergessen" button under the login form (`auth-login/src/pages/index.js`, `Views.password`). Single field: `email` (required, valid format). Submit calls `webAuth.changePassword({ connection: 'Username-Password-Authentication', email })`. On success shows toast "E-Mail verschickt!" ("Email sent!") and stays on the same screen (no redirect); a "Abbrechen" (cancel) button returns to the login/signup tabs. This triggers Auth0's `change-password` email (see `docs/email/README.md`), titled "Nusszopf – Neues Passwort erstellen".
2. **"Set new password" (consume the reset link)** — the separate `auth-password` app, which the `change-password` email presumably links to. Single field: new `password`, with the **identical strength policy** as signup (min 8, lower, upper, digit, special char from `!@#$%^&*`). Copy: title "Neues Passwort erstellen", description "Mit deinem neuen Passwort kannst Du dich wie gewohnt einloggen." On submit it reads Auth0-injected hidden fields (`auth0-csrf`, `auth0-ticket`, `auth0-email`) from the DOM and POSTs `{ newPassword, confirmNewPassword, _csrf, ticket, email }` to `/lo/reset`. On success: toast "Passwort geändert! Weiterleitung zum Login." ("Password changed! Redirecting to login.") then, after a 1.5s delay, a hard redirect to `${DOMAIN}/api/login` (i.e. back into the `webapp`'s login route). On failure: generic error toast, no retry-count limiting observed client-side.

There is **no separate "change password while logged in" screen** anywhere in `webapp` (no such route among the grepped auth/session files) — changing password historically always goes through the same "forgot password" email-link flow, even for an authenticated user. Mark this **Confirmed by absence**: do not invent an in-app change-password settings screen unless later evidence (e.g. `docs/design/screens.md` profile screen archaeology) contradicts this.

## 5. Logout (Confirmed)

Single action, triggered from the header (`btn_logout_nav-header` in E2E). Client calls `AuthContext.logout()` (`webapp/src/utils/services/auth.service.js`), which clears the cached `window.__user` and hard-redirects to `/api/logout`, which in turn calls `auth0.handleLogout` and redirects to `/` per `postLogoutRedirect`. No confirmation dialog, no "logged out" toast/page observed.

## 6. Account lifecycle & security notices (Confirmed, translate-not-copy)

Three Auth0-side behaviors surface as email notifications only (full template content owned by `docs/email/README.md`; cross-referenced here only for the triggering behavior):

- **IP blocking** (`blocked-account.mjml`, title "Nusszopf – IP-Adresse blockiert"): Auth0's brute-force protection blocks an **IP address** (not the account) after repeated failed attempts and emails the account owner. This is Auth0 Attack Protection, not custom Nusszopf logic — **Inferred** that Nusszopf 2 needs equivalent brute-force throttling (e.g. Laravel's login rate limiting) to preserve the protection, but the exact threshold/duration is Unknown (owned by Auth0 tenant config, not present in either repo).
- **Breached-password alert** (`password-breach-alert.mjml`, title "Nusszopf – Sicherheitshinweis"): Auth0's breached-password-detection feature. **Inferred** equivalent: a Laravel-side check against a breached-password service (e.g. HaveIBeenPwned-style validation) on password set — Unknown whether this is in scope for the vertical-slice rewrite; record as an open question.
- **Welcome email** (`welcome.mjml`): sent on successful signup, unconditionally (no verification gate, see §2).

### JWT claims / roles (Confirmed, Auth0-specific mechanic)

`be-nusszopf/auth0/rules/hasuraIdToken.js` stamps every token with Hasura claims: `x-hasura-default-role: "user"`, `x-hasura-allowed-roles: ["user", "anonymous"]`, `x-hasura-user-id`, `username`. Only two roles are visible in this rule: `user` (authenticated) and `anonymous` (unauthenticated/public GraphQL role). **Confirmed for this narrow mechanic only** — the full historical permission model (any additional roles, per-resource authorization) is owned by `docs/domain/permissions.md`, not this document; do not treat "two roles" as the complete picture without cross-checking that spec.

### Social-login avatar sync (Confirmed, translate-not-copy)

`be-nusszopf/auth0/rules/userPicture.js`: for social-connection users only (`user_id` containing `google` or `apple`), Auth0 copies the provider's profile picture URL into the Postgres `users.picture` column on every login, fire-and-forget (swallows errors). **Product behavior to reproduce:** a user who registers/logs in via Google should have their avatar auto-populated from their Google profile if they have no picture set; **mechanic to discard:** the Auth0-rule plumbing and the Hasura admin-secret mutation.

## 7. Migration mapping (Inferred — architecture guidance, not yet an approved decision)

| Auth0/Next.js mechanic | Laravel-native equivalent |
|---|---|
| Auth0 Universal Login custom page (`auth-login`) | Laravel `AuthenticatedSessionController` + Blade/Livewire view, single app, no separate deployment |
| Auth0 hosted password-reset page (`auth-password`) | Laravel's built-in password-reset flow (`Illuminate\Auth\Passwords`), Blade/Livewire view |
| `auth0-js` `WebAuth.login` / `.redirect.signupAndLogin` / `.changePassword` | Laravel `Auth::attempt`, `User::create`, `Password::sendResetLink` |
| `@auth0/nextjs-auth0` OIDC session cookie, 8h rolling | Laravel session-based auth guard; reproduce the 8-hour rolling session lifetime as a documented, deliberate choice (not a Laravel default) |
| `withAuth` client-side redirect gate | Laravel `auth` middleware (server-side, avoids the historical flash-before-redirect) — **this is a demonstrable improvement over history**, record in `docs/rewrite/intentional-changes.md` when implemented |
| Auth0 rule: lazy `users` row provisioning on first login | Ordinary `User::create()` at registration time — no more JIT provisioning gap |
| Auth0 rule: newsletter opt-in side effect | Direct side effect of the registration action itself |
| Auth0 rule: Hasura JWT claims (`user`/`anonymous` roles) | Laravel policies/gates + guest access, per `docs/domain/permissions.md` |
| Auth0 Attack Protection (IP block) | Laravel rate limiting on auth routes (`throttle` middleware) — exact thresholds Unknown, needs an explicit decision |
| Auth0 breached-password detection | Optional: a Laravel password-breach check — needs an explicit decision, not assumed in scope |
| Google social login | Laravel Socialite (Google provider) |
| Apple social login (button existed, never wired) | **Do not implement** unless separately approved — historically incomplete, not a real historical capability (see `docs/rewrite/open-questions.md`) |
| Invisible Auth0 bot-detection captcha | Needs an explicit decision (e.g. hCaptcha/Turnstile, or rely on rate limiting) — Unknown what, if anything, replaces it |

## 8. Open items requiring a decision (cross-reference `docs/rewrite/open-questions.md`)

- No email verification was ever enforced historically (Confirmed) — approve whether Nusszopf 2 preserves this (product-fidelity default) or treats it as a security gap to close (would be a product change, not a bug fix, since nothing here is "broken").
- The exact Auth0 password policy is only known via its client-side mirror (5 composed rules); the authoritative dashboard configuration is Unknown.
- Apple login was incomplete/disabled historically — confirm it should not be reproduced.
- IP-block thresholds and breached-password-check scope are Unknown and were enforced by Auth0 platform features, not app code.
- Whether the 8-hour rolling session duration should be preserved exactly or is an arbitrary historical default is Unknown.
