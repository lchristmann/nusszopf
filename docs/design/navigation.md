# Navigation

Source: `historical/web-nusszopf/projects/ui-library/stories/organisms/NavHeader/*`, `Footer/*`, and `webapp/src/components/Page/Page.js`. Evidence order: E2E > implementation.

## Global navigation shell

Every screen is wrapped in `<Page>` (`webapp/src/components/Page/Page.js`), which conditionally renders:

- `<NavHeader>` — only when the page passes `navHeader={{ visible: true }}`. The Home page (`/`) explicitly hides it (`visible: false`) and supplies its own in-hero branding instead. **Confirmed.**
- page `<main>` content.
- `<Footer>` — always rendered, with a `variant` prop controlling which footer layout is used (see below).

There is **no persistent sidebar and no desktop horizontal nav bar** — navigation is a single top bar plus a hamburger-triggered dropdown menu at every breakpoint (the `NavHeader` component renders the same `MenuButton`/`Menu` structure regardless of viewport size; there is no separate desktop nav-links row in the source). **Confirmed** — this is a deliberate, simple IA: one nav pattern for all screen sizes, not a mobile-only hamburger with a desktop-only nav bar as is common in later web conventions.

## NavHeader (top bar)

Source: `ui-library/stories/organisms/NavHeader/NavHeader.organism.js`.

Structure (`Frame as="nav"`, `bg-steel-400 text-steel-800`, `sticky top-0` when `fixed` — the default):

- **Left**: Nusszopf logo (`NusszopfHeaderLogo`, 25px mobile / 30px desktop icon-only mark, not the full wordmark used on Home), clickable, navigates to `/` (or the external marketing site root if `mode="external"`). If a `goBackUri` prop is supplied, a back-chevron (`ChevronLeft`, 28px) appears to its right; clicking it does `router.push(goBackUri)`, or `router.back()` if `goBackUri === 'back'` (used by `/privacy?back=1`, see `docs/design/screens.md`).
- **Right**: a search icon (`Search`, always visible, links to `/search`), then — **only if the user is authenticated** — a "Nuss" icon (custom icon, `size=21.5`) linking to `/user/projects`, then a hamburger button (`Menu` icon from `react-feather`) that opens a `reakit` `Menu` dropdown.

Scroll behavior: a `shadow` class is added to the nav bar once `window.scrollY > 0` (only when `fixed`). **Confirmed**, a purely cosmetic scroll-elevation effect, no content change.

### Hamburger dropdown menu

Rendered via `reakit`'s `Menu`/`MenuButton`/`useMenuState` (`placement: 'bottom-end'`, `animated: 150` — 150ms open/close animation, `reakit-animate-scale` class), styled as a rounded card (`rounded-md shadow-md bg-steel-400`) anchored to the bottom-right of the button. Items, in order, **conditionally rendered**:

1. **Search** — always present.
2. **Create project** (`PlusCircle` icon) — only when `mode === 'internal'` (i.e. not shown on the external marketing-site variant of the header). If unauthenticated, clicking it still routes straight to `/api/login` rather than showing an error — **Confirmed**, login is deferred to the destination action rather than gating the menu item itself.
3. If authenticated: **My projects** (Nuss icon) and **Account** (truncated to the user's name, max 12 chars, `User` icon) linking to `/user/profile`.
   If not authenticated: a single **Log in / Sign up** item (`LogIn` icon) that calls `router.push('/api/login')` (or the external login URL in `mode="external"`), after first firing a `loading`-type toast notification ("logging in…").
4. If authenticated: **Log out** (styled in `text-warning-700`, i.e. the app's warning/red-orange color, visually distinct from the other items) — fires a loading toast then calls the `logout()` handler from the auth service.

Every interactive element carries a `data-test="..."` attribute (`btn_burger_nav-header`, `btn_login_nav-header`, `btn_logout_nav-header`, `btn_search_nav-header`, `btn_user-projects_nav-header`, `btn_go-back_nav-header`, `btn_settings_nav-header`, `btn_create-project_nav-header`, `btn_logo_nav-header`) — these are the actual selectors the historical E2E suite drives navigation with (`_auth.spec.js`, `_landingpage.spec.js`). **Confirmed** — any Playwright rewrite of these journeys should reuse equivalent stable test hooks.

`mode` prop (`internal` | `external`) lets the same `NavHeader` component be reused by a second, external-facing site. **Resolved/Confirmed**: `mode="external"` is used by both `auth-login/src/containers/Page/Page.js` and `auth-password/src/containers/Page/Page.js` — both apps' shared page shell renders `<NavHeader mode="external" />` and `<Footer variant="auth0" className="bg-white sm:bg-steel-100" />` unconditionally on every screen. This is not dead code; it is the standard chrome for the entire authentication sub-app pair (`auth.nusszopf.org`), distinct from `webapp`'s own `internal`-mode header. See `docs/design/components.md`'s Footer entry, which is corrected to match.

## Footer

Source: `ui-library/stories/organisms/Footer/Footer.organism.js`. Three variants, selected per-page via `footer={{ variant: '...' }}`:

- **`vercel`** (default) — just a centered "Powered by Vercel" badge/link. Used on most authenticated app screens (profile, projects, search, project detail, etc. use `footer={{ className: '...' }}` without an explicit `variant`, defaulting to `vercel`).
- **`auth0`** — Vercel badge plus an Auth0 "JWT Auth for open source projects" badge, side by side. **Confirmed**, and confirmed dead specifically within `webapp` (no page there passes `variant="auth0"`) but **live and load-bearing in `auth-login`/`auth-password`** — both apps' shared `Page.js` renders `<Footer variant="auth0" .../>` on every screen. Since Auth0 itself is being replaced (`docs/authentication/README.md`), this specific footer variant (and its Auth0 sponsor badge) does not need to be reproduced in Nusszopf 2's login/register/password screens — it was Auth0-program branding, not a general-purpose Nusszopf footer layout — but the surrounding page-shell pattern (a dedicated, minimal chrome for the auth screens, distinct from the main app's `vercel`-footer chrome) is worth preserving as a design decision if Nusszopf 2's own login/register screens get a visually distinct, minimal shell.
- **`classy`** — used on Home (`footer={{ variant: 'classy', className: 'bg-steel-200' }}`). Two-row (stacks on mobile, row on `md+`) layout: legal links (Legal Notice / Privacy / Legal Policy, in that order, routing to `/legalNotice`, `/privacy`, `/legalPolicy`) on the left, Instagram icon link (`instagram.com/nuss.zopf`) + Vercel badge on the right.

All three sponsor/partner logos (Vercel, Auth0, sponsor badges in the main README) reflect the project's original OSS sponsorship program — **Confirmed** historical fact, not necessarily something Nusszopf 2 needs to reproduce (a product/branding decision, not covered by this doc).

## Route-level navigation rules (cross-referenced from `docs/design/screens.md`)

- Unauthenticated users hitting an auth-required page are redirected by `withAuth` (see `docs/authentication/README.md` for the exact mechanics) rather than the page rendering a "please log in" state.
- `/user/project/[id]/edit` redirects to `/404` client-side if the project id doesn't resolve after loading (`useEffect` in the page, not a route-level guard) — **Confirmed**, this is a soft/late redirect (the shell of the page can flash before redirecting), not a hard 404 at the routing layer, unlike `/projects/[id]`'s SSR `notFound: true`.
- `/newsletter/subscribe/[token]` and `/newsletter/unsubscribe/[token]` perform **server-side 307 redirects** to `/404` or `/500` on failure (`getServerSideProps`), so these never flash the confirmation UI on an invalid/expired token. **Confirmed.** Nusszopf 2: the 404 renders in place (no redirect), with the same effect.
- Login (`/api/login`) always sets `returnTo: '/user/projects'` (`webapp/src/pages/api/login.js`) — i.e. **every** login, regardless of where it was triggered from, lands the user on their projects dashboard rather than returning them to the page they were on. **Confirmed** — a real product behavior to preserve or explicitly call out as a fixable UX gap in `docs/rewrite/open-questions.md` (not decided here).

## Not covered by this pass

- Whatever navigation exists inside `auth-login`/`auth-password` themselves (their own headers/back links) — belongs to the authentication archaeology, not duplicated here.
- `FilterPopover` (search filter UI) internal navigation/state — noted as a component in `docs/design/components.md`, not detailed as navigation.
