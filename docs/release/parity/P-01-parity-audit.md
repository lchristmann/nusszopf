# P-1 Parity audit (2026-09-23)

Exit evidence (`master-roadmap.md` §4): every `screen-specs.md` row is ticked with evidence, and every
Confirmed claim that the implementation contradicts is reconciled in the docs. This page also covers
§7.1 items 1, 2, 4, 5 and 6. Item 7 (authorization allow/deny per row) is in P-4.

Test names abbreviate `tests/Feature/<Area>/<Name>Test.php` as **F:Name** and
`tests/E2E/specs/<actor>/<name>.spec.ts` as **E:name**. Every screen also has phone, tablet and desktop
screenshots compared with the historical app (**V**, `docs/testing/visual-regression.md`), which covers
"Layout", "Components" and "Responsive behavior" visually.

## Route inventory (§7.1.1)

| Historical route | Nusszopf 2 | Evidence |
|---|---|---|
| `/` | `home` | F:HomePage, E:public-shell, V |
| `/search` | `search` | F:SearchPage, E:search, V |
| `/projects/[id]` | `projects.show` | F:ProjectDetail(Content), E:project-detail, V |
| `/user/projects` | `projects.mine` | F:MyProjects, E:my-projects, V |
| `/user/project/create` | `projects.create` | F:ProjectWizard, E:project-wizard, V |
| `/user/project/[id]/edit` | `projects.edit` | F:ProjectEdit, E:project-journey, V |
| `/user/profile` | `profile` | F:ProfilePage, E:profile, V |
| `/legalNotice`, `/legalPolicy`, `/privacy` | same paths | F:LegalPages, E:public-shell, V |
| `/newsletter/subscribe/[token]`, `/newsletter/unsubscribe/[token]`, `/newsletter/unsubscribe/lead` | same paths | F:ConfirmationPages, F:Unsubscribe, E:newsletter, V (lead form) |
| `/404`, `/500` | the error page, answered in place with the real status code for any unknown URL or failure; the Next.js pages that backed the error rendering have no route of their own | F:ErrorPages, E:public-shell, V; Replace, `tenth-slice.md` |
| `/api/login`, `logout`, `callback`, `me`, `session` (Auth0) | `login`, `logout`, Google `auth/google/*` | F:Login, F:RouteProtection, F:GoogleLogin; Replace (Auth0 gone), `docs/authentication/README.md` §7 |
| `/api/contact`, `/api/newsletter`, `/api/upload` | Livewire actions of the contact dialog, the newsletter forms and the avatar dialog | F:ContactForm, F:Subscribe, F:AvatarUpload; Replace, `docs/architecture/mapping.md` |
| `/api/sitemap` (served as `/sitemap.xml`) | `/sitemap.xml` | F:Sitemap |
| `/api/events/*` (Hasura webhooks) | model events and queued jobs | F:ProjectSearchSync, F:SearchSyncFailure; Replace |
| auth-login / auth-password apps | `/login`, `/password/forgot`, `/password/reset/{token}` | F:Login, F:Registration, F:PasswordReset, E:auth-journey, E:password-reset, V |

The one route change beyond these is the new `/contact/nusszopf-vcard.vcf` and `/robots.txt` (slice 10).

## Screen checklist (§7.1.1)

Rows: Purpose · Access · Layout · Components · Data · Actions · Navigation · Validation · Loading ·
Empty · Error · Success · Responsive · Authorization · URL · Side effects. Each is followed by its
evidence. "V" alone means the screenshot comparison is the evidence.

**Home.**
- Purpose, Access, Data, Actions: F:HomePage (sections, copy, CTAs, the mailbox), E:public-shell (Journey 1).
- Layout, Components, Responsive: V; the stacking at 375/768/1440 is also checked in E:public-shell.
- Validation and Success (newsletter): F:Subscribe, E:public-shell.
- Loading and Empty: none, as specified.
- Error: F:ErrorPages.
- Authorization: public, F:HomePage.
- Side effects: F:Subscribe (pending lead, mail).

**Search.**
- Purpose, Data: F:SearchPage, F:ProjectSearch.
- Components:
  - filter popover, load more and scroll-to-top: E:search;
  - masonry dealt left to right: E:search ("deals the hits…"), V.
- Actions and URL (`?q`, `?f[]`): F:SearchPage, E:search.
- Loading: skeleton in F:SearchPage and E:search; load-more spinner in E:search.
- Empty: the no-hits section, F:SearchPage and V.
- Error: a failed "Mehr laden" toast, F:SearchPage.
- Responsive: 1/2/3 columns, V and E:search.
- Authorization (public only, BUG-002): F:ProjectRequestSearch, F:MeilisearchIntegration.

**Project detail.**
- Access and Authorization:
  - F:ProjectDetail and F:ProjectPolicy, where a private project 404s for everyone but its owner;
  - F:ErrorPages, where a private project's 404 is identical to a missing project's.
- Components:
  - rich text, team, motto and banner: F:ProjectDetailContent;
  - requests and request dialog: F:ProjectRequestDetail, E:project-requests;
  - contact dialog: F:ContactForm, E:project-detail;
  - visitor counter: F:ProjectAnalytics, E:project-detail;
  - share: E:project-detail. The share button was broken; it was fixed in this phase.
  - report link: F:ProjectDetailContent.
- Loading: none (server-rendered), reconciled in `screen-specs.md`.
- Empty: the info card, F:ProjectRequestDetail.
- Success: the share and contact toasts, E:project-detail.
- Side effects (BUG-001): F:ProjectAnalytics.
- Layout and Responsive: V.

**My Projects.**
- Access: F:MyProjects, F:RouteProtection.
- Components and Actions (open, edit, toggle with its 1/s throttle, delete): F:MyProjects, E:my-projects.
- Loading (skeleton) and Empty (welcome card): F:MyProjects.
- Success toasts: F:MyProjects.
- Header, masonry and the two create buttons: V. The header was invented; it was fixed in this phase.
- Side effects (re-index): F:MyProjects.

**Project creation.**
- Validation, URL (`?step`), no draft, loading and error toasts: F:ProjectWizard, F:ProjectRequestWizard, E:project-wizard.
- Success: E:project-journey.
- Authorization (owner forced): F:ProjectWizard.
- Side effects (atomic creation, index only when public): F:ProjectRequestWizard, F:ProjectSearchSync.
- Responsive: E:project-wizard (phone), V.

**Project edit.**
- Access (owner only, BUG-021): F:ProjectEdit.
- Components and Actions: F:ProjectEdit, F:ProjectRequestEdit, E:project-journey, E:project-requests.
- Loading: `SkeletonView`, reproduced in this phase, F:ProjectEdit.
- Empty: F:ProjectRequestEdit.
- Success, including the discard `confirm()`: E:project-journey.
- Side effects: F:ProjectSearchSync.
- Layout: V.

**Profile.**
- Access: F:ProfilePage.
- Components: the avatar dialog (F:AvatarUpload, E:profile), the newsletter subsection (F:ProfileNewsletter, E:newsletter), the info cards (F:ProfilePage).
- Validation: F:ProfileNewsletter.
- Loading: none (server-rendered, `eighth-slice.md`).
- Success and side effects: F:DeleteAccount, E:profile.
- Layout: V. Frame and colours were fixed in this phase.

**Legal pages.**
- Data (operator Markdown), Navigation (`?back`) and Responsive: F:LegalPages, E:public-shell, V.

**Newsletter confirmation pages.**
- Validation (token), Error (in-place 404) and Side effects: F:ConfirmationPages, F:NewsletterToken, E:newsletter.

**Newsletter unsubscribe by e-mail.**
- Validation, toasts and throttle: F:Unsubscribe.
- Layout: V.

**Error screens.**
- Every status, the layout and the support link: F:ErrorPages, E:public-shell, V.

**Authentication screens.**
- Access (guest-only): F:RouteProtection.
- Validation: F:Registration, F:PasswordReset.
- Loading and error toasts: F:Registration ("shows the historical loading toasts…"), F:Login. These were fixed in this phase.
- Success: F:Login, F:PasswordReset, E:auth-journey.
- Side effects: F:Registration (lead, mails), F:PasswordReset.
- Social login: F:GoogleLogin.
- Layout: V. The card and logo were fixed in this phase.

## Workflows and journeys (§7.1.2)

| Workflow / journey | Playwright evidence |
|---|---|
| Journey 1 — landing page CTAs | E:public-shell ("Journey 1"); the create CTA is retired (stale historical selector) |
| Journey 2 — registration, logout, login | E:auth-journey (new in this phase: through the nav menu, login by username) |
| Journey 3 — project lifecycle incl. requests, update visible on My Projects, delete removes from search | E:project-journey, E:project-requests, E:first-slice-journey (preview and search re-check added in this phase) |
| Journey 4 — account settings (username on the avatar, delete account → Home) | E:auth-journey, E:profile |
| Journey 5 — search, filter, contact from a result | E:search |
| Journey 6 — newsletter subscribe/confirm/unsubscribe (all three paths) | E:newsletter, E:public-shell |
| Journey 7 — contact a project owner | E:project-detail |
| Journey 8 — avatar upload/crop | E:profile |
| Password reset, login lockout | E:password-reset, E:login-lockout |
| Workflows: account creation, publish, create with requests, contact, account deletion, picture replacement, newsletter | the journeys above; `docs/domain/workflows.md` |

## Bugs and open questions (§7.1.4, §7.1.5)

- `bugs.md` has 38 entries; Unknown = 0. BUG-016/017/019 summary rows were reconciled with their bodies,
  and the BUG-013/014/015 bodies with their decided rows.
- Every **Fix** has its regression test. The IDs are cited in the tests, or named in the bug's
  "Regression test" line. BUG-025's "Personen" was the only unpinned one; it is pinned now.
- Every **Preserve** is pinned: BUG-013 (the `confirm()` dialogs in the E2E specs), BUG-015 (F:Login),
  BUG-028 (F:ProjectRequestWizard, E:project-requests), BUG-038 (F:SeoTags). BUG-014 is dead vocabulary,
  so there is no behavior to pin.
- New in this phase: BUG-038 (the sitemap lists `noindex` project pages), Preserve.
- `open-questions.md`: no Unknown, unowned entry. The two remaining evidence notes are closed with an owner.

## Scaffolding (§7.1.6)

No open "Intentional scaffolding" row in any slice document (`00-doc-debts.md`).

## Defects found and fixed in this phase

1. The "Teilen" button did nothing: an uncompiled `@js()` sat inside a component tag. It is fixed, with
   a static guard test against the pattern.
2. The edit screen lacked `SkeletonView`.
3. Registration had no abuse limit after Auth0's captcha went away. It is now limited per IP
   (`intentional-changes.md`).
4. The project page had lost its historical `noindex` (BUG-038).
5. A Firefox race in the password-reset spec; it was a test defect.
6. Everything the visual comparison found (P-2).

## Result

**Done.** Every row above has evidence; the docs contradicted by the implementation were reconciled
in the same commits.
