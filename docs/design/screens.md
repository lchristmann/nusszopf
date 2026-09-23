# Screens

Source: `historical/web-nusszopf/projects/webapp` (Next.js pages router) unless noted. All routes are French... no — all copy is German (`<Html lang="de">`, confirmed in `projects/webapp/src/pages/_document.js`). Nusszopf's tagline in the README is "Network for joint idea and project processes" — the product lets users publish **projects** and attach **requests** (Gesuche) for help, and lets other users find and respond to them.

Evidence order used below: E2E specs (`projects/e2e/cypress/integration/*.spec.js`) > page implementation > ui-library stories > data/CMS files.

## Route inventory (Confirmed — from `src/pages/**`)

| Route | File | Auth | Purpose |
|---|---|---|---|
| `/` | `pages/index.js` | Public (`isAuthRequired: false`) | Landing page |
| `/search` | `pages/search.js` | Public | Project/request search |
| `/projects/[id]` | `pages/projects/[id].js` | Public, SSR (`getServerSideProps`) | Project detail |
| `/user/projects` | `pages/user/projects.js` | Required | "My projects" dashboard |
| `/user/project/create` | `pages/user/project/create.js` | Required | 4-step project creation wizard |
| `/user/project/[id]/edit` | `pages/user/project/[id]/edit.js` | Required | Project edit (3 tabs) |
| `/user/profile` | `pages/user/profile.js` | Required | Account settings |
| `/legalNotice` | `pages/legalNotice.js` | Public | Impressum |
| `/legalPolicy` | `pages/legalPolicy.js` | Public | Terms/AGB |
| `/privacy` | `pages/privacy.js` | Public | Privacy policy |
| `/newsletter/subscribe/[token]` | `pages/newsletter/subscribe/[token].js` | Public, SSR | Newsletter double opt-in confirmation |
| `/newsletter/unsubscribe/[token]` | `pages/newsletter/unsubscribe/[token].js` | Public, SSR | Newsletter unsubscribe confirmation (from email link, has token) |
| `/newsletter/unsubscribe/lead` | `pages/newsletter/unsubscribe/lead.js` | Public | Newsletter unsubscribe form (no token — user types email) |
| `/404` | `pages/404.js` | Public | Not found |
| `/500` | `pages/500.js` | Public | Server error |
| `/api/*` | `pages/api/*.js` | — | Next.js API routes: `login`, `logout`, `callback`, `me`, `session` (Auth0 handlers — see `docs/authentication/README.md`), `contact`, `newsletter`, `upload`, `sitemap`, `events/leads`, `events/search`, `events/users` |

All page components are wrapped in `withAuth(Component, { isAuthRequired })` (`src/utils/hoc/withAuth.js`), which is the single authorization gate for every screen. **Confirmed.**

Every page renders through the shared `<Page>` component (`src/components/Page/Page.js`), which supplies SEO tags (`next-seo`, German locale `de_DE`), an `ErrorBoundary`, an optional `NavHeader`, the page content, and a `Footer`. **Confirmed.**

## Home (`/`)

Confirmed from `pages/index.js` + `src/assets/data/*.data.js`. No `NavHeader` (`navHeader={{ visible: false }}`) — the landing page has its own header. Sections, top to bottom:

1. **Header** — two-column hero (`bg-steel-50`): Nusszopf logo (`assets/logos/nusszopf-logo-*.svg`) on one side, title/subtitle (`headerData.title/subtitle`) on the other. Below it, a highlighted info card (`bg-livid-300`) with an ordered list explaining how the product works (`headerData.info[2]`, rendered as `<ol>` — Confirmed this is a numbered "how it works" explainer, content itself is CMS data not yet transcribed).
2. **HowToSection** (`containers/home/HowToSection`, `bg-yellow-250`) — "How To Nusszopf (Alte Version)": four static `StepCard`s (Idee! / Projekt / Gesuche / Umsetzung; step 3 shows the `Request` icon instead of a number) and one CTA "Alte Version entdecken" → `/search`. **Confirmed** (slice 10).
3. **CarouselSection** — present in code but **commented out** in `pages/index.js` (`{/* <CarouselSection /> */}`). **Confirmed dead/disabled section** — historical page does not render it even though the component and its container still exist. Candidate for `docs/rewrite/open-questions.md` (not populated by this pass — flag for the synthesis step).
4. **About** section (`bg-turquoise-300`) — 3-column feature list (`homeData.about.list`).
5. **Contest** section (`bg-red-300`) — heading/description/link plus a sponsor/partner logo (`contestData.host`).
6. **Fellows** section (`bg-pink-200`) — sponsor/fellow logos row plus a 3-column options list, each with its own CTA button.
7. **NewsletterSection** (`containers/home/NewsletterSection`) — newsletter subscribe form, styled section.

Footer variant: `classy` (legal links + Instagram + Vercel badge — see `docs/design/navigation.md`). Implemented in slice 10 (`docs/rewrite/tenth-slice.md`), copy verbatim; the Vercel badge is not reproduced (decision 5).

## Search (`/search`)

Confirmed from `pages/search.js`. `NavHeader` visible. Layout:

1. Header frame (`bg-moss-300`) containing an `<h1>` title and `<SearchInput>`.
2. Results area: three states —
   - **Initial** (`isInitial`): `<SkeletonHits>` placeholder grid.
   - **Has results** (`groupedHits.length > 0`): a `Masonry` grid (breakpoints: 3 cols default, 2 cols ≤1023px, 1 col ≤639px — Confirmed literal values in `search.js`) of `<HitCard>`, one per **project**, grouped so a project's matching requests appear nested inside its card (`groupedHits` keyed by project id — Confirmed by `HitCard` receiving `hits` array grouped by `projectId`).
   - **No results**: `<NoHitsSection>`.
3. "Load more" button, shown only if `hits.nbHits > hits.hits.length`, with a spinner (`Loader` icon, `animate-spin`) while loading more.
4. A floating "scroll to top" circular button fixed to the bottom-right (`fixed bottom-0 right-0 m-6`, `size="circle"`, chevron-up icon).

Search itself is powered by `useSearch()` (`src/utils/services/search.service.js`) — see `docs/search/README.md` for the Meilisearch-side evidence; this document only records the UI shape.

**Suspected incomplete test coverage:** the E2E spec for this screen (`_search.spec.js`) is disabled (`xcontext`) and its three tests are stubs (`expect(true).to.equal(true)`) — search behavior was apparently never actually covered by the historical E2E suite, despite being a core surface. Record in `docs/rewrite/open-questions.md` during synthesis.

## Project detail (`/projects/[id]`)

Confirmed from `pages/projects/[id].js`. Public, but SSR resolves the current Auth0 session server-side to know if the viewer is the owner (`userId`, defaults to `'anonymous'`). `getServerSideProps` returns `notFound: true` (→ Next.js 404) if the project id does not resolve — **Confirmed** 404 is data-driven, not just route-driven.

Layout:
1. `<Banner>` (owner-only editing banner/visibility indicator — container not fully inspected, **Inferred** from name + `userId` prop).
2. Header card (`bg-lilac-300`): title, goal/subtitle, location (with OpenStreetMap link if geocoded, `MapPin` icon) and formatted period (`de-DE` locale dates, `Calendar` icon), plus two action buttons: **Contact** (`Send` icon) and **Share** (`Share2` icon, uses native `navigator.share` when available, falls back to copy-to-clipboard with a success toast).
3. Two-column body: left = rich-text "What" (goal description), optional "Who" (team) and "How" (motto) sections, then a visitor counter (`<VisitorCounter>`); right = list of `<RequestCard variant="view">` (or an `<InfoCard>` empty state if there are no requests) plus the project owner's `<Avatar variant="project">`.
4. A "report" link at the bottom-right (`mailto:` link with the project ID appended to the subject, `AlertTriangle` icon) — **Confirmed** abuse-reporting is a plain mailto, not an in-app form.
5. `<RequestDialog>` opens when a visitor clicks a request card; `<ContactDialog>` opens if the project's contact channel is the platform's own inbox (`contact === NZ_EMAIL`, i.e. `mail@nusszopf.org`) — otherwise **Contact** just opens the visitor's mail client directly to the project's configured contact address (`mailto:`). **Confirmed** dual contact paths depending on project configuration.

View counting: increments project analytics via GraphQL mutation once per browser per project, tracked in `localStorage` under `nusszopf_viewed_projects` (own-project views are excluded by comparing `userId`). **Confirmed** — this is client-side, unauthenticated-friendly, and not spoof-proof (any user can clear `localStorage` to re-count); flag as a possible "known limitation, not a bug" item rather than something to silently fix.

## User Projects (`/user/projects`)

Confirmed from `pages/user/projects.js`. Auth required. Header card with the user's `<Avatar>` and a "create project" button (desktop: in the header row; mobile: full-width button above the list — both link to `/user/project/create?step=0`). Body states:

- **Loading**: `<ProjectsSkeleton>`.
- **Has projects**: `Masonry` grid of `<EditProjectCard>`, each supporting click-through to the public project page, edit, delete, and a visibility toggle (public/private) throttled to 1/second.
- **Empty**: `<WelcomeCard>` with a title/description/greeting — this is the new-user empty state.

## Project creation (`/user/project/create`)

Confirmed from `pages/user/project/create.js` + `_projects.spec.js`. A 4-step wizard (`Stepper`/`useStepper` from ui-library) inside a `FramedGridCard`, with a `Progressbar` in the header showing the current step label and progress:

1. **Description step 1**: title, goal, description (rich text), period (fixed dates or "flexible" radio), location (place search or "remote" radio). Fields confirmed via E2E selectors: `input_project-description`, `input_project-title`, `input_project-goal`, `radio_flexible_project-period`, `radio_remote_project-location`.
2. **Description step 2**: team (rich text), motto. E2E selectors: `input_project-team`, `input_project-motto`.
3. **Requests step**: add one or more "requests" (Gesuche) via a dialog (`EditRequestDialog`) with title, description, and a category select (`select_request-category`, categories: companions/rooms/materials/financials/others — `REQUEST_CATEGORY` enum in `src/utils/enums.js`). A project can also have zero requests (step is skippable — confirmed no `type="submit"` blocking here in the E2E happy path is not fully conclusive; **Inferred** requests are optional since `initialValues.requests = []`).
4. **Settings step**: visibility (public/private, default `public`) and `contact` (boolean). **Resolved (contact field)** — `project-form.data.js` confirms the field is presented as a radio choice: "Persönlich" (the project's own configured contact email is shown/used directly, `mailto:`) vs. "Über Nusszopf" (contact is routed through the platform's own inbox, i.e. `contact === NZ_EMAIL`, opening `<ContactDialog>` instead of a direct `mailto:`) — confirming the mechanism inferred from `[id].js`'s `NZ_EMAIL` check.

   **Resolved (pre-implementation review pass, 2026-09-18):** `pages/projects/[id].js`'s `getServerSideProps` was read in full, along with `utils/libs/apolloClient.js`. It uses the **viewer's own session token** (or none, falling through to the `anonymous` role) for its `GET_PROJECT` query — never an elevated/admin credential — so Hasura's `visibility`-based `select_permissions` filter applies exactly as documented, and a private project genuinely 404s for any non-owner, including via direct URL. The "private might mean unlisted-but-reachable" speculation floated during archaeology is confirmed **false**. Full detail: `docs/rewrite/open-questions.md`, "Does `/projects/{id}`'s SSR enforce `visibility`, or only existence?".

Submission validates both step-1 and step-2 Yup schemas again defensively before calling `addProject`, and shows an error toast (`cms.notify.project.errors[1]`) if that defensive check fails even though the stepper considered the form complete — **suspected latent inconsistency between per-step validation and final validation**, worth a source read of `step1ValidationSchema`/`step2ValidationSchema` before deciding if it's a real historical bug (not resolved in this pass; record as open question).

A source-code comment in the E2E spec notes a workaround: "the slate-editor has to be edited first, else it would not work" (`_projects.spec.js` line 2, referencing `ianstormtaylor/slate#3476`) — **Confirmed** historical bug/workaround in the rich-text editor library affecting form completion order; a genuine candidate for `docs/rewrite/open-questions.md` (does the replacement rich-text approach need this workaround, or does it go away with a new editor?).

## Project edit (`/user/project/[id]/edit`)

Confirmed from `pages/user/project/[id]/edit.js`. Auth required; redirects to `/404` if the project doesn't resolve. Header shows the project title and a `<Select>` with three tab-like values (hard-coded German labels in the page itself, not CMS data — **Confirmed** literal array `['Beschreibung', 'Gesuche', 'Einstellungen']`, i.e. Description / Requests / Settings):

- **Beschreibung** → `<ProjectView>` (edit title/goal/description/etc., confirmed via E2E `input_project-title`, `btn_save_project-view`).
- **Gesuche** → `<RequestsView>` (list of requests with a per-request contextual menu — confirmed via E2E `menu_edit-request-card`, `menuitem-0`/`menuitem-1` — offering **Edit** and **Delete** actions).
- **Einstellungen** → `<SettingsView>` (project settings + **Delete project** button, confirmed via E2E `btn_delete_settings-view`).

Switching tabs while there are unsaved changes triggers a native browser `confirm()` dialog ("Do you want to discard changes?", `cms.alert`) — **Confirmed**, uses `window.confirm`, not a custom-styled dialog, unlike other confirmation flows in the app that do have custom UI (see `docs/design/states.md`).

Loading state: `<SkeletonView>` while project data or user data is loading.

## Profile / account settings (`/user/profile`)

Confirmed from `pages/user/profile.js`. Auth required. A `FramedGridCard` with:

- **Header**: title + `<Avatar variant="settings">` with an edit affordance that opens `<AvatarDialog>`.
- **Newsletter** subsection: if the user's `lead` record has not confirmed (`!user.data.lead?.hasConfirmed`), shows a subscribe form (privacy-consent checkbox, required) with a Formik-validated checkbox; if already confirmed, shows an unsubscribe button (native `confirm()` prompt).
- **Sponsoring** subsection: static text + button linking out (`cms.sponsoring.action.href`) — likely a donation/support link (**Inferred**, exact target Unknown without reading `assets/data/profile.data.js`).
- **Delete account** subsection (in a second column): destructive action, native `confirm()` prompt, then GraphQL `deleteUser` mutation, then `logout()`, then a success toast. E2E selector `btn_delete-account_settings-page` confirms this exact flow (`_settings.spec.js`).
- Two `<InfoCard>` blocks: a link to a contact document/file, and a `mailto:` support link.

Loading state: `<Skeleton>` bars in place of the newsletter subsection while `loading` is true.

## Legal Notice / Legal Policy / Privacy (`/legalNotice`, `/legalPolicy`, `/privacy`)

Confirmed from the three page files. All three are static content pages (`bg-steel-200`) rendered from CMS data arrays (`legalNoticeData`, `legalPolicyData`, `privacyData`), with a `goBackUri` in the header (`/legalNotice`, `/legalPolicy` always go back to `/`; `/privacy` goes to `router.query?.back ? 'back' : '/'` — i.e. Privacy can be deep-linked with a `?back=1` query param to make the back button return to the referring page instead of home — **Confirmed**, a small but real piece of navigation behavior). Content itself (the legal copy) was not transcribed in this pass — CMS-driven, treat as **Unknown** pending a dedicated content-archaeology pass; do not invent legal copy. **Nusszopf 2 (slice 10, decision A-4):** the pages render the operator's own Markdown (`docs/deployment/README.md`, "Legal pages"); the historical texts are transcribed only as labelled examples in `docs/deployment/legal-examples/`.

## Newsletter confirmation pages

Confirmed from the three `newsletter/*` pages. All render a centered `<FramedCard>` with the Nusszopf logo linking home:

- **Subscribe confirm** (`/newsletter/subscribe/[token]`): SSR calls `confirmNewsletterSubscription(token)`; on success shows a "you're subscribed" message with the confirmed email address; on failure, **redirects (307) to `/404`**; on thrown error, redirects to `/500`. **Confirmed** — double opt-in confirmation is server-verified before render, not client-side.
- **Unsubscribe confirm** (`/newsletter/unsubscribe/[token]`): same SSR pattern with `confirmNewsletterUnsubscription(token)`; success message includes a `mailto:` "contact us" action instead of a "go home" button (`variant="button" type="mail"`) — **Confirmed** difference in the two confirmation screens' primary CTA.
- **Unsubscribe by email** (`/newsletter/unsubscribe/lead`): a plain form (no token) where the user types their email to unsubscribe (`useNewsletter().unsubscribeFromNewsletter`), with Yup email validation. This is the path used when a user doesn't have/use a unique unsubscribe token link. Submit button label is hard-coded German text "Abmelden" directly in the component (not CMS data) — **Confirmed** minor CMS inconsistency (every other page pulls copy from `assets/data`; this one button label does not).

## Error screens (`/404`, `/500`, render errors)

Confirmed from `pages/404.js`, `pages/500.js`, `pages/_error.js`, `components/ErrorPage/ErrorPage.js`, `components/Page/ErrorBoundary.js`. A single `<ErrorPage statusCode>` component is reused for all three cases (Next.js custom 404/500 pages, the catch-all `_error.js` for any other status code, and any uncaught render error caught by `ErrorBoundary`). Layout: centered (`FrameFullCenter`, `bg-warning-200`), heading with the numeric status code prefixed (e.g. "404 – …"), a message with a support link, and a "back to home" button. See `docs/design/states.md` for the full error-state catalogue (this is the *page-level* error state; there are also inline/toast error states).

## Not covered by this pass

- `auth-login` and `auth-password` screens (separate Next.js apps; hosted at `auth.nusszopf.org`) — covered in `docs/authentication/README.md`, not duplicated here.
- Exact CMS copy in `src/assets/data/*.data.js` (headings, body text, button labels) — the structural/behavioral shape is documented above; literal German copy should be pulled verbatim in a follow-up content pass rather than paraphrased.
- `HowToSection`, `CarouselSection` (disabled), `Banner`, `VisitorCounter`, `AvatarDialog`, `EditRequestDialog`, `ProjectForm/*` field-level components — named and positioned above but not opened line-by-line.
