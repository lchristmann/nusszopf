# Sixth Vertical Slice — Mail foundation and project contact

Status: **implemented and verified** (2026-09-22). This slice built the shared mail infrastructure
(a Blade mail layout reproducing the historical MJML template anatomy, a queued `Mailable`) and the
first mail that rides it: the "Über Nusszopf" project contact form, replacing the slice-2 `mailto:`
scaffold on that path. It closes BUG-005 (`Reply-To`), BUG-010 (server-side validation/escaping),
completes BUG-018's third journey (contact a project owner from a search result), and adds a local
Mailpit catcher to the dev/CI stack. Scope and sequencing come from `docs/rewrite/master-roadmap.md`,
"Slice 6".

**Out of scope (unchanged)**: every other historical email (welcome, password reset, blocked-account,
newsletter subscribe/unsubscribe — slices 7 and 9); BUG-006's newsletter copy fix (the templates it
applies to don't exist in Nusszopf 2 yet); `Project::NUSSZOPF_CONTACT`/`MAIL_FROM_ADDRESS` becoming
operator configuration (slice 10, "operator mailbox/identity are configuration").

## Historical mechanics (Confirmed against `web-nusszopf`/`emails-nusszopf`, read in full)

| Item | Evidence | Behavior |
|---|---|---|
| Trigger | `ContactDialog.js`, `pages/projects/[id].js` `handleContact` | A personal contact (`project.contact !== NZ_EMAIL`) is a plain `mailto:` to the owner's own address, no app involvement. "Über Nusszopf" opens a dialog instead: visitor e-mail + message, `POST /api/contact` |
| Recipient | `api/contact.js` | `user.private.email` — the project owner's own private e-mail, resolved server-side from the project's `user_id`. Never "Nusszopf's inbox" as such (`docs/security/authorization-matrix.md` corrected, see below) |
| Sender | `api/contact.js` | `{ name: 'Nusszopf (noreply)', email: 'noreply@nusszopf.org' }` |
| Subject/body | `contact.mjml` | "Nusszopf – Kontaktanfrage"; heading "Nussige Nachricht"; names the project, and the request too if one was open (`<project_title> / <request_title>`); the visitor's message in a highlighted box; a closing line giving the recipient the visitor's own e-mail to reply to |
| `Reply-To` | `api/contact.js` | **Missing** — BUG-005. Replying goes to `noreply@nusszopf.org` |
| Validation | `api/contact.js` | **None server-side** beyond rate-limiting — BUG-010. Client-side (`contact-dialog.data.js`'s Yup schema): e-mail required + valid format (100-char input cap); message required, max 2000 |
| Rate limit | `runMiddleware.function.js` | 10 requests per 15 minutes per IP (`express-rate-limit`), shared by every historical API route it guarded (contact, newsletter, upload, sitemap) |
| Request-dialog contact | `pages/projects/[id].js` `currentRequest` state | The request dialog's own "Kontaktieren" calls the same `handleContact`, carrying whichever request was last opened — see decision 3 below for why this state machine isn't reproduced literally |
| Template shell | `docs/email/README.md`, "Shared template anatomy" | Body `#ECEFF1`, white content card, `#CFD8DC` footer band, Barlow 500/700, pill buttons, a 180px logo (SendGrid-CDN-hosted, not in either repo), 3 footer icon links (Instagram, mail, "to Nusszopf"), a vCard link, a privacy-policy link |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Mail layout hand-authored as Blade + inline-style HTML, not compiled from committed MJML at build time | **Deviation (implementation technique)** | Register B-5's recommended default was an MJML→Blade build step. Rejected here: it would add a Node MJML compiler to the build pipeline for a single template (six more land in slices 7/9, but building that pipeline before a second consumer exists is speculative infrastructure — CLAUDE.md: "do not add complexity merely because another project has it"). MJML is an authoring convenience for the same table/inline-style HTML every mail client needs regardless — the same relationship Tailwind has to the historical UI (CLAUDE.md) — so a hand-authored equivalent is not a fidelity loss, only a different way to produce the identical rendered structure (verified against a real Mailpit send, not just Blade unit tests). Revisit the build-step approach if slice 7/9 finds copy-pasting the shell error-prone |
| 2 | Logo: the historical 180px SendGrid-CDN image is replaced by the app's own `nusszopf-header-logo` SVG, inlined at 72px, not fetched from any external host | **Replace** (dependency dropped, self-hosted) | Same category as the ui-avatars.com avatar fallback (`intentional-changes.md`). The historical asset was never in either repository (SendGrid's own CDN only) — its exact pixel content (a lockup? mark only?) is **Unknown**; 72px is a considered choice for a 600px-wide card, not a guess at the original's content. Inlining (not an `<img src>`) also means the logo renders with zero external requests, a strict self-hosting improvement over the historical CDN dependency |
| 3 | `currentRequest` (which request, if any, a contact concerns) is set explicitly every time "Kontaktieren" opens, never left over from a previously-closed dialog | **Replace** (implementation technique, not a behavior change with product intent behind it) | The historical SPA's `useState` only cleared `currentRequest` in `closeContact`, not in `closeRequest` (`pages/projects/[id].js`) — closing a *request* dialog by itself left the state set, so a later click on the header's own "Kontaktieren" (a project-level contact) could still carry a stale request's title into the mail's subject line. Nothing suggests this was deliberate (it is the unexceptional shape of two booleans and one shared "which" variable, not a considered design); Nusszopf 2's server round-trip (`openContact(?requestId)`) sets the request context atomically with every open, so the ambiguity cannot arise. Not filed as a BUG entry: this is an implementation-technique difference (stateless request/response vs. lingering client state), the same class of call the fourth/fifth slices made for their own throttle/loading-state reproductions, not a "the historical behavior looked surprising but might be intended" case |
| 4 | Rate limit key is scoped to the contact form (`contact:{ip}`), not shared globally across every route the way the historical Node middleware instance was | **Replace** | Matches the existing codebase convention (`LoginRegister`'s `login:{ip}` is already its own scope, distinct from any other limiter) rather than literally reproducing one shared in-process counter across unrelated Node API routes — the observable limit (10 messages per IP per 15 minutes) is preserved exactly |
| 5 | Two footer links not reproduced: "Nusszopf als Kontakt speichern" (vCard) is dropped for now; "Zum Nusszopf" and "Datenschutzerklärung" point at this instance's own URLs, not `nusszopf.org` | **Replace** (operator-identity gap, deferred) / **Fix** (wrong link target for a self-hosted copy) | The historical vCard hardcodes the *original* project's own contact addresses (`mail@nusszopf.org`, `noreply@nusszopf.org`) — shipping it unmodified from every self-hosted instance would misattribute a stranger's contact details, the same category of gap slice 10 is scheduled to close ("operator mailbox/identity are configuration"); revisit there. The Instagram link stays the literal historical brand URL (A-5: brand assets reproduced verbatim), but "Zum Nusszopf" and the privacy link are this deployment's own `url('/')`/`route('privacy')` — pointing a self-hosted copy's transactional mail at the *original* nusszopf.org's now-unrelated pages would be a plain defect, not fidelity |
| 6 | `authorization-matrix.md`'s "Contact actions" row corrected | **Doc reconciliation** (CLAUDE.md: contradicted "Confirmed" claims must be reconciled, not coded around) | The row read "routes to Nusszopf's inbox" — contradicted by `contact.js`'s own `to: user.private.email`. Corrected in place; see the file's own diff |

## Implementation

- **Mail layout** `resources/views/components/mail/layout.blade.php` (`<x-mail.layout>`): the shared
  shell — logo, Barlow font link, white content card, `#CFD8DC` footer with Instagram/mail/"to
  Nusszopf" icon links and the privacy link. Two new icon files (`instagram.svg`, `mail.svg`,
  Feather paths taken directly from the historical MJML's inline SVGs); `link.svg` (footer's third
  icon) already existed from an earlier slice.
- **Mailable** `App\Mail\ContactMail` (`implements ShouldQueue`): `envelope()` sets `to` (owner),
  `replyTo` (visitor, BUG-005), the historical subject; `content()` renders `resources/views/mail/contact.blade.php`
  (project/request title logic, the highlighted message box, the closing line) — every variable via
  `{{ }}`, never `{!! !!}` (BUG-010's escaping half). Queued on the existing `queue-worker` (BUG-009's
  retry/dead-letter policy now covers mail too).
- **Livewire** `App\Livewire\Projects\ProjectDetail`: `contactEmail`/`contactMsg`/`contactRequestId`
  properties, `openContact(?requestId)` (resets the form, sets the request context atomically —
  decision 3), `submitContact()` (validation — BUG-010's other half — rate limit, `Mail::send()`,
  toast). The request id is re-resolved through `Project::requests()->visible()`, never trusted
  directly, the same discipline every other request read already uses.
- **UI** `resources/views/components/contact-dialog.blade.php` (`<x-contact-dialog>`): the dialog
  form, wired to the host component like the existing request-edit dialog (`ManagesRequestDialog`'s
  pattern) rather than a separate nested Livewire component. `project-detail.blade.php`'s header
  "Kontaktieren" button and `request-view-dialog.blade.php`'s own button each branch on
  `Project::hasPersonalContact()`: personal stays the existing `mailto:`, "Über Nusszopf" now opens
  the dialog (`openRequest`/`contactOpen` Alpine state shared on the page's root element).
- **Dev/CI infrastructure**: `mailpit` service in `compose.dev.yaml` (SMTP 1025, web/API 8025),
  `.env`/`.env.example` point `MAIL_HOST`/`MAIL_PORT` at it; `.env.production.example` documents the
  operator-supplied relay and what happens without one. `ci.yml`'s e2e job starts `mailpit` and
  passes `E2E_MAILPIT_URL` to Playwright.

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Mail/ContactMailTest.php` | project-only vs. project+request subject line, HTML escaping (message/e-mail/project title), envelope (subject, to, replyTo), failed-send lands in `failed_jobs` (BUG-009 extended to mail) |
| Feature | `tests/Feature/Projects/ContactFormTest.php` | end-to-end send (recipient, Reply-To), request-scoped message, validation (missing/malformed e-mail, empty/over-length message, historical copy), the 10/15-minute rate limit, a header-injection-shaped address rejected as invalid, a request id from another project ignored (falls back to project-level), the owner's e-mail never rendered on the page |
| Feature | `tests/Feature/Projects/ProjectDetailContentTest.php` | the header button is a dialog-opener (not a `mailto:`) for "Über Nusszopf"; personal contact unaffected |
| E2E | `tests/E2E/specs/visitor/project-detail.spec.ts` | a visitor sends a message through the dialog; the mail actually arrives at the owner's address via Mailpit's API, with the project title and message body |
| E2E | `tests/E2E/specs/visitor/search.spec.ts` | BUG-018's third journey completed: search → open a result → the contact dialog opens |
| E2E | `tests/E2E/specs/user/project-requests.spec.ts` | the request dialog's own contact button opens the form too (updated from the old scaffold's `mailto:` assertion) |

Manually verified against a real Mailpit send (not only Blade/Feature-test assertions): recipient,
`Reply-To`, subject, rendered logo/footer, and HTML-escaping of a message containing `<b>HTML</b>`.

## Remaining gaps

- *(Closed in slice 10: the vCard is generated and linked; the displayed mailbox is `NUSSZOPF_CONTACT_EMAIL`.)*
  The vCard footer link (decision 5) and `Project::NUSSZOPF_CONTACT`/`MAIL_FROM_ADDRESS` staying
  hardcoded literals — both fold into slice 10's "operator mailbox/identity are configuration" line,
  not new gaps this slice opened.
- BUG-006 (newsletter copy typo) stays Proposed, not Implemented — its templates are slice 9's.
- SMTP-outage recovery is verified at the Feature-test level (`ContactMailTest`'s `failed_jobs`
  test), not yet with a live production-stack drill the way Meilisearch/Redis/PostgreSQL were for
  slice 5's operational pass (`docs/deployment/operations.md`) — worth doing once a real relay is
  available to test against.
- The MJML-build-step question (decision 1) is left open for slices 7/9 to revisit once more than
  one template exists.
