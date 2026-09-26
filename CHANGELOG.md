# Changelog

All notable changes to Nusszopf 2 are recorded here, in the [Keep a Changelog](https://keepachangelog.com/) format.
Versions follow [Semantic Versioning](https://semver.org/); tags carry no `v` prefix. Operator-facing impact is tagged
inline (`**Breaking:**`, `**Migration required:**`); see `docs/release/changelog.md` and `docs/release/upgrades.md`.

## [Unreleased]

## [1.0.0-rc.1] - 2026-09-26

The first release candidate of Nusszopf 2, the faithful reimplementation of the historical Nusszopf on PHP 8.5,
Laravel 13, Livewire 4, PostgreSQL, Redis and Meilisearch. It is a pre-release: there is no earlier release to upgrade
from, so a fresh install is the only path (`docs/deployment/README.md`). Do not put it in front of real users before its
release checks are recorded in `docs/release/parity/P-16-release.md`.

### Added

- The historical product, screen for screen: home, search (projects and requests, category filters, "Mehr laden"),
  project pages with requests, contact, the visitor counter and "Projekt melden", the project editor with its rich-text
  editor, My Projects, the profile with avatar upload and cropping, account deletion, the newsletter, the legal pages,
  error pages, sitemap, `robots.txt` and Open Graph tags. The historical design is a hard requirement and is guarded by
  a visual-regression suite.
- Self-hosting: two images (`ghcr.io/lchristmann/nusszopf-php-fpm` and `…/nusszopf-web`, `linux/amd64` and
  `linux/arm64`), a production `docker-compose.yaml`, `.env.production.example` and `install.sh`, published with each
  release. Install, upgrade, backup, restore, rollback, search-index recovery and health checks are documented in
  `docs/deployment/`.
- Login by e-mail or username and by Google (optional), password reset, a welcome mail and a login-lockout notice.
  E-mail verification gates only the personal contact and the newsletter (BUG-030). Registration is throttled per IP
  instead of Auth0's captcha.
- Double opt-in on every newsletter path, with neutral, idempotent answers (BUG-011, BUG-032…034), and an operator
  export of subscriptions.
- Seven mail types, sent through the queue by Resend or any SMTP relay (`docs/email/README.md`).
- `php artisan nusszopf:health`, `/health` (with a token, reporting the version, the queue and `failed_jobs`) and
  `php artisan search:reindex`, the one documented command that rebuilds the search index from PostgreSQL.
- Queue-backed search indexing with retries and visible failures (replacing silently failing webhooks), a scheduler
  container and a Redis append-only file, so a Redis crash does not lose queued jobs.

### Changed

Deliberate differences from the historical Nusszopf, each recorded with its evidence and regression test in
`docs/rewrite/intentional-changes.md`:

- Authentication is enforced on the server before any protected markup is sent, not client-side after the page loaded.
- A request (Gesuch) is publicly visible only while its project is; search shows only what anyone may see and re-checks
  every hit against the database.
- Project view counters are incremented by the server only (BUG-017's `contactRequests` is not reproduced); the dedupe
  is a signed cookie.
- Search hits are escaped and only the highlight is markup (BUG-029); equally ranked hits are ordered oldest first
  (BUG-047).
- A social login no longer overwrites an avatar the user has set. Avatar uploads are re-validated and re-encoded on the
  server (BUG-031).
- Contact-mail sets `Reply-To` to the visitor; the contact form is validated and escaped server-side.
- Four copy typos, three low-contrast colours and the focus indicator, Escape handling, accessible names, error
  association and heading order were corrected to the WCAG-based bar of decision A-7 (BUG-039…044).
- Sitemap, `robots.txt` and Open Graph URLs use this instance's own address (BUG-035…037).
- Social sign-in, mail, geocoding and legal texts are operator-provided: the legal pages ship as examples with
  placeholders that each operator must replace (`legal/`, `docs/legal/`).

### Fixed

- Period validation ignoring a flexible period's stale dates (BUG-022), stored dates shifting by a day (BUG-023),
  mislabelled list buttons (BUG-024), whitespace-only titles and goals (BUG-026), and a project and its requests not
  being created together (BUG-027).

### Security

- Security headers and a Content-Security-Policy on every response (BUG-046).
- The security review (`docs/release/parity/P-04-security.md`) found and fixed one High and four Medium issues before
  this release: mailed links taking their host from the request (password-reset takeover), an untrusted
  `X-Forwarded-For` in the production template, an unlimited "Passwort vergessen", an avatar upload that could kill a
  PHP worker with a tiny image, and sessions surviving a password reset (which now ends the account's other sessions).
  Six lower findings were fixed as well.
- `composer audit` and `npm audit` report nothing; the whole Git history was scanned for secrets.

### Known limitations

Recorded and tracked, not waived; the release checks are listed in `docs/release/parity/P-16-release.md`.

- Rendering of the seven mails in Gmail, Outlook and Apple Mail, and a real-iPhone and real-Android pass, are checked
  during the release-candidate phase; until the page says so, they are unverified.
- Mail through a generic SMTP relay with real TLS was not exercised (Resend was). Provider rate limiting (429),
  greylisting and outages are unmeasured.
- Queue jobs are delivered at least once: a mail queued at the moment of a worker crash can be sent twice.
- Backups are not encrypted and stale backups are not alerted on (decision B2); the operator owns both.
- A screen-reader listening pass has not been performed.
