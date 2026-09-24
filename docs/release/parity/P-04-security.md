# P-4 Security review (2026-09-24)

Exit evidence (`master-roadmap.md` §4):

- an authorization-matrix coverage report;
- upload, mail and token abuse tests;
- a review of the security headers and the CSP;
- `composer audit` and `npm audit` clean or triaged;
- a review of secret handling;
- `/up` and the debug surfaces reviewed for production.

The bar is §7.2 item 8: "no open High/Medium finding; residual risks are documented".

Scope: the whole application at commit `6061145` plus this phase's changes. That covers routes, Livewire
components, policies, mails, tokens, uploads, configuration, both Docker images, the nginx configuration and the
operator templates. The historical findings (`docs/security/README.md`, BUG-001/002/010/016/020/029/031) were
re-checked against the implementation and still hold.

Every finding below except BUG-046 is a defect of Nusszopf 2 itself, not historical behaviour: SEC-01 to SEC-11 are
this page's identifiers. BUG-046, the historical absence of security headers, is in `docs/rewrite/bugs.md`.

## Findings

| ID | Finding | Severity | Kind | State |
|---|---|---|---|---|
| SEC-01 | Mailed links take their host from the request's `Host` header: password-reset takeover | **High** | Confirmed, reproduced | Fixed |
| SEC-02 | Production template trusts `X-Forwarded-For` from anyone (`TRUSTED_PROXIES=*`, port on `0.0.0.0`) | Medium | Confirmed | Fixed |
| SEC-03 | "Passwort vergessen" has no per-IP limit: one client can mail every account | Medium | Confirmed | Fixed |
| SEC-04 | Newsletter confirmation/unsubscribe mails are unlimited per recipient across senders | Low | Confirmed | Fixed |
| SEC-05 | Avatar upload decodes before checking dimensions: a 157-byte PNG kills the PHP worker | Medium | Confirmed, reproduced | Fixed |
| SEC-06 | No CSP, Referrer-Policy or Permissions-Policy; PHP and nginx versions disclosed; `/storage/` loses `nosniff` | Low | Confirmed | Fixed (BUG-046) |
| SEC-07 | Unused signed `storage/{path}` GET/PUT routes on the private disk | Low | Hardening | Fixed |
| SEC-08 | Search's page count is client-writable: one request can fetch the whole index | Low | Confirmed | Fixed |
| SEC-09 | Login by "e-mail or username" picks an arbitrary account when a username equals another account's address | Low | Confirmed | Fixed |
| SEC-10 | A password reset leaves every other signed-in session valid | Medium | Confirmed | Fixed (approved by the maintainer 2026-09-24) |
| SEC-11 | Toasts insert their message as HTML | Info | Hardening (latent sink, not exploitable today) | Fixed |

Nothing High or Medium remains open.

### SEC-01 — Host-header poisoning of the links in mails (High)

- **Found:** no absolute URL was pinned to `APP_URL`. Laravel builds absolute URLs from the current request.
  nginx answers any `Host` (`server_name _`), so a mail triggered during a visitor's request carries a link on
  whatever host that visitor claimed.
- **Reproduced** against the development stack: a "Passwort vergessen" request for `demo@nusszopf.test` sent with
  `Host: evil.example`. Mailpit showed the account owner a genuine mail whose link was
  `http://evil.example/password/reset/<valid token>?email=…`. One click by the owner hands the token to the attacker,
  which is an account takeover.
- **Same pattern elsewhere:**
  - the verification link, which the attacker could use to verify an account registered on the victim's address;
  - the blocked-account "Das bin ich!" link;
  - the newsletter confirmation and unsubscribe links, which allow a consent forged on the victim's behalf.
- **Fix:** `AppServiceProvider` forces the root URL and scheme to `APP_URL` in every environment except `local`.
  The development stack is exempt because its in-container browser tests reach the app as `http://web`
  (`compose.dev.yaml`). `docs/deployment/README.md` now says that `APP_URL` must be the exact public address.
- **Tests:** `tests/Feature/Security/HostHeaderTest.php` covers the reset, verification, newsletter and signed links,
  and a page requested under a foreign host. All five tests fail without the fix. The smoke test also checks the
  production images: a page requested with `Host: evil.example` must not mention that host.

### SEC-02 — `X-Forwarded-For` trusted from anyone (Medium)

- **Found:** `.env.production.example` set `TRUSTED_PROXIES=*` and `APP_BIND=0.0.0.0`. A client that reaches the
  published port directly can name any address in `X-Forwarded-For`. It then escapes every per-IP limit: login (5/min),
  registration, contact, newsletter and forgotten password. The per-account login lock still holds.
- **Fix:** the template trusts loopback and the private networks only
  (`127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7`). That covers a proxy on the same host, which
  reaches the container through Docker's private bridge, and a proxy on the LAN. A proxy with a public address must be
  listed. `docs/deployment/README.md` explains this and warns against `*`. No release exists yet, so no installed
  `.env` needs migrating.
- **Tests:** `tests/Feature/Security/TrustedProxiesTest.php` parses the shipped template and runs Laravel's
  `TrustProxies`. The template has no wildcard, a direct client cannot spoof its address, and a bridge, loopback or LAN
  proxy is believed. The smoke test runs the production stack behind this default.

### SEC-03 — No per-IP limit on "Passwort vergessen" (Medium)

- **Found:** the password broker spaces mails to one address (60 s), but nothing limited a sender. One client could
  have a reset mail sent to every registered address, once a minute each. Auth0, whose limits covered this
  historically, is gone.
- **Fix:** 10 requests per IP per 15 minutes, the budget of the other public forms. When the limit is reached, the
  field shows "Zu viele Versuche. Bitte warte kurz." (the login and registration copy). Invalid input does not count.
  The answer still names no address, so the form stays enumeration-safe. The threshold is Claude-decidable under
  roadmap B-7; see `docs/rewrite/intentional-changes.md`.
- **Tests:** `tests/Feature/Auth/PasswordResetTest.php`: the limit, the budget returning after 15 minutes, and invalid
  input not spending it.

### SEC-04 — Newsletter mails unlimited per recipient (Low)

- **Found:** the newsletter forms are limited per IP (historical, Preserve), but a sender that changes its address could
  mail one person a confirmation or unsubscribe link as often as it liked.
- **Fix:** at most 3 confirmation mails and 3 unsubscribe mails per address per hour, counted case-insensitively
  (`Newsletter::MAILS_PER_ADDRESS_PER_HOUR`). Past the cap the form answers exactly as before and simply sends nothing,
  so the answer still reveals nothing (BUG-032/033).
- **Tests:** `tests/Feature/Newsletter/SubscribeTest.php` and `UnsubscribeTest.php` ("… at most three … per hour").

### SEC-05 — Avatar decompression bomb (Medium)

- **Found:** `AvatarUploader` handed the upload to GD's `imagecreatefromstring` first. GD allocates the whole bitmap
  from the dimensions in the header.
- **Reproduced:**
  - a 389 KB PNG declaring 20000×20000 pixels ended PHP with `Allowed memory size of 268435456 bytes exhausted`;
  - a crafted 157-byte PNG does the same.

  Both pass the `image` and `max:5120` rules. A fatal error cannot be caught: the request dies with a 500 after holding
  a worker and 256 MB. Any registered user could repeat it.
- **Fix:** the header is read first (`getimagesizefromstring`). Anything above 4096 px on a side, or unreadable, is
  refused before decoding (`AvatarUploader::MAX_SOURCE_DIMENSION`) with the existing toast "Bild konnte nicht
  gespeichert werden.". The crop dialog uploads 512×512, so no genuine upload is affected.
- **Tests:** `tests/Feature/Profile/AvatarUploadTest.php`:
  - the 157-byte flood through the real Livewire upload, a test that would crash the test process on regression;
  - a real 4097×10 image refused, and a 4096×10 one accepted.

### SEC-06 — Security headers (Low; BUG-046)

- **Found:**
  - pages sent only `X-Content-Type-Options` and `X-Frame-Options`, added by nginx;
  - no Content-Security-Policy, Referrer-Policy or Permissions-Policy;
  - `X-Powered-By: PHP/8.5.10` and `Server: nginx/1.31.6` named exact versions;
  - nginx's `/storage/` location has its own `add_header`, so it silently dropped both inherited headers for uploaded
    files.

  The historical app sent no security headers at all: `web-nusszopf` has no `headers()` or CSP (BUG-046).
- **Fix:** `App\Http\Middleware\SecurityHeaders` runs on every response, `/up` and `/health` included. It sends
  `nosniff`, `SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, a Permissions-Policy denying camera,
  microphone, geolocation, payment and USB, and this policy:

  ```
  default-src 'self'; script-src 'self' 'unsafe-eval'; style-src 'self' 'unsafe-inline';
  img-src 'self' <APP_URL origin> data: https://*.googleusercontent.com; font-src 'self'; connect-src 'self';
  object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'
  ```

  - **No inline scripts.** The one inline script (the toast flashed across a redirect) now travels as a
    `data-flash-toast` attribute. The two inline `onclick` handlers (Privacy's "Zurück" and the project banner's close
    button) are delegated listeners in `resources/js/app.js`. An injected `<script>` or `on…=` attribute therefore
    cannot run.
  - **`'unsafe-eval'`** is required. Livewire's bundled Alpine evaluates the ~100 `x-on`/`wire:` expressions in the
    views with `new Function`. The E2E check below shows every one of them failing without it.
  - **`'unsafe-inline'` for styles** covers the `style` attributes that Livewire, Alpine and the views set.
  - **`img-src`:** the `APP_URL` origin sits next to `'self'` because avatar URLs come from the public disk's
    `APP_URL`. Google's avatar host is there for BUG-004.
  - **Vite dev server:** the policy is left out while it runs.

  nginx: `server_tokens off`, and the two headers now sit in each static-file location, including `/storage/`, instead
  of at server level, so PHP responses do not carry them twice. PHP: `expose_php = Off`.
- **No visible change:** the visual suite passed 69 of 69.
- **Tests:**
  - `tests/Feature/Security/SecurityHeadersTest.php`: headers on pages, errors, redirects, `/up`, `/health` and a
    Livewire update; the policy's key directives; no inline `<script>` or `on…=` on the guarded pages (it fails against
    the old views); the flashed toast escaped as data;
  - `tests/E2E/specs/security/csp.spec.ts`: an owner journey and a visitor journey on Chromium, Firefox and WebKit.
    It covers registration, the wizard with the rich-text editor and request dialog, the card menu, the banner, the
    request dialog, the avatar cropper and upload, a flashed toast, "Zurück", the search filter and the login links.
    It fails on any `securitypolicyviolation`, and removing `'unsafe-eval'` was confirmed to fail it;
  - the smoke test checks the production images: the headers present, `nosniff` sent only once, no `X-Powered-By`, no
    nginx version, and `nosniff` on uploaded files.

### SEC-07 — Unused `storage/{path}` routes (Low, hardening)

The private `local` disk had Laravel 12's `'serve' => true`, which registers signed `GET` and `PUT storage/{path}`
routes. Nothing uses them; the disk holds Livewire's temporary uploads; in production nginx answers `/storage/` itself.
Now `'serve' => false`. Test: `SecurityHeadersTest` ("exposes no download or upload route for the private disk") and
the route inventory below.

### SEC-08 — Client-writable search page count (Low)

`Search::$pages` was a public property, so a client could set it to 2000. One request then asked Meilisearch for
`50 × pages` hits, up to `maxTotalHits` (100000, `config/scout.php`), and rendered every one. It is `#[Locked]`
now: only "Mehr laden" raises it, one page per round trip, as for a real visitor. Test: `SearchPageTest` ("refuses a
page count set by the client…").

### SEC-09 — Ambiguous login lookup (Low)

A username only has to be free of whitespace, so it may look like an address (`a@b.de`). The login query
`email = x OR name = x` then returned whichever account came first. If a squatter registered someone's address as
their own username, the address owner could no longer log in with their address, and their failed attempts counted
against the squatter's lock. The address is now matched first, then the username. Test: `LoginTest` ("matches the
address before a username…"), which fails without the fix.

### SEC-10 — Sessions survive a password reset (Medium)

- **Found:** resetting the password did not end sessions signed in elsewhere. There is no remember-me, but the
  session lasts 8 hours and rolls. A stolen or forgotten session stayed valid after the owner reset the password
  because of it.
- **Fix:** Laravel's `AuthenticateSession` in the `web` group. A session whose stored password hash no longer
  matches is logged out on its next request (a redirect to login). Accounts without a password (Google-only) are
  unaffected.
- **Historical:** Auth0's behaviour on a password change is platform configuration that cannot be recovered
  (Unknown). It is a visible change on the other devices. **The maintainer approved it on 2026-09-24** as intentional
  security behavior; the unknown historical behavior does not block the decision (`docs/rewrite/intentional-changes.md`).
- **Tests:** `PasswordResetTest` ("ends every other signed-in session…", which fails without the fix; "keeps a session
  signed in while the password is unchanged").

### SEC-11 — Toast messages as HTML (Info)

`nzToast()` put its message into `innerHTML`. Every message is a fixed string today, and the operator's contact
address is the only configured part, so nothing is exploitable. The message is `textContent` now, so it can never
become a sink.

## Authorization matrix coverage

Every row of `docs/security/authorization-matrix.md`, its enforcement, and the tests that pin it. Rows marked
**new** were not pinned before P-4; their tests are in `tests/Feature/Security/AuthorizationCoverageTest.php`.

| Matrix row | Enforcement | Tests |
|---|---|---|
| Project: view public / private | `ProjectPolicy::view` → `Project::visible()`; 404, never 403 | `ProjectPolicyTest`, `ProjectDetailTest`, `ErrorPagesTest` ("answers a private project exactly like a missing one") |
| Project: list/search | `Project::visible()` on every read; index gate | `ProjectVisibilityScopeTest`, `MyProjectsTest`, `ProjectSearchTest`, `ProjectSearchIndexingTest`, `ProjectRequestSearchTest` |
| Project: create as self | `user_id` from the session | `ProjectWizardTest` ("always creates the project for the caller …"), `ProjectPolicyTest` |
| Project: update / toggle visibility | `ProjectPolicy::update` | `ProjectPolicyTest`, `ProjectEditTest` (404 for a non-owner), `MyProjectsTest` ("denies toggling another user's project") |
| Project: delete | `ProjectPolicy::delete` | `ProjectPolicyTest`, `ProjectEditTest`, `MyProjectsTest` |
| Project: reassign owner | `user_id` not fillable; bound model not client-settable | **new:** "never lets a project change its owner", "does not let the client point an owner's edit screen at another user's project" |
| Project: view counter (BUG-001) | server-side increment only | `ProjectAnalyticsTest` ("exposes no route that lets a caller write the counter directly") |
| Project: contact counter (BUG-017) | not reproduced | `ProjectAnalyticsTest`; route inventory |
| Project: "Persönlich" only when verified | `contactAllowed()` | `ProjectEditTest`, `ProjectWizardTest` |
| Request: view / list (BUG-002) | `ProjectRequestPolicy::view` → `ProjectRequest::visible()` | `ProjectRequestAuthorizationTest`, `ProjectRequestSearchTest` ("never indexes the requests of a private project") |
| Request: create / update / delete | `ProjectRequestPolicy` | `ProjectRequestAuthorizationTest` (allow and deny per row, guest included), `ProjectRequestEditTest` |
| Request: move to another project | `project_id` not fillable | `ProjectRequestModelTest` ("cannot be moved … by mass assignment"), `ProjectRequestAuthorizationTest` |
| User: view name and avatar | public | `ProjectDetailContentTest`, search hit tests |
| User: view e-mail | never serialized for others (`#[Hidden]`) | `ContactFormTest` ("never renders the owner's e-mail …"); **new:** "never shows an account's e-mail address to anyone else" (detail page, sitemap, search page and component, a stranger's My Projects, model and search-document serialization) |
| User: register | `guest` middleware; per-IP limit | `RegistrationTest`, `RouteProtectionTest` |
| User: update own name | no path exists | route inventory (no such route); no Livewire action |
| User: avatar | `UserPolicy::update` | `AvatarUploadTest` ("gates avatar upload to the account itself") |
| User: delete own / another account | `UserPolicy::delete`; no admin path | `DeleteAccountTest`; route inventory |
| Lead: subscribe (form, registration, Profile) | pending lead, double opt-in, per-IP and per-address limits | `SubscribeTest`, `RegistrationTest`, `ProfileNewsletterTest` |
| Lead: confirm / unsubscribe by token | HMAC token, bound to the lead and the purpose, 7 days | `NewsletterTokenTest`, `ConfirmationPagesTest` |
| Lead: unsubscribe by address / Profile | neutral answer; own address only | `UnsubscribeTest`, `ProfileNewsletterTest` |
| Lead: read / list | Profile reads its own lead; export is CLI only | `ProfileNewsletterTest`, `ExportSubscribersTest`; route inventory |
| Search | index-time gate plus query-time scope | `ProjectSearchTest` ("drops the documents of a project that is private or gone"), `ProjectRequestSearchTest` |
| Contact via dialog | public, per-IP limited, validated, Reply-To | `ContactFormTest`; **new:** "does not let the client point a public project page at a private project …", "mails a contact to the owner without showing the owner's address …" |
| Contact via `mailto:` | link only | `ProjectDetailContentTest` ("links the contact button to the owner's address for a personal contact") |
| Direct URLs / API endpoints | none beyond the web routes | **new:** "exposes exactly the known HTTP entry points, each behind its documented gate": the complete route list, each with its `auth`/`guest`/`signed`/`throttle` gate. A new route fails the test until it is added there and to the matrix |
| Signed mail links (unblock, verification) | `signed` middleware plus the target's own check | `LoginLockoutTest` ("unblocks exactly the IP/account pair the link was signed for"), `EmailVerificationTest` ("rejects an expired or tampered verification link", "… whose hash does not match …") |
| Google login | Socialite state; link or create only for a Google-verified address | `GoogleLoginTest` |

While writing the coverage tests, one suspicion was ruled out. A client update of a component's bound `Project`
(`set('project', …)`) is silently ignored by Livewire, and the component keeps its own project. The two "does not
let the client point …" tests pin this.

## Abuse tests: upload, mail, tokens

| Surface | Abuse | Test |
|---|---|---|
| Avatar upload | a file that only claims to be an image | `AvatarUploadTest` ("rejects a non-image upload …") |
| | an oversized or non-square image | "center-crops and caps …" |
| | a pixel flood | **SEC-05** tests |
| | another account | "gates avatar upload …" |
| | Livewire's temporary upload | `image`, `max:5120`, 12 MB and `throttle:60,1` Livewire defaults; nginx 10 MB body; only an authenticated component offers uploads |
| Contact mail | header injection | `ContactFormTest` ("rejects an e-mail address carrying a header-injection attempt …") |
| | flooding | "rate-limits contact submissions per IP …" |
| | a foreign request id | "ignores a request id that does not belong to the project …" |
| Registration mails | mass sign-up | `RegistrationTest` (10 per IP per 15 min) |
| Reset mails | flooding | **SEC-03** tests; broker spacing per address |
| Newsletter mails | flooding by IP | `SubscribeTest`/`UnsubscribeTest` (per IP) |
| | flooding by rotating IPs | **SEC-04** tests |
| Lockout mail | repeated sending | `LoginLockoutTest` ("… e-mails only its owner, once") |
| Verification resend | flooding | `throttle:6,1`, own address only (`EmailVerificationTest`) |
| All mailed links | host poisoning | **SEC-01** tests |
| Newsletter tokens | tampering, other purpose, other instance, expiry | `NewsletterTokenTest` |
| | an old link against a later lead | `ConfirmationPagesTest` |
| Password-reset token | single use, strength policy | `PasswordResetTest` ("… invalidates the token") |
| Signed links | tampering, expiry | `EmailVerificationTest`, `LoginLockoutTest` |
| Sessions | survival after a reset | **SEC-10** tests |
| | the login lookup | **SEC-09** test |

## Dependency audit (2026-09-24)

- `composer audit`: "No security vulnerability advisories found." No abandoned packages were reported.
- `npm audit`: "found 0 vulnerabilities".

Nothing needed triage. These audits do not run in CI yet; see recommendations.

## Secret handling

- **Repository:**
  - `.env` is ignored (`.gitignore`) and excluded from the image build context (`.dockerignore`), so no working-copy
    secret can reach a published image.
  - A pattern scan of the whole Git history (AWS, GitHub, SendGrid, Google, Stripe and Slack tokens, private keys, and
    non-empty `*_PASSWORD`, `*_SECRET`, `HEALTH_TOKEN` and `MEILISEARCH_KEY` assignments) found only the development
    defaults (`DB_PASSWORD=nusszopf`, `MAIL_PASSWORD=null`).
  - `tests/Visual/historical-harness/hist.env` holds throwaway values for the local historical harness only.
  - P-15 repeats the history check with a dedicated scanner.
- **Installation:** `install.sh` generates `APP_KEY`, the database password, the Meilisearch key and `HEALTH_TOKEN`
  with `openssl rand`. It writes `.env` with mode 600 and never overwrites an existing one. Compose refuses to start
  without the required secrets.
- **Runtime:**
  - PostgreSQL, Redis and Meilisearch publish no port in the operator stack; only `web` does.
  - The Meilisearch master key is used server-side only, since search runs through Livewire.
  - `/health` compares its token with `hash_equals` and gives details only with it.
  - Session cookies are `HttpOnly`, `SameSite=Lax` and `Secure` (`SESSION_SECURE_COOKIE=true` preset).
  - Logs go to stderr at `warning`.
- **Key use:** `APP_KEY` encrypts cookies and signs the newsletter tokens (`NewsletterToken`, HMAC). Rotating it
  invalidates open newsletter links (7 days), as it invalidates sessions. `.env.production.example` already says to keep
  the key with the backups.

## Production `/up` and debug surfaces

- **Debug output:** `APP_DEBUG=false` in the template. The smoke test requests an unknown page on the production
  images and checks for a 404 with the Nusszopf error page and no stack trace or `vendor/laravel`. Uncaught exceptions
  render the error page with status 500 (`ErrorPagesTest`).
- **Debug tooling:** none installed in production. `laravel/pail` and `laravel/pao` are dev dependencies
  (`composer install --no-dev` in the image). Telescope, Debugbar and Ignition are not installed.
- **`/up`:** Laravel's liveness page. It is static, touches no dependency and reveals nothing. It is the `web`
  container's healthcheck and stays. Its markup names two CDN resources (a Tailwind script and a font), which the CSP
  now blocks; nobody reads the page in a browser.
- **`/health`:** status only without the token; version and checks with it (`HealthTest`).
- **Livewire routes:**
  - `update` carries CSRF and the checksummed snapshot;
  - `upload-file` and `preview-file` need signed URLs, which only the authenticated Profile component issues;
  - the asset routes are static.
- **Other routes:** `storage/{path}` removed (SEC-07). The complete route list is pinned by the inventory test.
- **Versions:** no version in HTTP headers (SEC-06). The version stays available to the operator: `/health` with the
  token, `nusszopf:health` and the image label. `/health` with a token was deliberately kept (`docs/rewrite/architecture-decisions.md`,
  "Version exposure to operators").
- **nginx:** denies dotfiles. `.env` and `.git` are not in the images at all.

## Recommendations and residual risks (not defects)

- **`'unsafe-eval'`** stays in the CSP. Livewire's CSP-safe build would need all ~100 Alpine expressions rewritten into
  its restricted syntax. This is a post-1.0 opportunity: the policy already blocks inline and foreign scripts.
- **HSTS** belongs to the operator's TLS proxy, not the app. A mistaken long `max-age` sent by the app could lock an
  instance out of plain HTTP. Recommended in `docs/deployment/README.md`.
- **Dependency audits in CI:** run `composer audit` and `npm audit`, or Dependabot/Renovate (P-15), so a new advisory
  becomes visible without a manual run.
- **Development stack:** `APP_ENV=local` does not pin URLs to `APP_URL` (SEC-01), so its in-container browser tests
  can use `http://web`. That is development only; the production images are covered by the smoke test.
- **Contact flooding:** a project owner can still receive contact messages from senders on changing addresses, up to 10
  per 15 minutes per sender. This is historical and preserved.
- **Username enumeration:** registration still names a taken username. This is historical (`cms.notify.error[1]`) and
  preserved. Login and "Passwort vergessen" answer neutrally.
- **Login timing:** only an existing account's password hash is checked, so response time differs slightly for
  unknown accounts. This is Laravel's standard behaviour, and the per-IP and per-account limits bound it.
- **Redis:** Redis runs without a password on the stack's internal network, and no port is published. An operator who
  publishes it must add one.
- **Proxy trust:** a proxy outside the private ranges must be listed in `TRUSTED_PROXIES`. Otherwise the signed links
  in mails fail, because the app sees `http` where the link says `https`. This is documented.

## Verification

- **Pest:** 601 passed (serial, as `composer test` and CI run it). **Pint:** clean. **Larastan:** no errors.
- **Playwright,** full suite on Chromium, Firefox and WebKit, with Mailpit: 165 passed, 0 failed. The 12 skipped are
  skipped by design: the search runs gated on environment variables and the axe spec's Firefox/WebKit runs. The CSP
  spec contributes 6 of the 165.
- **Visual regression:** 69 of 69, unchanged baselines.
- **Smoke test:** `sh scripts/smoke-test.sh` builds both production images, installs through `install.sh` with the new
  `TRUSTED_PROXIES` default and passes. It includes the new P-4 step: headers, no version disclosure, no duplicate
  headers, `nosniff` on uploads, and no link built from a forged Host.
- **Audits:** `composer audit` and `npm audit` are clean (above).

## Status

**Done — formally closed by the maintainer on 2026-09-24**, with the evidence and documentation above.

- Every finding is fixed with a regression test.
- Nothing High or Medium is open (§7.2 item 8).
- The residual risks are listed above.
- SEC-10 was approved by the maintainer on the same day.
