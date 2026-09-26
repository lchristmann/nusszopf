# Fifth Vertical Slice — My Projects and project lifecycle completion

Status: **implemented and verified** (2026-09-22). This slice completed the "My Projects" grid begun in
the first and second slices: the per-card menu (view/edit/toggle-visibility/delete), the `WelcomeCard`
empty state, the `ProjectsSkeleton` loading state, and closed BUG-001 by adding server-controlled view
counting (`ProjectAnalytics`, the `VisitorCounter` digits) and the "Projekt melden" report link on the
project detail screen. It is the specification of what was built and why; scope and sequencing come
from `docs/rewrite/master-roadmap.md`, "Slice 5".

**Out of scope (unchanged)**: the contact dialog and mail (slice 6), the author avatar image and the
`Avatar` header on this screen (Avatar slice — second-slice.md's existing scaffolding row already
covers every screen using the initial-on-grey stand-in, not just the detail page's author avatar),
`ProjectAnalytics.contactRequests` (BUG-017, resolved dead, not reproduced).

## Historical mechanics (Confirmed against `web-nusszopf`, read in full)

| Item | Evidence | Behavior |
|---|---|---|
| Card | `EditProjectCard.js` | Eye/EyeOff icon + title (no text visibility label), goal cut at 90, one `PreviewRequestCard` per request; the whole card body is a button that opens **edit**, not view |
| Card menu | `EditProjectCard.js`, `edit-project-card.data.js` | `MoreHorizontal` menu, 4 items in order: Ansehen, Bearbeiten, Verbergen/Veröffentlichen, Löschen |
| Visibility toggle | `pages/user/projects.js` `handleVisibility`, `projects.service.js` `updateProject` | `lodash.throttle(fn, 1000)` — **no options object**, so lodash's defaults apply: `{ leading: true, trailing: true }`. A first call fires immediately; a second call inside the 1000 ms window is not dropped — it is queued and re-fires with its own arguments once the window elapses (verified 2026-09-22 against lodash's documented default; corrects this slice's original, inaccurate "leading-edge only, dropped" characterization). Calls the **same shared `updateProject`** the edit screen's settings save uses, so it carries the same loading/success/error toasts, not a distinct notification of its own |
| Delete | `projects.service.js` `deleteProject` | native `confirm()` ("Möchtest Du das Projekt wirklich löschen?"), BUG-013 pattern |
| Empty state | `WelcomeCard.js`, `projects.data.js` `welcome` | title/description/greeting + a `Nuss` mark; **no button** of its own — the header's own create CTA is the only one |
| Loading state | `ProjectsSkeleton.js` | two-column skeleton (single column below `lg`), distinct from Search's three-column `SkeletonHits` |
| Create CTA | `pages/user/projects.js` | renders **twice**: `hidden lg:block` in the header, and a separate full-width `lg:hidden` button in the body |
| Masonry | `Masonry.organism.js` defaults (`pages/user/projects.js` passes no override) | `{ default: 2, 1023: 1 }` — 2 columns from `lg`, 1 below; gap 16 px (`gap = { wrap: '-ml-4', col: 'pl-4', row: 'mb-4' }`) — narrower than Search's 3/2/1, gap-20 |
| Visitor counter | `VisitorCounter.js` | 4 zero-padded digit boxes; `null`/`undefined` → `0000`; `> 9999` → `+999` then `9` (5 boxes) |
| View counting | `pages/projects/[id].js` `updateViews` effect | client `localStorage['nusszopf_viewed_projects']` array dedupe, excludes the owner; a plain client-writable GraphQL mutation (BUG-001) |
| Report link | `project.data.js` `report` | `mailto:mail@nusszopf.org?subject=Projekt melden` with `(ID: {id})` appended to the built href, unencoded |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | `ProjectAnalytics` counters become server-controlled only | **Fix** (BUG-001, implemented) | `intentional-changes.md`; no `ProjectAnalyticsPolicy` exists because there is no user-facing write ability to authorize |
| 2 | View-counter dedupe: a cookie, not `localStorage` | **Replace** (register B-4) | the increment is now server-side (`ProjectDetail::recordView()`), so there is no client script left to read `localStorage`; same "not spoof-proof" property as historically |
| 3 | Visibility-toggle throttle becomes a hard server-side drop (`RateLimiter`, 1/second, leading-edge only), not lodash's actual leading**+trailing** default | **Replace** | `lodash.throttle(fn, 1000)` with no options queues a trailing re-fire (a second click inside the window flips visibility again, ~1s later, unprompted) — indistinguishable from "nobody passed `{ trailing: false }`," not a considered product behavior. Reproducing it needs a delayed, cancellable job racing a concurrent edit/delete; the fourth slice made the same call for its 500 ms search-input throttle (decision 6) |
| 4 | `contactRequests` not reproduced | **Preserve absence** (BUG-017, resolved dead) | no confirmed frontend call site ever wrote it |
| 5 | The page opens on the skeleton and then loads (`wire:init="load"`) | **Replace** | same pattern as the fourth slice's Search screen — a full-page Livewire component renders synchronously, so this reproduces the historical client-side-fetch loading state |
| 6 | ~~Masonry is CSS columns (2/1), not `react-masonry-css`~~ — closed in P-2: `nzMasonry` deals the cards left to right, CSS columns remain the no-JavaScript fallback | **Deviation (visual)** → fixed, same class as Search's decision 7 | breakpoints, gap and card look match Search's own `gap-*` + `break-inside-avoid(-column) mb-*` pattern (`search.blade.php`) — the historical *flexbox* Masonry's gap object (`wrap: '-ml-4', col: 'pl-4'`) does not translate to `columns-*` at all; an initial, literal copy of that pairing overflowed the left column 16px past the frame and was corrected during the closure verification pass (2026-09-22), no product/visual regression shipped beyond this slice's own working tree. Column-fill order (top-to-bottom vs. dealt-out) still differs — parity audit (P-1/P-2) |
| 7 | Native `confirm()` for card-menu delete | **Preserve** (BUG-013) | same pattern already adopted for request deletion and project deletion from the settings view |
| 8 | Report link built by literal string concatenation, unencoded | **Preserve** | matches the historical `${cms.report.href} (ID: ${id})`; the appended value is always a UUID, so there is no injection risk despite the missing encoding |

## Implementation

- **Schema** `database/migrations/2026_09_22_090000_create_project_analytics_table.php`: `project_analytics`
  (`project_id` PK/FK, `views`, CHECK `<= 1,000,000`); `App\Models\ProjectAnalytics` (guarded like any other
  model — the actual fix is the *absence* of a client-facing write path, not the `$fillable` list);
  `Project::analytics()` (`hasOne`).
- **View counting** `App\Livewire\Projects\ProjectDetail::recordView()`: skips the owner, dedupes via the
  `nz_viewed_projects` cookie, `firstOrCreate()->increment('views')`. `render()` passes `views` and the
  report `mailto:` link; the Blade view renders the four digit boxes and the report link (`project-detail.blade.php`).
- **Grid** `App\Livewire\Projects\MyProjects`: `ready`/`load()` (skeleton pattern), `toggleVisibility()`
  (owner-scoped lookup, `Gate::authorize('update', ...)`, `RateLimiter` 1/second, the shared "Änderungen
  speichern..." / "Projekt wurde aktualisiert." / "Sorry, die Änderungen konnten nicht gespeichert werden."
  toasts), `deleteProject()` (owner-scoped, `Gate::authorize('delete', ...)`, success/error toast).
  `my-projects.blade.php`: the dual create-CTA (a plain
  `hidden lg:block` wrapper div, since the button component's own unconditional `inline-flex` would otherwise
  beat a `hidden` class of equal specificity on source order), the skeleton, the literal `WelcomeCard` copy, and
  the `EditProjectCard`-equivalent grid with its `x-menu`.
- **Shared**: a `preview` variant added to `x-request-card` (`PreviewRequestCard.js` parity, used only in
  `EditProjectCard`'s nested request list); three new icons (`eye`, `eye-off`, `alert-triangle`, Feather's canonical
  paths, same format as the existing icon set).
- **Test-id renames**: `MyProjects`' create CTA and the grid card's own click-through now use the historical
  `route_create-project_projects-page` / `route_edit-project_projects-page` test ids (previously
  `btn_create-project_user-projects` / `link_edit-project`, second-slice scaffolding); existing E2E specs and
  `MyProjectsPage.ts` updated to match, plus `MyProjectsPage.openProject()` for the "Ansehen" menu item now that
  the card's own click-through goes to **edit**, not view, matching `EditProjectCard.js`.

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Projects/MyProjectsTest.php` | skeleton → grid, own-projects-only scoping, empty state, request preview, toggle (both directions), the 1/second throttle, search re-sync on toggle, delete (success + authorization), denies a stranger's toggle/delete |
| Feature | `tests/Feature/Projects/ProjectAnalyticsTest.php` | first-visit creates the row at 1, further visits increment, cookie dedupe (same browser, different project), owner never counted, an authenticated stranger is counted, no route exposes a client-writable counter, cascade delete |
| Feature | `tests/Feature/Projects/ProjectDetailContentTest.php` | visitor-counter digit rendering (zero-padded, `+9999` cap), the report `mailto:` link |
| E2E | `tests/E2E/specs/user/my-projects.spec.ts` | publish/hide from the grid (with a real 404 ⇄ 200 check), delete from the grid (native `confirm()`) |
| E2E | `tests/E2E/specs/visitor/project-detail.spec.ts` | the counter increments once per guest browser, never for the owner |

## Remaining gaps

- The `User Projects` header's `Avatar` (title text stands in for it currently) — folded into the existing
  Avatar-slice scaffolding row (second-slice.md), not a new gap opened by this slice.
- Masonry column-fill order (decision 6), to be judged in the parity audit alongside Search's equivalent.
- The operator mailbox (`Project::NUSSZOPF_CONTACT`) is still the hardcoded historical literal, not yet
  instance configuration — that conversion is scoped to the mail/legal slices (A-4/A-5), not this one.
