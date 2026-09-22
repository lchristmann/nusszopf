# Screen-by-Screen Specification

A consistent, checklist-format specification for every screen, intended as an implementation checklist. This document is the structured index; `docs/design/screens.md` carries the narrative evidence and citations behind each fact here — read both together, and treat `screens.md` as the source of truth if the two ever appear to disagree (this file is a derived summary). Evidence status (Confirmed/Inferred/Unknown) for any given fact is as recorded in `screens.md`/`states.md`/`navigation.md`/`responsive-behavior.md`/`authentication.md`; not repeated per-field here for brevity.

---

## Home (`/`)

| Field                  | Spec                                                                                                                                                                                     |
|------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Public landing page / marketing entry point                                                                                                                                              |
| Access                 | Public                                                                                                                                                                                   |
| Layout                 | No `NavHeader` (own hero branding); `Footer` variant `classy`                                                                                                                            |
| Components             | Hero header (`bg-steel-50`) with logo + info card; `HowToSection`; `CarouselSection` (disabled, do not implement); About (turquoise); Contest (red); Fellows (pink); `NewsletterSection` |
| Data displayed         | CMS-driven copy (`assets/data/*.data.js` — literal German copy pending a content pass, do not invent)                                                                                    |
| Actions                | "Search" CTA → `/search`; "Create project" CTA → `/api/login`-equivalent if unauthenticated, else project creation; newsletter subscribe form                                            |
| Navigation             | Entry point for all unauthenticated visitors                                                                                                                                             |
| Validation             | Newsletter form: valid email format                                                                                                                                                      |
| Loading state          | None distinct from global route-change indicator                                                                                                                                         |
| Empty state            | N/A                                                                                                                                                                                      |
| Error state            | Global `ErrorPage`/`ErrorBoundary` on render failure                                                                                                                                     |
| Success state          | Newsletter subscribe → success toast                                                                                                                                                     |
| Responsive behavior    | Hero: stacked column → `lg:flex-row`; info card constrained/centered only at `lg`; 3-column sections stack below `lg`                                                                    |
| Authorization behavior | None — fully public                                                                                                                                                                      |
| URL/query params       | None                                                                                                                                                                                     |
| Side effects           | Newsletter subscribe creates a `Lead` (server-side, see `docs/domain/entities.md`) and sends the subscribe-confirmation email                                                            |

## Search (`/search`)

| Field                  | Spec                                                                                                                                                                                                        |
|------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Find public projects/requests                                                                                                                                                                               |
| Access                 | Public                                                                                                                                                                                                      |
| Layout                 | `NavHeader` visible; header frame `bg-moss-300`; Masonry results grid                                                                                                                                       |
| Components             | `SearchInput` (explicit submit — Enter/blur or the search-icon click, throttled 500ms against rapid resubmission; corrected in the first-slice verification pass, 2026-09-19, from an earlier "debounced 500ms" mischaracterization — `SearchInput.js`'s `handleChange` only updates local state, it never queries on keystroke) + `FilterPopover`; `Masonry` of `HitCard` (grouped by project, nested `HitRequestCard`); `SkeletonHits`; `NoHitsSection`; "load more" button; floating scroll-to-top button |
| Data displayed         | Search hits: project title/goal (highlighted), truncated summary, nested matching requests                                                                                                                  |
| Actions                | Type/search; apply filters; load more; scroll to top; click a hit → project detail                                                                                                                          |
| Navigation             | Reachable from `NavHeader` search icon (always visible)                                                                                                                                                     |
| Validation             | None (free-text query)                                                                                                                                                                                      |
| Loading state          | Initial: `SkeletonHits`; load-more: spinner in button; input trailing icon cycles search/loading/refresh-needed                                                                                             |
| Empty state            | `NoHitsSection` with a "create a project" CTA                                                                                                                                                               |
| Error state            | Confirmed (`search.service.js`): a failed query shows the no-hits section; a failed "Mehr laden" keeps the hits and shows the error toast "Sorry! Das hat gerade nicht geklappt."                                   |
| Success state          | Results rendered                                                                                                                                                                                            |
| Responsive behavior    | Masonry columns: 1 (≤639px) / 2 (640–1023px) / 3 (≥1024px), explicit breakpoints, not Tailwind defaults 1:1                                                                                                 |
| Authorization behavior | Results scoped to public projects/requests only (BUG-002 fix applies here — private-project requests must never surface); no differential behavior for authenticated vs. anonymous visitors                 |
| URL/query params       | Historically none (state in memory). Nusszopf 2: `?q=` (term) and `?f[]=` (applied filter options), so a result page can be shared and survives a reload — an addition (`docs/rewrite/fourth-slice.md`, decision 8) |
| Side effects           | None (read-only)                                                                                                                                                                                            |

## Project detail (`/projects/{id}`)

| Field                  | Spec                                                                                                                                                                                                                                                                                                                                   |
|------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | View a single project and its requests; contact/share/respond                                                                                                                                                                                                                                                                          |
| Access                 | Public, SSR-aware of the viewer's identity for owner-only affordances                                                                                                                                                                                                                                                                  |
| Layout                 | `FramedGridCard`-based two-column body; `Banner` (owner-only); header card `bg-lilac-300`                                                                                                                                                                                                                                              |
| Components             | Header card (title, goal, location w/ OSM link, period, Contact + Share buttons); left column (rich-text "What"/"Who"/"How", `VisitorCounter`); right column (`RequestCard variant="view"` list or `InfoCard` empty state, owner `Avatar`); report `mailto:` link; `RequestDialog`; `ContactDialog` (only when `contact === NZ_EMAIL`) |
| Data displayed         | Full `Project` fields, its `Request`s, owner's public `Avatar`                                                                                                                                                                                                                                                                         |
| Actions                | Contact (dialog or `mailto:`, depending on project config); Share (native share API or copy-link); click a request → `RequestDialog`; report abuse (`mailto:`)                                                                                                                                                                         |
| Navigation             | Reached from search results, "my projects" list, or direct link                                                                                                                                                                                                                                                                        |
| Validation             | N/A (read screen); `ContactDialog` message form validated                                                                                                                                                                                                                                                                              |
| Loading state          | Not fully traced in archaeology — treat as Unknown/needs confirmation at implementation                                                                                                                                                                                                                                                |
| Empty state            | No requests → `InfoCard` in place of the request list                                                                                                                                                                                                                                                                                  |
| Error state            | `notFound: true` (project id doesn't resolve) → hard 404, not a soft/late redirect                                                                                                                                                                                                                                                     |
| Success state          | Share → success toast; contact submit → loading→success/error toast                                                                                                                                                                                                                                                                    |
| Responsive behavior    | Two-column body collapses to one column below `lg` (`FramedGridCard.Body.Col twoCols` pattern)                                                                                                                                                                                                                                         |
| Authorization behavior | Public if `visibility === public`; owner can always view their own regardless of visibility; view/contact counters incremented server-side only (BUG-001 fix), excluding the owner's own views                                                                                                                                         |
| URL/query params       | `id` route param only                                                                                                                                                                                                                                                                                                                  |
| Side effects           | View-counter increment (server-controlled, once per visitor per project, excluding owner)                                                                                                                                                                                                                                              |

## User Projects (`/user/projects`)

| Field                  | Spec                                                                                                                                                                                  |
|------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Authenticated user's project dashboard                                                                                                                                                |
| Access                 | Auth required                                                                                                                                                                         |
| Layout                 | `FramedGridCard`; header card with `Avatar` + create-project button                                                                                                                   |
| Components             | `Masonry` of `EditProjectCard` (click-through, edit, delete, visibility toggle); `WelcomeCard` (empty state); `ProjectsSkeleton` (loading)                                            |
| Data displayed         | The authenticated user's own projects (all visibilities)                                                                                                                              |
| Actions                | Create project (→ `/user/project/create?step=0`); click card → view/edit; delete; toggle visibility (throttled 1/sec)                                                                 |
| Navigation             | Reached via `NavHeader`'s "my projects" icon (authenticated only)                                                                                                                     |
| Validation             | N/A                                                                                                                                                                                   |
| Loading state          | `ProjectsSkeleton` while `loadingProjects \|\| loadingUser`                                                                                                                           |
| Empty state            | `WelcomeCard` when the user has zero projects                                                                                                                                         |
| Error state            | Standard toast pattern on any failed mutation (delete, visibility toggle)                                                                                                             |
| Success state          | Delete → "Das Projekt wurde gelöscht." toast, card removed. Visibility toggle → "Projekt wurde aktualisiert." toast (the toggle calls the same shared `updateProject` service function the edit screen's settings save uses, confirmed by re-reading `projects.service.js` in the fifth slice — not a distinct, unconfirmed toast) |
| Responsive behavior    | Create-project CTA renders twice — a distinct, larger full-width mobile button (`<lg`, separate DOM node) and a smaller header-row button (`lg:block`) — not a single resized element |
| Authorization behavior | Only the caller's own projects, any visibility                                                                                                                                        |
| URL/query params       | None                                                                                                                                                                                  |
| Side effects           | Visibility toggle re-triggers search indexing (once Scout sync exists)                                                                                                                |

## Project creation (`/user/project/create`)

| Field                  | Spec                                                                                                                                                                                                                                                                                   |
|------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | 4-step wizard to create a new project (and optionally its first requests)                                                                                                                                                                                                              |
| Access                 | Auth required                                                                                                                                                                                                                                                                          |
| Layout                 | `FramedGridCard` (`lg:mb-20 lg:mt-12`; header `bg-lilac-300`, body white on `<lg`, `lg:bg-steel-100` page) — header: `Progressbar` (label per step, 25/50/75/100 %) and the typed title (`Neues Projekt` while empty); body `gap="medium"`, two `twoCols` per step, a centered `oneCol` navigation row. **Implemented (second slice)** — see `docs/rewrite/second-slice.md`. |
| Components             | Step 1 (`Beschreibung 1/2`): left title, goal, description (rich text); right location (Ortsunabhängig / Ortsgebunden + place search), period (Flexibel / Festgelegt + Von/Bis). Step 2 (`Beschreibung 2/2`): team (rich text) | motto. Step 3 (`Gesuche`): intro + "Gesuch erstellen" (`EditRequestDialog`) | created requests or the info card "Gesuche für das Projekt kannst Du entweder jetzt oder später erstellen." Step 4 (`Einstellungen`): visibility (Öffentlich default / Privat) | contact (Persönlich / Über Nusszopf, the owner's e-mail truncated to 25). |
| Data displayed         | Form state only (no persisted data yet)                                                                                                                                                                                                                                                |
| Actions                | Next/Back per step; add/remove requests (step 3, optional — zero requests allowed); final submit (Create)                                                                                                                                                                              |
| Navigation             | Reached from "create project" CTAs across the app; on success → `/user/projects`                                                                                                                                                                                                       |
| Validation             | Yup schema per step (step 1, step 2), re-validated defensively at final submit; category select from the closed `REQUEST_CATEGORY` enum                                                                                                                                                |
| Loading state          | `Projekt erstellen...` loading toast; the button is disabled while creating                                                                                                                                                                                                            |
| Empty state            | N/A                                                                                                                                                                                                                                                                                    |
| Error state            | Field errors (below the field, `textXs` italic `text-warning-700`) once the field was left or a failed `Weiter`; toast "Bitte überprüfe deine Eingaben oder versuche es später erneut." if the final re-validation fails; toast "Sorry, das Projekt konnte nicht erstellt werden." if persisting fails. **Resolved**: the defensive re-validation is kept (it is cheap and at the point of persistence). |
| Success state          | `Projekt wurde erstellt.` toast, redirect to `/user/projects`, new project visible in the grid                                                                                                                                                                                         |
| Responsive behavior    | `<lg`: one column, white body under the lilac header, `Page` white; `lg`: two columns (`lg:col-span-5`, `lg:col-start-2` on the left), steel page. Verified at 375 px (no horizontal scroll). |
| Authorization behavior | `user_id` forced to the caller server-side; cannot create on another user's behalf                                                                                                                                                                                                     |
| URL/query params       | `?step=N`, N ∈ 0..3. **Confirmed**: every entry starts at step 0 (the stepper pushes `?step=0` on mount, so a deep link or refresh never lands on a later step); browser back/forward follow the URL; values outside 0..3 or non-numeric are ignored. |
| Side effects           | Project + optional requests created; both indexed into search regardless of `visibility` at creation time (indexer/query-time filtering must enforce visibility, not the write path — see `docs/search/README.md`)                                                                     |

## Project edit (`/user/project/{id}/edit`)

| Field                  | Spec                                                                                                                                                                                                                           |
|------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Edit an existing project's description, requests, and settings                                                                                                                                                                 |
| Access                 | Auth required, owner only                                                                                                                                                                                                      |
| Layout                 | `FramedGridCard`; header with the saved project title and the view `Select` (`nz-select-lilac`, `w-56`, aria-label "Projekt Bereich auswählen"). **Implemented (second slice).** Beschreibung: left title, goal, description, motto — right location, period, team — centered `Speichern`. Einstellungen: visibility | contact, `Speichern`, then the warning-colored "Projekt löschen" block. |
| Components             | `ProjectView` (description tab); `RequestsView` (requests tab, per-request contextual menu with Edit/Delete); `SettingsView` (visibility/contact + delete-project button); `SkeletonView` (loading)                            |
| Data displayed         | Full editable project + its requests                                                                                                                                                                                           |
| Actions                | Save (description tab); edit/delete a request (via `EditRequestDialog` / native confirm); toggle visibility/contact (settings tab); delete project (native confirm)                                                            |
| Navigation             | Reached from `/user/projects`; tab switch is in-page (`Select`, not separate routes)                                                                                                                                           |
| Validation             | Same field-level rules as creation, per tab                                                                                                                                                                                    |
| Loading state          | `SkeletonView` while `loadingProject \|\| loadingUser \|\| !projectData`                                                                                                                                                       |
| Empty state            | Requests tab with zero requests — exact treatment Unknown, not directly observed (see `docs/design/states.md`)                                                                                                                 |
| Error state            | Client-side soft redirect to `/404` if the project id doesn't resolve (a late `useEffect` redirect, not a hard SSR 404 — this flash-before-redirect pattern should be evaluated the same way as BUG-003 during implementation) |
| Success state          | `Projekt wurde aktualisiert.` toast (nothing happens if nothing changed); view switch with unsaved changes → native `confirm()` "Möchtest Du die Seite wirklich verlassen? Deine Änderungen gehen dann verloren."; delete → "Das Projekt wurde gelöscht." + `/user/projects` |
| Responsive behavior    | Standard `FramedGridCard` responsive collapse                                                                                                                                                                                  |
| Authorization behavior | Owner only, target behavior. Historically, the edit screen's data-fetch reused the same visibility-scoped `GET_PROJECT` query as the public detail page (not an owner-only query) — a non-owner requesting a **private** project's edit URL got Hasura's `null` and a client-side `/404` redirect, but a non-owner requesting a **public** project's edit URL got the (already-public) data rendered, since mutations stayed separately owner-gated (BUG-021, `docs/rewrite/bugs.md`). Nusszopf 2 tightens this to strictly owner-only for both cases — a 404 either way, matching `ProjectDetail`'s existing no-existence-leak treatment — since the historical leniency was a query-reuse artifact with no product value, not a considered capability.                                                                                                                         |
| URL/query params       | `id` route param                                                                                                                                                                                                               |
| Side effects           | Any content edit touching search-relevant columns re-triggers indexing; visibility change re-triggers indexing and affects public reachability                                                                                 |

## Profile / account settings (`/user/profile`)

| Field                  | Spec                                                                                                                                                                                                                                                                                                |
|------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Manage avatar, newsletter subscription, view sponsoring/support links, delete account                                                                                                                                                                                                               |
| Access                 | Auth required                                                                                                                                                                                                                                                                                       |
| Layout                 | `FramedGridCard`, two-column body (stacks below `lg`)                                                                                                                                                                                                                                               |
| Components             | Header: title + `Avatar variant="settings"` (opens `AvatarDialog`); Newsletter subsection (subscribe form or unsubscribe button depending on `lead.hasConfirmed`); Sponsoring subsection (static + external link); Delete-account subsection; two `InfoCard`s (contact doc link, support `mailto:`). Nusszopf 2 (slice 8): the newsletter subsection is inert scaffolding until slice 9 (no `Lead` model yet); the "Kontakt speichern" vCard `InfoCard` is not reproduced (`docs/rewrite/intentional-changes.md`, extends the sixth-slice mail-footer decision) — only the support `mailto:` `InfoCard` renders |
| Data displayed         | Current user's name/avatar, newsletter confirmation status                                                                                                                                                                                                                                          |
| Actions                | Change avatar; subscribe/unsubscribe to newsletter; delete account                                                                                                                                                                                                                                  |
| Navigation             | Reached from `NavHeader`'s "Account" menu item                                                                                                                                                                                                                                                      |
| Validation             | Newsletter privacy-consent checkbox required (Formik)                                                                                                                                                                                                                                               |
| Loading state          | Three stacked `Skeleton` bars in the newsletter subsection while `loading`. Nusszopf 2: no equivalent — the page is server-rendered (Livewire/Blade), so there is no async client-side fetch for this data to show a loading state for |
| Empty state            | N/A                                                                                                                                                                                                                                                                                                 |
| Error state            | Toast on any failed mutation                                                                                                                                                                                                                                                                        |
| Success state          | Newsletter subscribe/unsubscribe → loading→success/error toast; delete account → loading→success toast, then `logout()`, then redirect home. Nusszopf 2: redirects to `/search`, not `/` — `/` is itself a temporary redirect to `/search` (routes/web.php, pending Home in slice 10) and would lose the flashed toast |
| Responsive behavior    | Two-column layout stacks below `lg`; text alignment flips `text-center sm:text-left` in one subsection                                                                                                                                                                                              |
| Authorization behavior | Own account only — no route param, always "me"                                                                                                                                                                                                                                                      |
| URL/query params       | None                                                                                                                                                                                                                                                                                                |
| Side effects           | Delete account cascades to delete all owned projects/requests/analytics; logs out; avatar change fires the (fixed, per BUG-004) avatar-storage cleanup. Nusszopf 2 (slice 8): account deletion also removes every owned project's search-index documents (via `Project::delete()`'s existing model events, not a raw DB cascade — `docs/rewrite/open-questions.md`, "Account deletion and orphaned external state") and the avatar file on local disk |

## Legal Notice / Legal Policy / Privacy (`/legalNotice`, `/legalPolicy`, `/privacy`)

| Field                  | Spec                                                                                                                      |
|------------------------|---------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Static legal content (Impressum, AGB, Datenschutz)                                                                        |
| Access                 | Public                                                                                                                    |
| Layout                 | `bg-steel-200`, header with `goBackUri`                                                                                   |
| Components             | Static content rendered from CMS data arrays                                                                              |
| Data displayed         | Legal copy (Unknown exact text pending content pass — do not invent)                                                      |
| Actions                | Back navigation only                                                                                                      |
| Navigation             | `/legalNotice`/`/legalPolicy` always return to `/`; `/privacy` returns to `?back=1`'s referring page if present, else `/` |
| Validation             | N/A                                                                                                                       |
| Loading state          | None                                                                                                                      |
| Empty state            | N/A                                                                                                                       |
| Error state            | Standard global error page                                                                                                |
| Success state          | N/A                                                                                                                       |
| Responsive behavior    | Standard `Frame` responsive container                                                                                     |
| Authorization behavior | None — fully public                                                                                                       |
| URL/query params       | `/privacy?back=1` — changes the back-button's destination                                                                 |
| Side effects           | None                                                                                                                      |

## Newsletter subscribe confirmation (`/newsletter/subscribe/{token}`)

| Field | Spec |
|---|---|
| Purpose | Complete the newsletter double opt-in |
| Access | Public, token-gated |
| Layout | Centered `FramedCard` |
| Components | Success message with confirmed email; logo linking home |
| Data displayed | Confirmed email address (on success only — failure never renders this screen) |
| Actions | None beyond implicit "go home" via logo |
| Navigation | Reached only via the subscribe-confirmation email link |
| Validation | Token verified server-side (signed JWT, per historical evidence) before render |
| Loading state | N/A (SSR-resolved before render) |
| Empty state | N/A |
| Error state | Invalid/expired token → server-side 307 redirect to `/404`; thrown error → redirect to `/500`. No "link expired" specific messaging (see `docs/rewrite/open-questions.md`) |
| Success state | Confirmation message |
| Responsive behavior | Standard `FramedCard` |
| Authorization behavior | Token possession is the only "authorization" — no login required |
| URL/query params | `token` route param |
| Side effects | `Lead.hasConfirmed` flips to true; triggers list-sync side effect |

## Newsletter unsubscribe confirmation (`/newsletter/unsubscribe/{token}`)

| Field                  | Spec                                                                                                                 |
|------------------------|----------------------------------------------------------------------------------------------------------------------|
| Purpose                | Complete a token-based newsletter unsubscribe                                                                        |
| Access                 | Public, token-gated                                                                                                  |
| Layout                 | Centered `FramedCard`                                                                                                |
| Components             | Success message; primary CTA is a `mailto:` "contact us" button (not "go home", unlike the subscribe-confirm screen) |
| Data displayed         | Confirmation message                                                                                                 |
| Actions                | `mailto:` contact                                                                                                    |
| Navigation             | Reached only via the unsubscribe email link                                                                          |
| Validation             | Token verified server-side before render                                                                             |
| Loading state          | N/A                                                                                                                  |
| Empty state            | N/A                                                                                                                  |
| Error state            | Same 307-redirect pattern as subscribe-confirm                                                                       |
| Success state          | Confirmation message                                                                                                 |
| Responsive behavior    | Standard `FramedCard`                                                                                                |
| Authorization behavior | Token possession only                                                                                                |
| URL/query params       | `token` route param                                                                                                  |
| Side effects           | `Lead` row deleted; triggers list-sync side effect                                                                   |

## Newsletter unsubscribe by email (`/newsletter/unsubscribe/lead`)

| Field                  | Spec                                                                                                                                   |
|------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| Purpose                | Unsubscribe without a token, by typing an email                                                                                        |
| Access                 | Public                                                                                                                                 |
| Layout                 | Plain form                                                                                                                             |
| Components             | Email input, submit button (hard-coded label "Abmelden" — a confirmed minor CMS inconsistency vs. every other page's data-driven copy) |
| Data displayed         | None                                                                                                                                   |
| Actions                | Submit email to unsubscribe                                                                                                            |
| Navigation             | Standalone entry point (not linked from an email — used when a token link isn't available/used)                                        |
| Validation             | Yup email format                                                                                                                       |
| Loading state          | Standard toast pattern                                                                                                                 |
| Empty state            | N/A                                                                                                                                    |
| Error state            | Toast on failure                                                                                                                       |
| Success state          | Toast on success                                                                                                                       |
| Responsive behavior    | Standard `Frame` container                                                                                                             |
| Authorization behavior | None — email is the only "credential", matching the same weak-identification pattern as the historical product                         |
| URL/query params       | None                                                                                                                                   |
| Side effects           | Deletes the matching `Lead` row; triggers list-sync side effect                                                                        |

## Error screens (`/404`, `/500`, any other status, render errors)

| Field                  | Spec                                                                   |
|------------------------|------------------------------------------------------------------------|
| Purpose                | Uniform error presentation                                             |
| Access                 | Public (reachable regardless of auth state)                            |
| Layout                 | `FrameFullCenter`, `bg-warning-200`                                    |
| Components             | Single `ErrorPage` component, parameterized by an optional status code |
| Data displayed         | Status code (if any) + message + support-contact link                  |
| Actions                | "Back to home" button                                                  |
| Navigation             | Terminal — no further app navigation expected                          |
| Validation             | N/A                                                                    |
| Loading state          | N/A                                                                    |
| Empty state            | N/A                                                                    |
| Error state            | This *is* the error state                                              |
| Success state          | N/A                                                                    |
| Responsive behavior    | Centered at all breakpoints                                            |
| Authorization behavior | None                                                                   |
| URL/query params       | N/A                                                                    |
| Side effects           | Render-error case logs to console in non-production only               |

## Authentication screens (Login/Register, Forgot Password, Set New Password)

These historically lived in two separate Auth0-hosted Next.js apps (`auth-login`, `auth-password` —
see `docs/authentication/README.md` §1) and become ordinary routes inside the Nusszopf 2 monolith;
there is no historical 1:1 route to cite, so this entry describes the target Laravel routes by the
behavior they must reproduce. Full field/validation/copy detail: `docs/authentication/README.md` §2–4.

| Field         | Login/Register (combined, tab-switched)                                                                                                                                                                           | Forgot Password                     | Set New Password                                  |
|---------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------|---------------------------------------------------|
| Access        | Guest-only                                                                                                                                                                                                        | Guest-only                          | Token-gated (reached via emailed link)            |
| Loading state | Submit-loading toast; invisible captcha reload on failure                                                                                                                                                         | Submit-loading toast                | Submit-loading toast                              |
| Error state   | Field validation (5-rule password policy on register); distinguished duplicate-username error                                                                                                                     | Generic failure toast               | Generic failure toast                             |
| Success state | Redirect to `/user/projects` (both login and registration land here)                                                                                                                                              | "Email sent" toast, stays on screen | Toast + delayed redirect to login                 |
| Validation    | See `docs/authentication/README.md` §2 for the exact per-field rules                                                                                                                                              | Valid email format                  | Same password-strength policy as registration     |
| Side effects  | Registration creates the `User` row directly (no more JIT-provisioning gap, closing the historical Auth0-rule dependency) and, if "newsletter" was checked, creates a `Lead` per BUG-011's still-pending decision | Sends a password-reset email        | Updates the password, invalidates the reset token |
| Social login  | Google only — Apple was present but never wired up historically; do not build it (BUG-012)                                                                                                                        | N/A                                 | N/A                                               |

## Not covered by this pass

Exact literal CMS copy for legal pages, home page marketing sections, and profile sponsoring link — pull verbatim from source at implementation time rather than paraphrasing.
