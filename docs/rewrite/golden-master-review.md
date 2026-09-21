# Golden-Master Review

First-slice verification pass (2026-09-19). This document records the screen/component-by-component
comparison between the historical Nusszopf product (`../historical/web-nusszopf`,
`../historical/be-nusszopf`) and the current Nusszopf 2 implementation, per `CLAUDE.md`'s and
`.claude/rules/02-visual-fidelity.md`'s fidelity requirements. Classifications used throughout:
**Match**, **Intentional scaffolding**, **Historical bug fix**, **Implementation discrepancy**,
**Historical unknown**.

## Behavioral comparison

Scope: registration, login, project-creation form fields, project-detail page, search page, and a
sanity check that redirecting `/` to `/search` doesn't skip anything acceptance-critical. Source of
truth: `../historical/web-nusszopf/projects/{auth-login,webapp}/src/**`.

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Registration password policy (5 rules, order, messages) | `SignUpForm.js:27-33` (Yup chain) | `PasswordPolicy.php` | None — identical rules, identical short-circuit order | Match | None |
| Registration privacy checkbox required | `SignUpForm.js:34` (`mixed().oneOf([true])`) | `LoginRegister.php:94` (`'privacy' => ['accepted']`) | None | Match | None |
| Registration username rules (max 15, no-whitespace, unique) | `SignUpForm.js` Yup chain, single-error-at-a-time display | `LoginRegister::register()` | Multiple simultaneous error messages possible (no `bail`) | Implementation discrepancy (cosmetic) | **Fixed** — added `'bail'` to the username rule array |
| Login (username-or-email field, redirect target, no historical rate limiting) | `LoginForm.js` | `LoginRegister::login()` | None (the added 5-attempts/60s rate limiter has no historical equivalent — Auth0-platform-side, already an adopted Category B default) | Match | None |
| Project title/goal max length (40/150) | `TitleField.js:7`, `GoalField.js:7` | `ProjectForm::rules()` | None | Match | None |
| Project description max length (6000) | `ProjectField.js:12` — measured on `JSON.stringify()` of the serialized rich-text document (has structural overhead), not plain text | `ProjectForm::rules()` — measured on raw text | Current cap is strictly more generous than history's effective plain-text budget | Implementation discrepancy (low severity) | **Not fixed** — the plain textarea is already documented temporary scaffolding pending the real rich-text editor (`docs/rewrite/architecture-decisions.md`); revisit when that editor lands, not before |
| Project-detail owner banner (both visibility variants, verbatim copy, dismiss, edit link) | `Banner.js`, `banner.data.js` | `project-detail.blade.php` (previously: private-only, paraphrased, no dismiss/edit-link) | Real, moderate-severity gap — public-owner variant missing entirely | Implementation discrepancy | **Fixed** — see "Frame / FramedGridCard / FramedCard" section below for the full rewrite (owner-only banner above the card, both variants, verbatim copy, dismiss control, edit link) |
| Search query trigger (explicit submit vs. live-as-you-type) | `SearchInput.js` — `handleChange` only updates local state; a query fires only on Enter/blur or the search-icon click, throttled 500ms against resubmission | `Search.php`/`search.blade.php` previously used `wire:model.live.debounce.500ms` — queries on every keystroke | Real, moderate-severity interaction-model difference | Implementation discrepancy | **Fixed** — converted to `wire:submit="search"` with a deferred `wire:model`, matching explicit-submit; `docs/design/screen-specs.md`'s "debounced 500ms" claim corrected to describe the real mechanism; `tests/E2E/pages/SearchPage.ts` updated from a fixed `waitForTimeout` to pressing Enter |
| Search empty-query "browse everything" | `search.service.js:60-61` | `Search::render()` | None | Match | None |
| Search masonry breakpoints (1/2/3 cols at <640/640–1023/≥1024) | `search.js:33` | `search.blade.php` | None | Match | None |
| Home (`/`) → `/search` redirect | `pages/index.js` — large CMS/marketing page, no functional overlap with first-slice acceptance criteria | `Route::redirect('/', '/search')` | None found — nothing acceptance-critical is skipped | No discrepancy | None |

## Visual comparison

Scope: colors, typography, Button/Input/Checkbox atoms, NavHeader, Footer, Frame/FramedGridCard,
Toast, and the Login/Register, Search, My Projects, Project detail screens. Source of truth for the
palette/type scale: `../historical/web-nusszopf/tailwind.config.js` (the real root config the
`ui-library`'s own `tailwind.config.js` re-exports) and `Text.css`/`Button.css`/`Tab.css`.

### Colors and typography

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Full color palette | `web-nusszopf/tailwind.config.js` (`theme.colors`, 11 hue groups, every hex value) | `resources/css/app.css` `@theme` block | None — every hex value diffed byte-for-byte, all match (case aside) | Match | None |
| Font family fallback stack | `fontFamily.sans: ['Barlow', ...defaultTheme.fontFamily.sans]` (full Tailwind default sans stack) | `--font-sans: 'Barlow', ui-sans-serif, system-ui, sans-serif, ...emoji fallbacks` (shortened fallback chain) | Fewer intermediate system-font fallbacks; invisible in practice since Barlow always loads via `@fontsource/barlow` | Match (trivial) | None |
| 11-item type scale | `Text.css` — `.nz-title-lg` … `.nz-text-xs`, exact Tailwind utility mapping per class | `app.css` `@layer components` block | None — all 11 variant names and their exact `text-*`/`font-*`/`leading-*` utilities match 1:1 | Match | None |
| Spacing/shadow/animation tokens | `tailwind.config.js` `extend.spacing/boxShadow/animation/keyframes` | `app.css` `@theme` | None — `18/84/128` spacing, `shadow-lg-dark`, `opacityFade`/`scaleFade` keyframes all match | Match | None |

### Button / Input / Checkbox

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Button color/ring treatment | `Button.css` `.nz-btn-*` (border-2, rounded-full, ring-2 ring-transparent, `hover/focus:ring-X/25`) | `app.css` `.nz-btn-*` | None (Tailwind 4's `ring-X/25` shorthand is the modernized equivalent of v3's `ring-opacity-25`) | Match | None |
| `filled` variant | `ButtonVariant.filled` declared but maps to the identical class as `outline` everywhere (BUG-014, already resolved) | `button.blade.php` implements only `outline`; no fake "filled" look anywhere | None | Historical bug fix (already documented) | None |
| Button `circle` size | `ButtonSize.circle = 'text-lg p-2'` (rounding comes from the color class, not the size class) | `$sizeClass` for `circle` includes a redundant `rounded-full` alongside the color class's own `rounded-full` | Cosmetically inert duplicate class, no visual difference | Match | None |
| Input border radius | `Input.atom.js` base classes include `rounded-md` | `input.blade.php` used `rounded-none` | **Real, visible discrepancy** — inputs/textareas had square corners instead of the design system's rounded ones | Implementation discrepancy | **Fixed** — `resources/views/components/input.blade.php` now uses `rounded-md`, plus the other missing base classes below |
| Input placeholder color | `placeholder-current` (placeholder text matches the input's text color, not Tailwind's default gray) | Missing entirely | Placeholders rendered in the wrong (default gray) color | Implementation discrepancy | **Fixed** — added `placeholder-current` |
| Input focus/disabled polish | `appearance-none`, `focus:outline-none focus:placeholder-transparent`, `disabled:opacity-50 disabled:pointer-events-none` | Missing | Disabled inputs had no dimmed/non-interactive treatment; placeholder didn't hide on focus | Implementation discrepancy | **Fixed** — all four classes added to `input.blade.php`'s shared base class string |
| Checkbox visual | `Checkbox.atom.js` — a visually-hidden native input plus a Feather `Square`/`CheckSquare` icon swap; **no bordered box, the check mark is the icon itself** | `checkbox.blade.php` — a native `<input type="checkbox">` styled with `border-2 border-steel-700 rounded-none` | Structurally different visual language (icon-drawn glyph vs. a bordered native control); would need an icon-asset strategy (Feather `Square`/`CheckSquare` SVGs) to close, not a class tweak | Implementation discrepancy | **Not fixed** — needs an icon-asset decision, see "Follow-up needed" below |

### NavHeader

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Structure/background/sticky | `NavHeader.organism.js` — `bg-steel-400 text-steel-800`, `sticky top-0`, `h-10 lg:h-12`, hamburger-triggered dropdown, no separate desktop link row | `nav-header.blade.php` | None on structure/background/height/sticky/single-hamburger-menu-at-every-breakpoint | Match | None |
| Top-bar action buttons | Icon-only (Feather `Search`, a custom `Nuss` logo-mark icon for "my projects"), no text labels in the always-visible row | Text labels ("Suche", "Meine Projekte") instead of icons; hamburger rendered as a Unicode `☰` character instead of Feather's `Menu` glyph; "go back" rendered as a Unicode `←` instead of `ChevronLeft` | **Real, visible discrepancy** — the always-visible bar looks completely different (a text-driven nav vs. a minimal icon bar) | Implementation discrepancy | **Not fixed** — needs an icon-asset strategy (see below); flagging only |
| Dropdown menu items | Suche, (create project, if `mode==='internal'` — always true in-app), Meine Projekte / Einloggen-Registrieren, Ausloggen (warning-colored) | Same item set, same conditional auth/guest branching | None in content/order; profile/"Settings" item correctly absent (no profile screen exists yet this slice) | Match / Intentional scaffolding | None |
| Scroll shadow | `hasScrolled` adds a `shadow` class once the page is scrolled | Not implemented | Minor, JS-only micro-interaction | Implementation discrepancy (low priority) | Not fixed — flagging only |

### Footer

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Variant applicability | `Footer.organism.js` has `vercel`/`auth0`/`classy` variants; none of this slice's screens (Search, Project detail, My Projects, Login) map to a directly-evidenced footer variant in the archaeology read so far | `footer.blade.php` — a bare `© {year} Nusszopf` line, explicitly self-documented in-code as deliberate minimal scaffolding (legal routes largely don't exist yet) | Legitimate, already-documented gap, not a silent invention | Intentional scaffolding | None — already correctly self-documented; revisit once `/legalNotice`/`/legalPolicy` routes exist |

### Frame / FramedGridCard / FramedCard

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| `Frame` breakpoints/sizes | `Frame.template.js` — `px-6 sm:px-16 lg:px-24 xl:px-32`, `default`→`sm:max-w-xl`, `large`→`sm:max-w-2xl` | `frame.blade.php` | None on the padding breakpoints; Nusszopf 2 cleanly picks one size class instead of reproducing history's redundant simultaneous `sm:max-w-xl` + size-class double-application (a CSS-cascade-order-dependent historical artifact, not a deliberate design) | Match (simplification of an ambiguous historical artifact) | None |
| `FramedGridCard.Header`/`.Body`/`.Body.Col` grid classes | `FramedGridCard.template.js` — `grid grid-cols-12 gap-2 py-6 lg:py-8` (header), `grid grid-cols-12 py-12 md:py-16` + gap-2/4/6 (body), `col-span-12 lg:col-span-5` (twoCols) / `col-span-12 lg:col-span-10 lg:col-start-2` (oneCol) | `framed-grid-card/{header,body,body-col}.blade.php` | None — every grid/gap/span class matches exactly | Match | None |
| **My Projects header color** | `pages/user/projects.js`: `headerColor="bg-steel-200 lg:bg-steel-100"` | `my-projects.blade.php` used `bg-lilac-300` | **Real, visible discrepancy** — wrong header color entirely | Implementation discrepancy | **Fixed** — changed to `bg-steel-200` |
| **My Projects body gap** | `<FramedGridCard.Body gap="medium">` | `<x-framed-grid-card.body>` (defaulted to `small`) | Wrong grid gap (`gap-2` instead of `gap-4`) | Implementation discrepancy | **Fixed** — `gap="medium"` |
| **Project detail body gap** | `pages/projects/[id].js`: `<FramedGridCard.Body gap="medium">` | `project-detail.blade.php` used `gap="large"` | Wrong grid gap (`gap-6` instead of `gap-4`) | Implementation discrepancy | **Fixed** — `gap="medium"` |
| Project detail header color | `headerColor`/`<FramedGridCard.Header className="bg-lilac-300">` | `bg-lilac-300` | None | Match | None |
| `FramedCard` (Login/Register wrapper) | `FramedCard.template.js` — a `Frame fluid` wrapper (`mt-12 mb-12 sm:mt-16`) around a `flex flex-col items-center w-full max-w-sm sm:max-w-md mx-auto rounded-lg sm:px-12 sm:py-16` card | `login-register.blade.php` previously used a flat, non-responsive `max-w-md mx-auto ... p-8` div, no `flex flex-col items-center`, no `fluid` outer Frame | **Real discrepancy** — wrong mobile width (`max-w-md` instead of `max-w-sm` below `sm`), wrong/flat padding, missing centered-column layout (relevant once a logo image is added) | Implementation discrepancy | **Fixed** — rewrote the wrapper to match `FramedCard`'s exact classes |
| Nusszopf logo on the login/register card | `SVGNusszopfLogoBig`, always rendered above the tab switcher | Not rendered at all (NavHeader itself only has a text wordmark, no logo asset exists anywhere in this codebase yet) | Missing brand asset | Implementation discrepancy | **Not fixed** — needs a logo SVG asset decision, see below |

### Toast (SR-009, `docs/rewrite/specification-review.md` — previously "Must clarify", now resolved)

| Area | Historical reference | Implementation (before this pass) | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Position | `Toasts.service.js`: `fixed right-0 z-50 w-full p-3 top-10 lg:top-12 sm:w-auto` — **top-right**, anchored just under the NavHeader | `toast-container.blade.php` used `fixed bottom-0 right-0 m-6` — **bottom-right** (a guess, per SR-009) | **Real, now-resolved discrepancy** — toasts appeared in the wrong corner of the screen | Implementation discrepancy | **Fixed** — container repositioned to `right-0 w-full p-3 top-10 lg:top-12 sm:w-auto` |
| Auto-dismiss timing | `AUTO_CLOSE_MS = 3000`, applied unconditionally to **every** toast type including `loading` | `resources/js/app.js` used `5000ms`, and explicitly skipped auto-dismiss for `loading` toasts | Wrong timing, and an invented type-based exception with no historical basis | Implementation discrepancy | **Fixed** — `app.js` now always auto-dismisses at `3000ms`, matching history exactly (preserve-unless-demonstrably-broken; the first slice doesn't dispatch any `loading` toasts yet, so this had zero current observable effect but would have been wrong the moment one is added) |
| Card markup (`bg-livid-300`, `textSm`, dismiss-on-click, per-type icon + close `×`) | `Toast.molecule.js` | `toast-container.blade.php` + `app.js`'s `nzToast()` | None — SR-009's guess at the card markup itself was actually correct | Match | None |
| Stacking (`opacity-50` on all but the most recent) and entrance animation (`framer-motion`, opacity/y/scale) | `Toasts.service.js` | Not implemented — new toasts just append, no dimming of older ones, no animation | A "materially visible animation" per `CLAUDE.md`'s visual-fidelity rules, technically in scope | Implementation discrepancy | **Not fixed** — needs a small CSS-transition implementation, flagged for follow-up (low complexity, but out of this pass's remaining budget) |

### Search screen structure

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Header frame size/color | `pages/search.js`: `<Frame size="large" className="... bg-moss-300 text-moss-800">` plus an inner `max-w-3xl mx-auto` constraint | `search.blade.php` used a default-size (`sm:max-w-xl`) frame, no `text-moss-800`, no inner `max-w-3xl` wrapper | Narrower max-width and missing text color | Implementation discrepancy | **Not fixed in this pass** — see note below |
| Results area width | `<Frame size="large" className="flex-1 h-full my-8 break-all">` — width-capped, **not** full-bleed | `search.blade.php` used `<x-frame fluid>` — full viewport width, no cap at all | **Real, visible discrepancy** — on wide screens the results grid spans the whole page instead of being centered/capped, changing card proportions | Implementation discrepancy | **Not fixed in this pass** — requires touching the Search Livewire view's layout beyond a one-line class swap; flagged for the parent/next pass to apply `size="large"` instead of `fluid` |
| Floating scroll-to-top button | A `circle`-sized `Button` with a `ChevronUp` icon, `fixed bottom-0 right-0 m-6 shadow-lg-dark bg-steel-300`, documented in `docs/design/screen-specs.md` as required | Entirely absent | Missing documented component | Implementation discrepancy | **Not fixed** — needs an icon asset (`ChevronUp`) and a small scroll listener |
| Password-reveal toggle on Login | `LoginForm.js` uses an `InputGroup` with an `Eye`/`EyeOff` toggle button | Plain `type="password"` input, no toggle | Missing documented interaction | Implementation discrepancy | **Not fixed** — needs an icon asset |

### Follow-up needed (not fixed in this pass — require more than a CSS/class tweak)

1. **Icon-asset strategy.** Multiple gaps above (NavHeader's icon-only top bar, Checkbox's icon-drawn glyph, Search's scroll-to-top `ChevronUp`, Login's `Eye`/`EyeOff` password toggle, the Nusszopf logo mark) all trace back to one open question: this codebase has no icon/logo asset pipeline at all yet (no inline SVG partials, no icon font, no bundled Feather-equivalent). Historically every one of these uses `react-feather` (MIT-licensed, simple outline SVGs) plus one custom `Nuss`/logo mark. Recommend: add a small set of inline SVG Blade partials for exactly the icons this slice's screens need (`search`, `menu`, `chevron-left`, `chevron-up`, `log-in`, `plus-circle`, `square`/`check-square`, `eye`/`eye-off`) rather than pulling in an icon font or JS dependency — this is a design/asset decision, not a one-line fix, so it was not done silently in this pass.
2. **Search screen width/color** (`size="large"`, `text-moss-800`, results-area max-width) — a one-line class change per element, but touching the Search Livewire view was left to the parent/coordinating pass rather than done here to avoid overlapping a concurrently-running behavioral-comparison pass also touching Search-adjacent files.
3. **Toast stacking/animation** — low complexity, deferred for budget reasons, not a fidelity blocker for the first slice's acceptance criteria (no test currently asserts animation).

## Files changed in this pass

- `resources/views/components/input.blade.php` — `rounded-md`, `placeholder-current`, `appearance-none`, `focus:placeholder-transparent`, `disabled:opacity-50 disabled:pointer-events-none`.
- `resources/views/components/toast-container.blade.php` — repositioned top-right (`top-10 lg:top-12`), not bottom-right.
- `resources/js/app.js` — auto-dismiss corrected to `3000ms` for every toast type, matching history.
- `resources/views/livewire/auth/login-register.blade.php` — rewrote the outer card to `FramedCard`'s exact classes; replaced the underline tab bar with the historical sliding-pill `Tab` design; removed the `color="lilac"` overrides on every button/input.
- **Correction made after this pass's own fix, during the coordinating verification pass**: simply omitting `color` here did not actually produce `steel` — the Blade `button.blade.php`/`input.blade.php` atoms' own hardcoded default was `lilac`, not `steel`, so login/register would have silently rendered lilac (the *opposite* of the intended fix) until this was caught. Fixed at the component level instead: `resources/views/components/button.blade.php` and `resources/views/components/input.blade.php`'s default `color` prop changed from `'lilac'` to `'steel'`, matching `Button.atom.js`/`Input.atom.js`'s own confirmed defaults (`color = 'steel'`). Verified safe: every other call site in the codebase already passes `color` explicitly, so this only changes behavior where no `color` prop is given — currently just the login/register screen.
- `resources/views/livewire/projects/my-projects.blade.php` — header color `bg-lilac-300` → `bg-steel-200`; body gap `small` (default) → `medium`.
- `resources/views/livewire/projects/project-detail.blade.php` — body gap `large` → `medium` (applied on top of a concurrent pass's Banner-component addition to this same file).

No `app/`, `routes/`, or `tests/Feature/**` files were touched by this visual-comparison pass.

## Additional files changed by the coordinating verification pass (this session)

Beyond the visual-comparison pass's own edits and the fixes recorded in the "Behavioral comparison"
table above:

- `app/Livewire/Projects/ProjectForm.php`, `tests/Feature/Projects/ProjectFormTest.php` — BUG-021 fix
  (404 instead of 403 for a non-owner's edit access; see `docs/rewrite/bugs.md`).
- `app/Models/Project.php`, `app/Policies/ProjectPolicy.php`, `tests/Feature/Projects/ProjectPolicyTest.php`
  — `ProjectPolicy::view()` now delegates to `Project::scopeVisible()` (with an explicit viewer id)
  instead of re-stating the same rule independently, closing first-slice acceptance criterion #8's
  "single enforcement point" requirement and a Gate-resolved-user-vs-session-user correctness gap.
- `app/Livewire/Search/Search.php`, `tests/Feature/Search/MeilisearchIntegrationTest.php` — added the
  query-time `->query(fn ($q) => $q->visible())` defense-in-depth scope on the search path
  (`docs/search/README.md` already called for this) plus a regression test proving it actually
  filters a force-indexed private project out of results.
- `resources/views/components/button.blade.php`, `resources/views/components/input.blade.php` — default
  `color` corrected from `lilac` to `steel`, matching `Button.atom.js`/`Input.atom.js`'s own confirmed
  defaults; this was needed to make the visual-comparison pass's login/register fix (removing the
  `color="lilac"` override) actually produce `steel`, not silently fall back to a wrong `lilac` default.
- `resources/views/livewire/search/search.blade.php`, `app/Livewire/Search/Search.php` — applied the
  visual-comparison pass's flagged-but-deferred "Search screen width/color" fix (`size="large"`,
  `text-moss-800`, capped results width instead of `fluid`).

---

# Second-slice verification pass (2026-09-19)

Scope: the project creation wizard, the project edit screen, the rich-text editor, the project detail
content those fields drive, and the shared chrome/controls those screens use. Source of truth:
`../historical/web-nusszopf/projects/{webapp,ui-library}/src/**` (read in full for the files listed in
`docs/rewrite/second-slice.md`). Method: every historical component's class string and structure was
compared with the Blade equivalent, then the rendered result was inspected in Chromium at 1280 px and
375 px (wizard steps 1, 3 and 4, project detail, edit "Beschreibung" and "Einstellungen"). *No
historical screenshots or a runnable historical app are available*, so "Match" below means "the
historical structure, classes, copy and states are reproduced", not a pixel diff — the same standard
the first-slice review used.

## Behavioral comparison

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| Step count, order, labels | `create.js`, `CreateProjectSteps/*`, `create-project.data.js` | `ProjectWizard` | None | Match | None |
| Fields per step and column | `DescriptionStep1/2`, `SettingsStep` | `project-wizard.blade.php` | None | Match | None |
| Forward gating / backward free | Formik `validationSchema` per step | `ProjectWizard::next()/back()` | None (the mechanism differs from what `first-slice.md` said — corrected) | Match | Doc corrected |
| `?step=N`, entry at step 0, invalid values | `useStepper.js` | `mount()` redirect + `updatedStep()` | None | Match | None |
| No draft | `create.js` | properties only | None | Match | None |
| Error display timing | Formik touched + errors | `blurred()` + failed `next()` | Validation runs on blur (Livewire round trip) rather than on every keystroke after touch | Match (equivalent display) | None |
| Final defensive re-validation + toast | `handleSubmit` | `ProjectWizard::create()` | None | Match | None |
| Creation payload | `serializeProject*` | `descriptionAttributes()`/`settingsAttributes()` | Plain-text projection separates blocks by a space (history joined list items with commas) | Match (search text only) | None |
| Title/goal 40/150, motto 200, team/description 6000, all messages | `*Field.js`, `project-form.data.js` | `ManagesProjectFields::fieldRules()` | Whitespace-only title/goal rejected | Historical bug fix | BUG-026 |
| Period rules and copy | `PeriodField.js` | `PeriodDateRule` | Ordering test not applied while flexible; strict 2/4-digit years | Historical bug fix / Historical unknown | BUG-022; year edge recorded in `second-slice.md` |
| Period storage and display | `parseDateISOString`, `[id].js` | `ProjectDate` | Display no longer time-zone dependent | Historical bug fix | BUG-023 |
| Location shape, provider, params, UX | `LocationField.js`, `location.service.js`, `Combobox` | `LocationSearch`, `project-form/location` | Provider call moved server-side (key private) | Match (Replace: infrastructure) | Documented |
| Rich-text toolbar, marks, lists, link prompt | `RichTextEditor.organism.js` | TipTap config | List-button aria-labels corrected; package replaced | Historical bug fix / Replace | BUG-024 |
| Rich-text rendering | `serializeJSX` | `RichText::toHtml` | None (https forcing, classes reproduced) | Match | None |
| Edit: views, per-view save, dirty/confirm, delete | `edit.js`, `EditProjectViews/*` | `ProjectEdit` | None | Match | None |
| Edit: owner-only, 404 | BUG-021 | `ProjectEdit::mount()` | (already a fix) | Historical bug fix | BUG-021 |
| Detail: header, body sections, requests column, avatar, banner | `[id].js`, `Banner.js`, `Avatar` | `project-detail.blade.php` | See scaffolding table | Match / Intentional scaffolding | None |
| Copy typos | `project-form.data.js`, `edit-projects-views.data.js` | — | "Peronen", "gepeichert" corrected | Historical bug fix | BUG-025 |
| Requests step/view, request cards, request dialog | `RequestsStep.js`, `RequestsView.js` | inert button + empty state (second slice) → the full feature (third slice, `third-slice.md`) | — | Match (was intentional scaffolding) | Done |
| ContactDialog (contact via Nusszopf) | `ContactDialog.js` | `mailto:mail@nusszopf.org` | Out of scope (server e-mail) | Intentional scaffolding | E-mail slice |
| VisitorCounter, "Projekt melden" | `[id].js` | absent | Out of scope | Intentional scaffolding | Later slice |

## Visual comparison

| Area | Historical reference | Implementation | Discrepancy | Classification | Action |
|---|---|---|---|---|---|
| `FramedGridCard` header/body coloring (`bg-lilac-300 lg:bg-steel-100`, `bg-white lg:bg-steel-100`, `lg:mb-20 lg:mt-12`) | `FramedGridCard.template.js`, `create.js` | wizard/edit/detail markup; `Page` props on the layout | The first slice put the header/body colors on the outer `Frame` at every breakpoint | Implementation discrepancy | **Fixed** for the three Slice 2 screens (My Projects/Search keep their first-slice markup, already reviewed) |
| `FramedGridCard.Header`/`Body` (`rounded-t-lg`/`rounded-b-lg`, className on both header layers) | same | `framed-grid-card/{header,body}` | Missing rounding classes | Implementation discrepancy | **Fixed** |
| `Page` `main` (`flex flex-col flex-1` + className), footer color | `Page.js` | `layout.blade.php` (`mainClass`, `footerBg`) | Missing | Implementation discrepancy | **Fixed** |
| NavHeader icon-only top bar, logo mark, chevron, menu icons | `NavHeader.organism.js`, `Nuss.icon.js`, `NusszopfHeaderLogo.icon.js` | `nav-header.blade.php` + `<x-icon>` | Was text labels / Unicode glyphs / text wordmark | Implementation discrepancy | **Fixed** (first-slice follow-up #1) |
| Checkbox glyph (`Square`/`CheckSquare`) | `Checkbox.atom.js` | `checkbox.blade.php` | Was a bordered native box | Implementation discrepancy | **Fixed** (first-slice follow-up; used by the registration screen) |
| Radiobox | `Radiobox.atom.js` | `radiobox.blade.php` | None | Match | None |
| Progressbar | `Progressbar.molecule.js` | `progressbar.blade.php` | None | Match | None |
| FieldTitle + Popover | `FieldTitle.js`, `Popover.organism.js` | `field-title.blade.php` | Was an always-visible caption (an unread-component simplification) | Implementation discrepancy | **Fixed** |
| Input / Goal & Motto textareas, Select, InfoCard | `Input.atom.js`, `Select.*`, `InfoCard.molecule.js` | `input`, `select`, `info-card` | None | Match | None |
| Button: disabled dimming/cursor, `outline-none`, `iconLeft` | `Button.atom.js` | `button.blade.php` | Missing | Implementation discrepancy | **Fixed** |
| Rich-text editor chrome (border/ring, toolbar row, button spacing, min height, placeholder) | `RichTextEditor.organism.js` | `rich-text-editor.blade.php`, `app.css` | TipTap's DOM instead of Slate's; styled to the same classes | Match | None |
| Combobox (search/X icon, popover, hover/selected option) | `Combobox.organism.js` | `project-form/location.blade.php` | None | Match | None |
| Project detail header (title, goal, MapPin/Calendar rows, Kontaktieren/Teilen `small` buttons, responsive stacking) | `[id].js` | `project-detail.blade.php` | None | Match | None |
| Project detail body (section titles `textLg`, `text-lg` rich text, requests column, `row-start-1` mobile ordering) | `[id].js` | same | None | Match | None |
| Author block | `Avatar.molecule.js` (`project`) | initial-on-grey circle | External image service dropped | Intentional scaffolding | Avatar slice |
| Banner close icon | `Banner.js` (`X`, aria "Information ausblenden") | `<x-icon name="x">` | Was a Unicode ×, aria "Banner schließen" | Implementation discrepancy | **Fixed** |
| Toast stacking (`opacity-50` on older toasts) and enter animation | `Toasts.service.js` | `app.js`, `app.css` | Was missing (first-slice follow-up #3) | Implementation discrepancy | **Fixed** |
| Toast "loading" then result | `Toasts.service.js` | `nzToast` | None — nothing replaces a toast historically; all close after 3 s | Match | Doc corrected (`states.md`) |
| Footer (sponsor badges) | `Footer.organism.js` | one-line footer | Documented minimal scaffolding | Intentional scaffolding | Unchanged |
| Scroll shadow on the sticky nav | `NavHeader.organism.js` | absent | Low-priority micro-interaction | Implementation discrepancy | Not fixed — not on a Slice 2 screen's critical path |
| Search scroll-to-top button, login password reveal | first-slice review | absent | Not Slice 2 screens | Implementation discrepancy | Not fixed — outside this slice |

## Follow-ups closed from the first-slice review

Icon-asset strategy (#1) — implemented as `<x-icon>` + `resources/icons/`; checkbox glyph; logo mark;
NavHeader icons; toast stacking/animation (#3). Still open: the search page's scroll-to-top button and
the login screen's password reveal (both need only `chevron-up`/`eye` icons now that the pipeline
exists, but belong to those screens' slices), the sticky-nav scroll shadow.

