# Ninth Vertical Slice — Newsletter

Status: **implemented and verified** (2026-09-23). This slice shipped newsletter subscription and consent:
the `Lead` model with a consent record, double opt-in on every path (public form, registration checkbox,
Profile), the three public `/newsletter/*` pages, the two historical mails, a daily purge of unconfirmed
leads, and an operator export of confirmed subscribers. Scope and sequencing come from
`docs/rewrite/master-roadmap.md`, "Slice 9"; the governing decisions are A-1 (consent) and A-6 (scope).

**Out of scope (unchanged)**: composing or sending newsletter issues, the "Nussig No. 1–3" issues and
`support.mjml` (A-6); Home itself, which places the public sign-up form, and the 404/500 page design
(slice 10).

## Historical mechanics (Confirmed, read in full for this slice)

| Item | Evidence | Behavior |
|---|---|---|
| API | `pages/api/newsletter.js`, `utils/functions/newsletter.function.js`, `api.function.js` | One route, five actions (`subscribe`, `subscribeConfirm`, `unsubscribe`, `unsubscribeConfirm`, `auth0SyncHasura`), 10 requests / 15 min per IP |
| Public form | `containers/home/NewsletterSection/NewsletterForm.js`, `newsletter.data.js` | Name (≤ 50, required), e-mail, privacy checkbox; loading → success/error toast; an existing lead → HTTP 500 → error toast |
| Registration | `auth0/rules/syncWithHasura.js` → `auth0SyncHasura` | Lead created **already confirmed**, no mail; errors swallowed (fail-open) |
| Profile | `pages/user/profile.js`, `profile.data.js` | Subscribe form while `!lead.hasConfirmed`; subscribe inserts + confirms from the browser, toast "Du bist jetzt angemeldet!"; unsubscribe: native `confirm()`, delete |
| Links | `newsletter.function.js` | JWT (`EMAIL_SECRET`, 7 days); subscribe carries `leadId`, unsubscribe `leadId` + `leadEmail` |
| Pages | `pages/newsletter/subscribe/[token].js`, `unsubscribe/[token].js`, `unsubscribe/lead.js` | `FramedCard` with the big logo; invalid link → 307 to `/404`; the unsubscribe-by-address form mails a link, 404 for an unknown address |
| Mails | `emails-nusszopf/src/sendgrid/newsletter/{subscribe,unsubscribe}.mjml` | Both open with "Bestätigte" (BUG-006) |
| List sync | `sync_leads_sendgrid` trigger, `pages/api/events/leads.js` | Confirmed/deleted leads mirrored to a SendGrid list |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Double opt-in on every path | **Fix** (BUG-011, A-1) | Registration and Profile now create a pending lead and send the mail; Profile's toast becomes the public form's "E-Mail verschickt! Bitte bestätige deine Anmeldung." |
| 2 | Consent record replaces `privacy`/`hasConfirmed` | **Fix** (A-1) | `requested_at`, `confirmed_at`, `source`, `consent_version` (`NEWSLETTER_CONSENT_VERSION`); no IP |
| 3 | Same answer for known and unknown addresses | **Fix** (BUG-032, BUG-033) | Pending → fresh mail, confirmed → nothing; unsubscribe-by-address mails only an existing lead |
| 4 | A valid subscribe link for a vanished lead → 404 | **Fix** (BUG-034) | Historically an empty success page |
| 5 | Mail grammar "Bestätige" | **Fix** (BUG-006) | Everything else verbatim |
| 6 | Unconfirmed leads purged after 14 days | **New** (A-1) | `newsletter:purge-unconfirmed`, daily 03:30 — the first real scheduler task. Counted from the latest request, so a lead with a still-valid 7-day link is never purged |
| 7 | Lead deleted with the account | **New** (A-1) | `AccountDeleter`, inside the existing transaction |
| 8 | No SendGrid list; operator export instead | **Replace** (A-6) | `newsletter:export` (CSV); external senders must link to `/newsletter/unsubscribe/lead` (`docs/deployment/operations.md`) |
| 9 | HMAC token instead of JWT | **Replace** | Keyed from `APP_KEY`, same single-segment URL shape and 7-day lifetime; bound to the lead id so an old link never touches a later lead for the same address |
| 10 | 404 rendered in place instead of a 307 to `/404` | **Replace** | Same visible outcome; the error page design itself is slice 10 |
| 11 | No separate `hasVerifiedEmail()` gate (A-3) | **Reconciliation** | The confirmation click proves ownership of the address; a gate would contradict A-1's registration path, where the address is always unverified at request time |
| 12 | Throttle shared across the public newsletter forms and Profile subscribe; mail-link pages not throttled | **Preserve** (+ one extension) | Historical budget 10 / 15 min per IP. The Profile subscribe now sends mail, so it spends the same budget. The link pages' historical calls came from the Next.js server's own address, and a signed token cannot be guessed |
| 13 | Registration newsletter side effect is fail-open | **Preserve** | A failure is reported; the account is still created |
| 14 | Mails sent from `MAIL_FROM_ADDRESS`, not `noreply@nusszopf.org` | **Replace** | Same as every other mail since slice 6 |
| 15 | E-mail matching is exact (as historically) | **Preserve** | `leads.email` unique; `User::lead()` matches the account address exactly |

## Implementation

- **Migration** `2026_09_23_120000_create_leads_table.php` (with a `source` CHECK constraint); `App\Models\Lead`, `LeadFactory`; `User::lead()`.
- **`App\Support\Newsletter`**: `subscribe()`, `confirm()`, `requestUnsubscribe()`, `confirmUnsubscribe()`, `forget()`, `purgeUnconfirmed()`. **`App\Support\NewsletterToken`**: make/read.
- **Mails**: `NewsletterSubscribeMail`, `NewsletterUnsubscribeMail` (queued) through the shared mail layout.
- **Pages**: `App\Http\Controllers\Newsletter\ConfirmationController` (two token pages), `App\Livewire\Newsletter\UnsubscribeByEmail`, `App\Livewire\Newsletter\SubscribeForm` (for Home, slice 10), throttle trait `ThrottlesNewsletter`; `x-framed-card`, `x-newsletter-logo` and the big logo SVG; the layout gained the historical `noindex` prop.
- **Wiring**: `LoginRegister::register()`, `Profile::subscribeNewsletter()`/`unsubscribeNewsletter()`, `AccountDeleter`.
- **Operations**: `newsletter:purge-unconfirmed` (scheduled), `newsletter:export`, `NEWSLETTER_CONSENT_VERSION`.

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Newsletter/NewsletterTokenTest.php` | round trip, URL shape, wrong purpose, tampered payload/signature, foreign key, 7-day expiry |
| Feature | `tests/Feature/Newsletter/SubscribeTest.php` | pending lead + consent record + mail, no IP stored, historical validation copy, pending re-request (refresh + resend), confirmed re-request (no change, no mail), throttle, storage error toast, name cap |
| Feature | `tests/Feature/Newsletter/ConfirmationPagesTest.php` | both pages' copy and `noindex`, idempotent confirm, unknown/tampered/expired → 404, vanished lead → 404 (BUG-034), cross-purpose tokens, old links never touch a later lead, idempotent unsubscribe |
| Feature | `tests/Feature/Newsletter/UnsubscribeTest.php` | page copy, mail for pending and confirmed leads, neutral answer for an unknown address (BUG-033), validation copy, shared throttle budget |
| Feature | `tests/Feature/Newsletter/PurgeUnconfirmedLeadsTest.php`, `ExportSubscribersTest.php` | 14-day rule, confirmed never purged, daily schedule; CSV columns, confirmed only, file and stdout |
| Feature | `tests/Feature/Mail/NewsletterMailTest.php` | subjects, verbatim copy, "Bestätige" (BUG-006), link |
| Feature | `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Profile/ProfileNewsletterTest.php`, `DeleteAccountTest.php` | checkbox → pending + mail, unchecked → nothing, fail-open; Profile states, consent required, own address only, unsubscribe; lead deleted with the account |
| E2E | `tests/E2E/specs/visitor/newsletter.spec.ts` | registration → mailbox → confirm → unsubscribe by address → mailbox → confirm; Profile subscribe → mailbox → confirm → Profile unsubscribe; unknown/tampered link → 404 |

Verified 2026-09-23 against the dev stack: Pest 483 passed; Larastan level 7 clean; Pint clean; the newsletter
spec 9/9 on Chromium, Firefox and WebKit. The three new pages were checked by screenshot at 375 and 1440 px
against the historical `FramedCard` layout (big logo, headings, copy, buttons).

## Remaining gaps

- ~~The public sign-up form has no page until Home exists (slice 10)~~ — closed in slice 10 (Home places it; E2E in `public-shell.spec.ts`).
- ~~An invalid link shows the framework's plain 404 page~~ — closed in slice 10 (historical `ErrorPage`).
- No "link expired" message (Preserve — historically a bare 404 too, `docs/design/states.md`).
- The throttle budget is per IP and shared, as historically; many visitors behind one NAT share it.
- Confirming via GET means a mail scanner that prefetches links could confirm a subscription — the same
  property as the historical flow; not changed without a product decision.
- ~~Pre-existing: `password-reset.spec.ts` fails on Firefox locally (the e-mail is still empty when "Senden" is
  clicked).~~ Fixed in P-1: the spec filled the field before the page had loaded and Livewire had bound
  `wire:model`; `LoginPage.goToForgotPassword()` now waits for the load.
