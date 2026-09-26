# Email Specification

Source: `../historical/emails-nusszopf` (MJML templates), cross-referenced with
`../historical/web-nusszopf/projects/webapp/src/pages/api/contact.js`,
`.../src/pages/api/newsletter.js`, `.../src/utils/functions/newsletter.function.js`,
`.../src/containers/projects/ContactDialog/ContactDialog.js`, and
`../historical/be-nusszopf/auth0/rules/*` and `../historical/be-nusszopf/docs/auth0/rules.md`.

All historical copy is German (informal "Du" address), in the brand's playful pun-heavy
voice ("Nusszopfer:in", "das ist so sicher wie die Nuss im Zopf"). Copy below is quoted
verbatim where marked **Verbatim** — do not rewrite or "improve" this wording; it is a
product-fidelity requirement, not placeholder text.

## Shared template anatomy (Confirmed, all templates)

Every template is built with MJML 4.7.1 (`emails-nusszopf/package.json`, engines: Node 12.x,
`yarn build` → `mjml`). There is no build/localization tooling beyond the MJML compiler and
manual minification via minifycode.com per the repo `README.md` — there is no i18n system;
German copy is hardcoded per template.

Common structure across every template:

- Body background `#ECEFF1` (blue-grey 50), content card background `#ffffff`, footer band
  background `#CFD8DC` (blue-grey 100).
- Header: 180px-wide Nusszopf logo image (hosted on SendGrid's CDN
  `cdn.mcauto-images-production.sendgrid.net`), top of card, no padding.
- Body typography: font family "Barlow" (Google Fonts, weights 500/700), heading
  `24px/700/#37474F`, body text `16px/500/#37474F` line-height `22px`.
- "Not you?" / security-notice text, where present, is colored `#B84405` (burnt orange).
- Buttons: pill-shaped (`border-radius: 100px`), `2px solid #37474F` border,
  text color `#37474F`, background `#ECEFF1` (i.e. an outlined/ghost button, not filled).
- Footer: three icon links (Instagram `https://www.instagram.com/nuss.zopf/`, "write an
  email" `mailto:mail@nusszopf.org`, "to Nusszopf" `https://nusszopf.org`) rendered as inline
  SVGs stroked `#263238`; below that, "Nusszopf als Kontakt speichern" linking to
  `https://nusszopf.org/contact/nusszopf-vcard.vcf`, and a "Datenschutzerklärung" (privacy
  policy) link to `https://nusszopf.org/privacy`.
- No `mj-preview` (preheader) text is set in any template. **Unknown** whether a preheader
  was set at the ESP/SendGrid dynamic-template level outside this repo.

This footer/header shell should be reproduced as a shared Blade mail layout; only the body
content block differs per email type.

## Transactional / lifecycle emails (Auth0-triggered)

Source: `emails-nusszopf/src/auth0/*.mjml`. These four filenames match Auth0's own
built-in customizable email template slots (`welcome`, `change_password`, `blocked_account`,
`stolen_credentials`/breach alert) **1:1 by name**. No explicit Auth0 tenant email-template
configuration file exists in `be-nusszopf` (only `auth0/rules/*.js`, which handle
Hasura-claim/Gravatar sync, not email — see `be-nusszopf/docs/auth0/rules.md`), so the exact
wiring (which Auth0 trigger fires which template, whether they were customized in the Auth0
dashion vs. left default) is **Inferred** from Auth0's standard product conventions, not
directly demonstrated in-repo. Auth0 is being replaced by Laravel-native auth per
`CLAUDE.md`, so these become Laravel's own auth notification equivalents.

### 1. Welcome (`auth0/welcome.mjml`)

- **Trigger:** Inferred — Auth0's `welcome` template, sent after a new user signs up /
  verifies. Laravel-native equivalent: post-registration welcome notification.
- **Recipient:** the new user.
- **Sender:** implied brand identity is "Nusszopf" (from-name not visible in this static
  template; SendGrid/Auth0 handles envelope sender). Reply/contact address used inline:
  `mail@nusszopf.org`.
- **Subject / mj-title:** "Willkommen beim Nusszopf!"
- **Body (Verbatim):**
  > "Wow, Du bist jetzt beim Nusszopf angemeldet! Das heißt, Du bist jetzt ein:e
  > Nusszopfer:in in ausgebackenster Form und wir könnten nicht glücklicher sein, dich beim
  > Nusszopf willkommen zu heißen."
  >
  > "Gestalte den Nusszopf aktiv mit: Sag uns, was Du für deine Ideenumsetzungen brauchst,
  > was dir fehlt und am besten helfen würde. Wir freuen uns über jeden Anstoß, das ist so
  > sicher wie die Nuss im Zopf!"
  >
  > "Backen wir uns die Welt, wie sie uns gefällt!" — "Dein Nusszopf Team"
  >
  > Security notice (orange): "Falls Du diese Anfrage nicht gestellt hast, kontaktiere uns
  > bitte via mail@nusszopf.org."
- **CTA:** none (no button) — purely informational.
- **Variables:** none (fully static copy).

### 2. Password reset link (`auth0/change-password.mjml`)

- **Trigger:** Inferred — Auth0 `change_password` template, sent when a password-reset flow
  is initiated (matches `web-nusszopf/projects/auth-password` app's likely purpose — see
  authentication archaeology owned separately).
- **Recipient:** the account holder who requested the reset.
- **Subject / mj-title:** "Nusszopf – Neues Passwort erstellen"
- **Body (Verbatim):** "Hier ist der Link, mit welchem Du dein Passwort ändern kannst.
  Klicke dazu einfach auf den Button."
- **CTA:** pill button "Neues Passwort erstellen" → `{{ url }}` (Auth0/Handlebars-style
  variable — a signed reset link).
- **Security notice:** same orange "not you?" text as Welcome, pointing to
  `mail@nusszopf.org`.
- **Variables:** `{{ url }}`.

### 3. Blocked account / IP block notice (`auth0/blocked-account.mjml`)

- **Trigger:** Inferred — Auth0 `blocked_account` template, sent when Auth0's
  brute-force/anomaly detection blocks an IP after repeated failed logins against this
  account.
- **Recipient:** the account holder whose account was targeted.
- **Subject / mj-title:** "Nusszopf – IP-Adresse blockiert"
- **Heading (Verbatim, pun on brand name):** "Was in Nusszopfs Namen geht hier vor?"
- **Body (Verbatim):**
  > "Wir haben verdächtige Aktivitäten bei der Anmeldung zu deinem Account festgestellt: die
  > IP-Adresse **{{ user.source_ip }}** aus **{{ user.city }}**, **{{ user.country }}** hat
  > mehrfach erfolglos versucht, sich bei deinem Nusszopfaccount anzumelden."
  >
  > "Sicherheitshalber haben wir daher diese IP-Adresse blockiert, sodass sie nicht mehr
  > versuchen kann, sich bei deinem Account anzumelden."
  >
  > "Wenn Du versucht hast dich anzumelden, kannst Du deine IP-Adresse hier wieder
  > freischalten, ansonsten musst Du dir keine Gedanken machen, denn die fremde IP-Adresse
  > ist und bleibt blockiert."
- **CTA:** pill button "Das bin ich!" → `{{ url }}` (unblock link).
- **Variables:** `{{ user.source_ip }}`, `{{ user.city }}`, `{{ user.country }}`, `{{ url }}`
  — these are Auth0's standard blocked-account template variables (Inferred from Auth0
  documentation conventions; confirms this is the stock Auth0 slot, lightly re-skinned).

### 4. Breached-password / stolen-credentials alert (`auth0/password-breach-alert.mjml`)

- **Trigger:** Inferred — Auth0's breached-password detection (Auth0's "stolen credentials"
  feature, checks user credentials against known breach lists) forcing a password reset.
- **Recipient:** the account holder.
- **Subject / mj-title:** "Nusszopf – Sicherheitshinweis"
- **Heading:** "Sicherheitshinweis"
- **Body (Verbatim):**
  > "Aufgrund eines Sicherheitsvorfalls auf einer anderen Webseite ist dein Account
  > gefährdet."
  >
  > "Bitte **ändere dein Passwort so bald wie möglich**, bis dahin werden alle
  > Anmeldeversuche blockiert, um deinen Account zu schützen."
  >
  > "Du erhältst diese E-Mail, weil Du einen Nusszopfaccount hast. Bei Fragen, kannst Du
  > dich immer gerne unter mail@nusszopf.org bei uns melden."
- **CTA:** pill button "Neues Passwort erstellen" → `{{ url }}`.
- **Behavioral side effect (important for the rewrite):** copy explicitly states that until
  the password is changed, **all login attempts on the account are blocked**. This account
  lockout behavior must be reproduced by whatever replaces Auth0's breach detection, or
  explicitly documented as a dropped feature if Laravel-native auth has no equivalent — see
  `docs/rewrite/open-questions.md`.

## Product-triggered emails (SendGrid, app-triggered — Confirmed)

These four are triggered directly by `web-nusszopf`'s Next.js API routes calling
`@sendgrid/mail` with a SendGrid **dynamic template** ID from an env var. This is
**Confirmed** by reading the calling code, not just the template.

### 5. Project contact message (`sendgrid/contact.mjml`)

**Implemented** in the sixth slice as `App\Mail\ContactMail` — `docs/rewrite/sixth-slice.md`.

- **Trigger (Confirmed):** `web-nusszopf/projects/webapp/src/pages/api/contact.js`, called
  from `web-nusszopf/projects/webapp/src/containers/projects/ContactDialog/ContactDialog.js`
  — i.e. a visitor uses the "contact" dialog on a project page to message the project owner.
- **Recipient (Confirmed):** `user.private.email` — the resolved project owner's private
  email address (looked up server-side via `getUser(req.body.user)`; the visitor's identity
  is not exposed to the recipient except via the reply-to address they typed in).
- **Sender (Confirmed):** `{ name: 'Nusszopf (noreply)', email: 'noreply@nusszopf.org' }`.
- **Template:** `process.env.SENDGRID_TEMPLATE_CONTACT` (SendGrid dynamic template; this
  static `.mjml` file is presumably the source used to author that dynamic template, not
  what's rendered at send time — SendGrid dynamic templates are edited in SendGrid's own
  editor and only exported/mirrored here).
- **Subject / mj-title:** "Nusszopf – Kontaktanfrage"
- **Heading (Verbatim, pun):** "Nussige Nachricht"
- **Body template (Verbatim, Handlebars):**
  > "Eine nussige Nachricht wurde an das Projekt **{{#greaterThan (length request_title) 0}}
  > {{@root.project_title}} / {{@root.request_title}} {{else}} {{@root.project_title}}
  > {{/greaterThan}}** geschickt!"

  i.e. shows "`<project title>` / `<request title>`" if the visitor picked a specific
  request/role on the project, else just the project title.
- Message body rendered in a highlighted box (`#EEF5F7` background, `#213E45` text,
  `border-radius: 8px`): `{{private_msg}}` — the visitor's typed message, unescaped/raw
  (`private_msg`, not `{{{...}}}`, so MJML/Handlebars-escaped as plain text).
- Closing line (Verbatim): "Du kannst die Person unter folgender E-Mail-Adresse erreichen:
  **{{contact_email}}**." — gives the recipient the visitor's reply-to email so they can
  respond directly (no reply-to header is set in `contact.js`; reliance is on this line of
  body copy instead of a `Reply-To` header — worth flagging as a possible defect, see
  Suspected Issues below).
- **Payload fields (Confirmed, from `contact.js`):** `contact_email`, `project_title`,
  `request_title`, `private_msg`, all taken verbatim from `req.body` with **no server-side
  validation or sanitization visible** in this handler (only rate-limiting middleware). Flag
  for the rewrite: validate/escape these fields.

### 6. Newsletter subscribe confirmation (`sendgrid/newsletter/subscribe.mjml`)

> **Nusszopf 2 (slice 9):** `App\Mail\NewsletterSubscribeMail` / `resources/views/mail/newsletter-subscribe.blade.php`,
> verbatim except BUG-006. Sent on every subscription path (public form, registration checkbox, Profile — BUG-011),
> from `MAIL_FROM_ADDRESS` like every other mail; the link is `App\Support\NewsletterToken` (7 days), not a JWT.
> At most 3 per address per hour, whoever asks (P-4, SEC-04); past that the form answers as usual and sends nothing.

- **Trigger (Confirmed):** `newsletter.function.js` → `handleSubscribe()`, called from the
  `/api/newsletter` route with `action: 'subscribe'`. A double-opt-in flow: submitting the
  newsletter signup form creates a "lead" record (`addLead`) and immediately sends this
  confirmation email; the lead is only marked confirmed when the recipient clicks through
  (`handleSubscribeConfirm`, action `subscribeConfirm`, verifies a JWT `token` with 7-day
  expiry signed with `EMAIL_SECRET`).
- **Recipient:** the address the visitor typed into the newsletter signup form.
- **Sender (Confirmed):** `{ name: 'Nusszopf (noreply)', email: 'noreply@nusszopf.org' }`.
- **Template:** `process.env.SENDGRID_TEMPLATE_SUBSCRIBE_ID`.
- **Subject / mj-title:** "Nussiger Newsletter – Anmeldebestätigung"
- **Heading (Verbatim, pun, soft-hyphenated):** "Der News&shy;letter ist zum
  Grei&shy;fen nah!"
- **Body (Verbatim):** "Bestätige deine E-Mail-Adresse durch einen Klick auf den Button und
  schon bist Du zum Newsletter angemeldet!" (note: template literally reads "Bestätigte" —
  likely a historical typo for "Bestätige"; see Suspected Issues.)
- **CTA:** pill button "E-Mail-Adresse bestätigen" → `{{{subscribe_url}}}`
  (triple-brace = unescaped URL), which `newsletter.function.js` builds as
  `${DOMAIN}/newsletter/subscribe/${emailToken}`.
- **Variables (Confirmed, from code):** `subscribe_url`, `username` (`lead.name`) — though
  `username` is not referenced anywhere in the visible template body; it is passed but
  unused in `subscribe.mjml` (possibly used only in the SendGrid-dashboard-edited dynamic
  template, not in this static source file — Unknown).

### 7. Newsletter unsubscribe confirmation (`sendgrid/newsletter/unsubscribe.mjml`)

> **Nusszopf 2 (slice 9):** `App\Mail\NewsletterUnsubscribeMail` / `resources/views/mail/newsletter-unsubscribe.blade.php`,
> verbatim except BUG-006; sent only when the address has a lead (BUG-033), and at most 3 per address per hour (P-4, SEC-04).

- **Trigger (Confirmed):** `handleUnsubscribe()`, action `unsubscribe`; also double-
  confirmation — clicking the link calls `handleUnsubscribeConfirm` (action
  `unsubscribeConfirm`) which verifies a JWT and deletes the lead (`deleteLead`).
- **Recipient:** the subscribed address requesting removal.
- **Sender:** `{ name: 'Nusszopf (noreply)', email: 'noreply@nusszopf.org' }`.
- **Template:** `process.env.SENDGRID_TEMPLATE_UNSUBSCRIBE_ID`.
- **Subject / mj-title:** "Nussiger Newsletter – Abmeldebestätigung"
- **Heading (Verbatim, brand pun):** "Der Nusszopf liebt dich sowieso!"
- **Body (Verbatim):** "Bestätigte deine Abmeldung von dem Newsletter, indem Du auf den
  Button klickst. Wenn Du möchtest, kannst Du dich natürlich jederzeit wieder anmelden."
  (same "Bestätigte" vs. "Bestätige" typo pattern appears here as "Bestätigte" too — see
  Suspected Issues.)
- **CTA:** pill button "Abmelden bestätigen" → `{{{unsubscribe_url}}}`, built as
  `${DOMAIN}/newsletter/unsubscribe/${emailToken}`.
- **Variables:** `unsubscribe_url`, `username` (also unused in this static template body).

## Manually-sent / campaign emails (no app trigger found — Unknown wiring)

### 8. Newsletter issues ("Nussig No. 1/2/3") (`sendgrid/newsletter/welcome.mjml`,
`sendgrid/newsletter/published/nussig-no2.mjml`, `.../nussig-no3.mjml`)

- **What these are:** despite the filename `welcome.mjml`, its `mj-title` and on-page
  heading are **"Nussig No. 1"** — this is not a generic "newsletter welcome" template with
  placeholders, it is the archived content of the *first published newsletter issue*, sibling
  to the `published/nussig-no2.mjml` and `nussig-no3.mjml` issues (same title pattern
  confirmed: "Nusszopf – Nussig No. 2" / "No. 3"). All three share the same shell and each
  contain fully-written, static German prose specific to that issue (project update,
  community messaging), ending with an unsubscribe link `{{{ unsubscribe }}}`.
- **Trigger:** **Unknown / Inferred as manual.** No code in `web-nusszopf` calls a
  "send newsletter issue" endpoint or references these template files or a corresponding
  env var (only `SENDGRID_TEMPLATE_SUBSCRIBE_ID` / `SENDGRID_TEMPLATE_UNSUBSCRIBE_ID` /
  `SENDGRID_TEMPLATE_CONTACT` exist in the codebase). These were almost certainly composed
  and sent manually as one-off SendGrid Marketing Campaigns against the subscriber list, not
  triggered by application code.
- **Product-fidelity implication:** these are point-in-time editorial content, not a
  reusable transactional template. The rewrite should reproduce the *subscribe/confirm/
  unsubscribe/confirm* mechanics (items 6–7) faithfully, but does not need to reproduce
  "Nussig No. 1–3" as a feature — they are historical marketing artifacts, not a product
  behavior to re-implement. Record this scoping decision in
  `docs/rewrite/intentional-changes.md` once approved.

### 9. Generic support/admin message (`sendgrid/support.mjml`)

- **What this is:** a template with no static copy at all — every content line is a raw
  Handlebars variable: `{{{nz_welcome}}}`, `{{{nz_message}}}`, `{{{nz_greeting}}}`,
  `{{{nz_author}}}` (all triple-brace/unescaped, allowing HTML), plus the standard
  header/footer shell. `mj-title`: "Nusszopf – Support".
- **Trigger:** **Unknown.** No reference to a `SENDGRID_TEMPLATE_SUPPORT`-style env var or
  any code path exists in `web-nusszopf` or `be-nusszopf`. Most likely used by an
  administrator composing an ad-hoc message directly in the SendGrid dashboard (a generic
  "letterhead" for one-off support replies), not fired by any automated trigger.
- **Product-fidelity implication:** likely operational tooling for the Nusszopf team, not
  end-user-facing product behavior with a discoverable trigger. Treat as **Unknown** scope
  for the rewrite; do not invent an admin "compose email" feature to replace it unless other
  evidence surfaces.

## Cross-cutting observations

- **HTML-only mail (decided, P-13):** Nusszopf 2 sends every mail as HTML only, with no plain-text alternative. The
  maintainer accepted this as the intended format on 2026-09-26 (`docs/rewrite/decisions-register.md`, "Mail format"),
  so it is not a gap to fill. Real delivery through Resend: `docs/release/parity/P-13-email-delivery.md`.

- **Links in Nusszopf 2 mails (P-4, SEC-01):** every link starts with `APP_URL`, never with the host of the request that
  triggered the mail, so a forged `Host` header cannot redirect a reset or confirmation token
  (`docs/release/parity/P-04-security.md`).

- **From-address consistency (Confirmed):** every app-triggered send uses the same sender —
  `Nusszopf (noreply) <noreply@nusszopf.org>`. The Auth0-triggered emails do not show a
  from-name/address in the template itself (Auth0 controls the envelope), but every template
  uses `mail@nusszopf.org` as the human contact address in body copy. Two distinct addresses
  are in play: `noreply@nusszopf.org` (system sender) and `mail@nusszopf.org` (human
  contact/reply address) — both should be preserved as configurable addresses in the rewrite
  (e.g. `MAIL_FROM_ADDRESS` vs. a `NUSSZOPF_CONTACT_EMAIL` setting).
- **Branding assets:** the logo image is hosted on SendGrid's own asset CDN
  (`cdn.mcauto-images-production.sendgrid.net`), not on `nusszopf.org` — this is an
  ESP-hosted asset that will need to be re-hosted (e.g. under the app's own domain/storage)
  since the rewrite will not use SendGrid. **Done in the sixth slice**: the app's own
  `nusszopf-header-logo` SVG is inlined directly in the shared mail layout — no external
  request at all, not even to the app's own domain (`docs/rewrite/sixth-slice.md`, decision 2).
- **vCard link:** every footer links to `https://nusszopf.org/contact/nusszopf-vcard.vcf`,
  a static downloadable contact card — confirm in `docs/design` whether this file is served
  from `web-nusszopf/projects/webapp/public/contact/nusszopf-vcard.vcf` (Confirmed: that file
  exists at that path) and needs to be reproduced as a static asset in the rewrite. **Done in
  slice 10**: `/contact/nusszopf-vcard.vcf` is generated from this instance's own addresses
  (`App\Support\Operator::vcard()`, `NUSSZOPF_CONTACT_EMAIL`), and every footer links it again.
- **No attachments, no retry/failure-handling logic** are visible in any template or in the
  two API handlers beyond a generic `handleError` catch — SendGrid's own delivery retries
  are relied upon. The rewrite should use Laravel's queued-mail retry/backoff instead and
  document that as an explicit robustness improvement (per `CLAUDE.md`'s "more robust where
  the historical implementation had... missing pieces").

## Suspected historical issues (do not silently fix — see `docs/rewrite/open-questions.md`)

1. **Grammar/typo: "Bestätigte" vs. "Bestätige".** Both `newsletter/subscribe.mjml` and
   `newsletter/unsubscribe.mjml` open their body copy with "Bestätigte deine..." where German
   grammar calls for the imperative "Bestätige deine..." ("Confirm your..."). "Bestätigte" is
   the past tense/participle, which reads as a grammatical error in context. Evidence:
   both occurrences are identical in structure, suggesting a copy-paste of the same mistake
   rather than two independent typos. Likely intended behavior: imperative "Bestätige".
   **BUG-006, Fixed in slice 9.** (Item 7's "Body (Verbatim)" line above used to show the corrected
   verb; reconciled in slice 9 against the source, which reads "Bestätigte" in both templates.)
2. **No `Reply-To` header on the contact-form email.** `contact.js` puts the visitor's email
   only in the body copy (`{{contact_email}}`) rather than setting a `replyTo` on the SendGrid
   payload. This means hitting "Reply" in a mail client replies to `noreply@nusszopf.org`
   instead of the visitor. This looks like a usability defect worth correcting in the rewrite
   (set `Reply-To` to the visitor's address) while documenting the historical behavior.
   **BUG-005, Fixed in the sixth slice** (`docs/rewrite/sixth-slice.md`).
3. **No input validation/escaping on contact-form fields.** `contact.js` passes
   `req.body.email`, `req.body.title`, `req.body.request`, `req.body.msg` straight into the
   dynamic template payload with only rate-limiting in front of it. Whether SendGrid's
   dynamic-template engine HTML-escapes these by default is Unknown from this repo alone;
   flag for security review in the rewrite regardless.
   **BUG-010, Fixed in the sixth slice** (`docs/rewrite/sixth-slice.md`).
4. **`username` variable passed but apparently unused** in both newsletter subscribe and
   unsubscribe static templates — Unknown whether the actual SendGrid-hosted dynamic
   templates (edited via SendGrid's UI, not necessarily kept in sync with this repo) use it
   for personalization ("Hallo {{username}}...") that this static `.mjml` source has simply
   drifted from. Flag as a source-of-truth conflict: SendGrid dashboard templates may differ
   from what's checked into this repo.
5. **Newsletter double opt-in is only enforced on one of two creation paths.** A `Lead` created
   via the public newsletter-signup form goes through true double opt-in (this subscribe email
   is sent, confirmation required). A `Lead` created via the "newsletter" checkbox at account
   signup is created **already confirmed**, with no confirmation email sent at all — this
   `subscribe.mjml` template is never triggered for that path. Confirmed via
   `web-nusszopf/projects/webapp/src/pages/api/newsletter.js` and
   `src/utils/functions/newsletter.function.js`. This is a product-intent question, not a
   research gap — see `docs/rewrite/open-questions.md` → "Newsletter signup-checkbox path
   skips double opt-in" for the two competing interpretations and why this needs a human
   decision before Nusszopf 2 implements either behavior.
