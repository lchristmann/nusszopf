# UI States

Source: cross-referenced from `webapp/src/pages/**`, `webapp/src/containers/**`, `ui-library/stories/**`. This catalogs loading/empty/error/success/interaction states across the product, referencing `docs/design/screens.md` and `docs/design/components.md` for the components involved rather than repeating their full descriptions.

## Loading states — Confirmed

| Context | Mechanism |
|---|---|
| Page/route transition | `LoadingIndicator` "rainbow" bar at the top of the viewport, shown only if the transition takes longer than **350ms** (`_app.js`: `setTimeout(..., 350)` on `routeChangeStart`, cleared on `routeChangeComplete`/`routeChangeError`). Debouncing the indicator this way avoids a flash on fast navigations — a deliberate, worth-preserving UX detail. |
| Search results (initial load) | `SkeletonHits` — a 3-column (1/2/3 depending on breakpoint) arrangement of variable-height `Skeleton` blocks (`bg-lilac-200`) mimicking the eventual masonry card layout. |
| Search "load more" | Spinning `Loader` icon replacing the static download icon inside the "load more" button (`animate-spin`), button remains clickable-looking but the click handler no-ops while `isLoading`. |
| Search input itself | The trailing icon inside `SearchInput` cycles between: default `Search` icon → `Loader` (spinning) while a search request is in flight → `RefreshCw` if the currently-applied filter no longer matches the pending filter selection (i.e. "your filter changed, search again") → back to `Search` once in sync. Three distinct visual states in the same slot, not a single boolean spinner. |
| User's projects list | `ProjectsSkeleton` while `loadingProjects || loadingUser`. |
| Project edit page (any tab) | `SkeletonView` while `loadingProject || loadingUser || !projectData`, shown in place of the entire tab body but the header/tab-selector chrome still renders. |
| Profile newsletter section | Three stacked `Skeleton` bars (`bg-steel-400`, decreasing widths, last one half-width) while `loading`. |
| Any async mutation (subscribe, unsubscribe, delete account, add/update/delete project or request, etc.) | A `loading`-type `Toast` notification is fired immediately on submit (`notify({ type: 'loading', message: ... })`), followed by a `success` or `error` toast on completion (**Confirmed, second slice**: `Toasts.service.js` never replaces a toast — each `notify` appends, every toast closes itself after 3 s, and all but the newest are `opacity-50`) — this is the **standard, universal pattern** for all mutation feedback across the app, not case-by-case. See "Toast/notification states" below. |
| Login/logout | Same loading-toast pattern, fired the instant the nav-menu action is clicked, *before* the actual redirect/auth call completes. |

## Empty states — Confirmed

| Context | Component / copy source | Behavior |
|---|---|---|
| Search, no results | `NoHitsSection` | Highlighted `livid` info box with a "create a project" CTA button (routes to `/user/project/create` if authenticated, else `/api/login`) — the empty state doubles as an acquisition funnel rather than a dead end. |
| User has no projects yet | `WelcomeCard` | Shown instead of the masonry grid when `data?.projects?.length === 0`; title/description/greeting copy, presumably also with a create-project affordance (component internals not opened — **Inferred** from naming/position, not fully confirmed). |
| Project has no requests | Inline `InfoCard` on the project detail page, in place of the requests list. |
| Project edit → Requests tab with none left | **Unknown** — not directly observed; `RequestsView` internals not opened in this pass. |

## Error states — Confirmed

| Context | Behavior |
|---|---|
| Route not found (`/404`, or any `getServerSideProps` returning `notFound: true`) | `ErrorPage` with `statusCode="404"`. |
| Server error (`/500`) | `ErrorPage` with `statusCode="500"`. |
| Any other HTTP status via Next's catch-all `_error.js` | `ErrorPage` with the actual `res.statusCode`/`err.statusCode` (defaults to 404 if neither is present). |
| Uncaught render error anywhere in the tree | `ErrorBoundary` (class component, `componentDidCatch`) renders `ErrorPage` with **no** status code — visually the same page, just without the "404 – " / "500 – " numeric prefix in the heading. Logged to `console.error` only in non-production. |
| Invalid/expired newsletter subscribe/unsubscribe token | Server-side 307 redirect to `/404` (or `/500` on a thrown exception) — the confirmation page never renders with bad data; there is no "this link has expired" inline message, it's a hard redirect to the generic 404 page. **Confirmed** — a candidate UX gap (no specific "link expired" messaging) worth noting in `docs/rewrite/open-questions.md`, not necessarily a bug to silently "fix" since it may be intentional simplicity. |
| Form field validation errors | Formik + Yup, rendered via `<ErrorMessage>` as italic warning-colored text (`text-warning-700`) directly under the field, e.g. profile newsletter consent checkbox, newsletter-unsubscribe email field. |
| Async mutation failure | `error`-type `Toast` (see below) — no inline error state, the form/UI otherwise stays exactly as the user left it so they can retry. |
| `ErrorPage` visual structure | Centered, `bg-warning-200`/`text-stone-800`, heading = `"{statusCode} – {message}"` or just the message with no code, body text with a support-contact link, and a "back to home" button (`bg-warning-300`). Same component for all of the above — there is exactly **one** error-page visual, parameterized only by an optional status code. |

## Success / feedback states — Confirmed

The app has a single, consistent notification mechanism: `useToasts().notify({ type, message })` from a global `ToastsProvider` (`ui-library/services/Toasts.service.js`), with at least three `type`s in observed usage: `loading`, `success`, `error`. Toast triggers observed in this pass:

- Project view-count increment: **silent**, no toast (background side effect, catches and swallows its own errors — `// error or constraints` comment in `projects/[id].js`).
- Copy project URL to clipboard (share fallback): `success` toast.
- Newsletter subscribe/unsubscribe (from profile): `loading` → `success`/`error`.
- Delete account: `loading` → `success` (then `logout()` + implicit redirect) / `error`.
- Add/update/delete project or request: **Inferred** to follow the same pattern (`useProjectsService` hook name and the profile-page precedent strongly suggest it), but the hook's internals were not opened in this pass — mark as **Inferred**, not directly confirmed.

There is no distinct "toast" visual spec captured in this pass beyond its existence and the `Toast.theme.js` file — a follow-up pass should open `Toast.molecule.js`/`Toast.theme.js` to record exact positioning, stacking, auto-dismiss timing, and per-type coloring before implementation.

## Confirmation / destructive-action states — Confirmed, and a notable inconsistency

Every destructive action in the files read in this pass (delete account, delete project, delete request, unsubscribe from newsletter, discard unsaved changes when switching edit tabs) uses the **native browser `window.confirm()` dialog**, not the app's own custom `Dialog` organism — despite that custom `Dialog` (built on `@reach/dialog`, with its own fade/scale animation, styling, and accessibility handling) existing and being used elsewhere for non-destructive flows (`RequestDialog`, `ContactDialog`, `EditRequestDialog`, `AvatarDialog`).

**This is a real, confirmed historical inconsistency**, not a guess: the codebase clearly has the capability to show a branded, animated, on-theme confirmation modal, and simply doesn't use it for any "are you sure?" prompt — every one of those instead falls back to the browser's own unstyled `confirm()`. This is a strong candidate for `docs/rewrite/open-questions.md` as a **suspected incomplete/inconsistent implementation** (likely just never got around to building a `ConfirmDialog` variant) rather than an intentional design choice — but per the product-fidelity rules, do not silently "upgrade" this to a custom dialog without recording the decision; the historical behavior (native browser confirm, with browser-default copy/buttons/styling that cannot be restyled) is itself observable, testable behavior, and Playwright can drive it via `window.confirm` stubbing exactly as Cypress does (`cy.on('window:confirm', () => true)` in `_settings.spec.js`/`_projects.spec.js`).

## Interaction/hover/focus states — Confirmed

Documented in full in `docs/design/visual-language.md` ("Shape language" / "Animation & motion" sections): consistent ring-based hover/focus treatment across `Button`, `Input`, `Select`, `Switch`; `NavHeader` gets a `shadow` once the page is scrolled; menu items get a `hover:bg-steel-300` background; search hit cards get a `hover:ring-lilac-300` ring.

## Not covered by this pass

- Exact `Toast` visual/timing spec (component internals not opened).
- Form-level "dirty/pristine" indicators beyond the tab-switch confirm-discard check on the project edit page.
- Any skeleton/loading treatment for the project detail page itself beyond what's implied by `loading` being passed into `<Avatar>` (the rest of that page's loading behavior, e.g. while `apollo.useGetProject` resolves client-side after SSR hydration, was not traced in this pass).
