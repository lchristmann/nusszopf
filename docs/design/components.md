# Components

Source: `historical/web-nusszopf/projects/ui-library/stories/{atoms,molecules,organisms,templates}` (a Storybook-organized component library, atomic-design style) plus consumers in `projects/webapp/src/{components,containers}`. This is the **primary evidence source for the target Blade/Livewire component library** — component boundaries and props here should map fairly directly to Blade components/Livewire components in Nusszopf 2, per the "Tailwind is an implementation tool, not the design source of truth" rule.

Depth key: **Read** = source file opened and behavior/markup verified in this pass. **Listed** = file exists and name/props inspected only (via directory listing / imports), full implementation not opened — treat internals as **Unknown** until a follow-up pass reads them.

## Atoms

| Component | Depth | Notes |
|---|---|---|
| `Text` | Read | Typography primitive. `variant` prop maps to CSS classes defined in `Text.theme.js`/`Text.css` — see `docs/design/visual-language.md` for the full scale. `as` prop lets it render any tag (h1–h4, span, p). |
| `Button` | Read (theme/CSS) | `variant` (clean/outline/filled), `size` (base/baseClean/small/large/circle), `color` (stone/steel/lilac/warning/blue/turquoise/yellow/moss). All colored variants render as `rounded-full` pill buttons with a 2px border and a hover/focus ring in the same hue at 25% opacity, `transition-shadow duration-200`. Supports `iconLeft`. |
| `Link` | Read (theme/CSS) | For **external**/absolute URLs (vs. `Route` for internal Next.js routing — see below). `variant` (button/text/svg), `type` (mail/file/url — changes icon/affordance, not fully inspected), `color` (11 named combinations, each a bg+text+border triad, e.g. `lilac` = `bg-lilac-200 text-lilac-800 border-lilac-800`). |
| `Route` | Listed (theme read) | Same variant/color system as `Link` but for internal (`next/link`) navigation. `RouteVariant` = text/button/svg, `RouteBorder` = small/medium/large. |
| `Input` | Read (theme) | `size` (base/large — large has bigger padding + semibold text), `color` (lilac/stone/steel/moss — each just changes the focus/hover ring + border color, all otherwise `border-2 text-current`). |
| `Select` | Read (theme/CSS) | Native `<select>` styled to match Input; `color` (steel/stone/lilac), transparent background, 2px border, hover/focus ring. |
| `Checkbox` | Listed | Custom checkbox atom; consumed with Formik `Field` in forms (e.g. newsletter privacy consent). |
| `Radiobox` | Listed | Custom radio atom; consumed for period-flexible / location-remote toggles in the project form (E2E: `radio_flexible_project-period`, `radio_remote_project-location`). |
| `Switch` | Read (theme/CSS) | Toggle switch; `color` (steel/stone/lilac), on-state = `bg-current`, off-state = `bg-white`, both with a colored ring border. |
| `Skeleton` | Read (usage) | Generic loading placeholder block (`<Skeleton className="h-4 bg-steel-400" />` style usage) — the building block for every skeleton/loading state cataloged in `docs/design/states.md`. |
| `LoadingIndicator` | Read | Full-width fixed top bar (`.rainbow` class) with an animated 6-color repeating gradient that scrolls horizontally on a 2s loop, `z-index: 100`. Shown/hidden via a `hidden` class toggle, not mounted/unmounted, driven by Next.js `Router.events` (`routeChangeStart`/`Complete`/`Error`) with a 350ms delay before showing (see `docs/design/states.md`). |

## Molecules

| Component | Depth | Notes |
|---|---|---|
| `Avatar` | Listed (usage read) | Has at least 3 documented usages/variants: default (nav/lists), `variant="settings"` (profile header, with an edit-avatar affordance), `variant="project"` (project owner display on project detail page). Has its own `Avatar.skeleton.js` for a dedicated loading placeholder. |
| `InfoCard` | Read (usage) | Simple bordered/colored info callout (`bg-gray-200`-style), used for "no requests yet" empty state and for contact/support links on the profile page. |
| `InputGroup` | Listed (usage: `SearchInput`) | Composite input wrapper supporting a left/right "element" slot — used by `SearchInput` to place the clear (×) and search/refresh/loading icon buttons inside the input's right edge. |
| `Progressbar` | Listed (usage: project creation) | Shows step label + numeric/percent progress in the project-creation wizard header. |
| `Toast` | Listed (usage: `Toasts.service`) | Notification/snackbar rendered via a global `ToastsProvider` (`services/Toasts.service.js`) and `useToasts().notify({ type, message })`. Types observed in usage: `loading`, `success`, `error`. This is the app's standard async-feedback mechanism — see `docs/design/states.md`. |

## Organisms

| Component | Depth | Notes |
|---|---|---|
| `NavHeader` | Read | Documented fully in `docs/design/navigation.md`. |
| `Footer` | Read | Documented fully in `docs/design/navigation.md`. |
| `Menu` / `MenuItem` | Read | Built on `reakit`'s `Menu`/`MenuButton`/`useMenuState`; used both by `NavHeader`'s hamburger dropdown and by per-item contextual menus (e.g. `menu_edit-request-card` in the project edit "Requests" tab, offering edit/delete). |
| `Dialog` | Read | Built on `@reach/dialog`. Full-screen sheet on mobile (`w-screen min-h-screen`), centered rounded card on `sm+` (`max-w-xl`, `sm:rounded-lg`). Overlay fades in (`animate-opacityFade`), content scales in on `sm+` (`sm:animate-scaleFade`) — see `docs/design/visual-language.md` for the exact keyframes. Concrete dialog instances: `RequestDialog`, `ContactDialog`, `EditRequestDialog`, `AvatarDialog`. |
| `Popover` | Listed (usage: `FieldTitle`) | Used to attach an info tooltip/popover to form field labels (`FieldTitle` component). |
| `Combobox` | Listed | Built on `@reach/combobox`; likely the location/place-search autocomplete in the project form (`LocationField.js`) — **Inferred**, not directly cross-checked in this pass. |
| `Tab` | Listed | Built on `@reach/tabs`; used by the auth app's login/register tab switch per E2E evidence (`[data-reach-tab-list]` in `_auth.spec.js`), not observed in `webapp` itself. |
| `Cropper` | Listed | Built on `react-easy-crop`; used by `AvatarDialog` for avatar image upload/crop (**Inferred** from naming and co-location; `AvatarDialog` internals not opened). |
| `Swiper` | Listed | Likely backs the (currently disabled) `CarouselSection` on Home — **Inferred**, not confirmed since that section is commented out. |
| `Masonry` | Read (usage) | Pinterest-style column layout (wraps `react-masonry-css`), used for both search results (`HitCard`s) and the user's own projects grid (`EditProjectCard`s), with page-specific `breakpointCols`/`gap` props. |
| `Stepper` / `useStepper` | Read (usage) | Multi-step form controller powering the 4-step project-creation wizard; exposes `step`, `progress`, `goForward`, `currentChild`. |
| `RichTextEditor` / `useRichTextEditor` | Read (usage) | Built on `slate`/`slate-react`/`slate-history`. Used for project **description** and **team** fields (rich text) and to *render* that rich text back out on the project detail page (`serializeJSX`). Has a documented historical bug/workaround (see `docs/design/screens.md`, project-creation section, re: `slate` issue #3476). |

## Templates (page-composition primitives)

| Component | Depth | Notes |
|---|---|---|
| `Frame` | Read | The base horizontal-padding + max-width container (`px-6 sm:px-16 lg:px-24 xl:px-32`, content capped at `sm:max-w-xl` [`size="default"`] or `sm:max-w-2xl` [`size="large"`], or `fluid` for full-width). Nearly every page section is a `<Frame>`. `as` prop lets it render `<header>`/`<nav>`/`<footer>`/etc. for semantic HTML. |
| `FrameFullCenter` | Read | Full-height, vertically+horizontally centered variant of `Frame`, used for error pages. |
| `FramedCard` | Read | Centered narrow card (`max-w-sm`/`sm:max-w-md`) with generous vertical margin — used for the newsletter confirmation screens. |
| `FramedGridCard` | Read | The dominant "app screen" layout: a 12-column CSS grid with a distinct `Header` sub-component and a `Body` sub-component (with `Body.Col` supporting `oneCol` [10/12, centered] or `twoCols` [5/12 each] spans, collapsing to full-width single-column below `lg`). Used by Profile, User Projects, Project Create, Project Edit, Project Detail. This is the single most important layout primitive to reproduce faithfully — it defines the "boxed header + white card body" look of every authenticated screen. |

## Webapp-level components (not part of the shared UI library)

| Component | Depth | Notes |
|---|---|---|
| `Page` | Read | App-shell wrapper: SEO tags, `ErrorBoundary`, conditional `NavHeader`, `Footer`. Documented in `docs/design/navigation.md`. |
| `ErrorBoundary` | Read | Class component catching render errors, rendering `ErrorPage` with no status code. |
| `ErrorPage` | Read | Documented in `docs/design/screens.md` / `docs/design/states.md`. |
| `FieldTitle` | Read | Form field label + optional info `Popover`. |
| `RequestCard` | Read (dispatcher) | A variant-dispatching component with 5 named variants, each a separate file: `PreviewRequestCard`, `ViewRequestCard`, `EditRequestCard`, `HitRequestCard`, `CarouselRequestCard` (the last presumably for the disabled carousel section). Represents a single **request** (Gesuch) attached to a project, rendered differently depending on context (form preview while creating, public view on project detail, owner-edit list, search-hit inline). Individual variant internals not opened in this pass. |
| `HitCard` | Read | Search-result card for one **project**, grouping all of that project's matching **requests** underneath it (via nested `HitRequestCard`s), with Meilisearch `_formatted` (highlighted) fields for title/goal and a truncated (90 char) summary built from description/location/team/motto/author. |
| `SearchInput` | Read | Debounced/throttled (500ms) search box with a combined clear/search/loading/refresh trailing icon button (icon switches between search, spinner, and "refresh needed" depending on state — see `docs/design/states.md`) and an attached `FilterPopover`. |
| `WelcomeCard`, `ProjectsSkeleton`, `NoHitsSection`, `SkeletonHits`, `EditProjectCard` | Read (usage) | Empty/loading-state components — cataloged in `docs/design/states.md`. |
| `VisitorCounter`, `Banner` | Listed | Project-detail-page components (view counter display, owner banner) — named/positioned in `docs/design/screens.md`, internals not opened. |
| `FilterPopover` | Listed | Search filter UI, attached to `SearchInput`. Internals (which fields are filterable) not opened in this pass — cross-reference `docs/search/README.md` for the backend-confirmed filterable fields once that pass exists. |

## Icons

`react-feather` is the primary icon set (imported per-icon: `Search`, `Menu`, `User`, `LogIn`, `PlusCircle`, `ChevronLeft`, `ChevronRight`, `ChevronUp`, `MapPin`, `Calendar`, `Send`, `Share2`, `AlertTriangle`, `X`, `RefreshCw`, `Loader`, `ArrowDownCircle`, `Instagram`, confirmed by direct imports across the files read in this pass). `lucide-react` is also a dependency (`package.json`) but no direct usage was found in the files opened in this pass — **Unknown** whether it's actually used elsewhere or a leftover dependency. A small number of custom SVG icons exist under `webapp/src/assets/icons/` (`Offer.js`, `Request.js`, plus an `index.js` barrel) and `webapp/src/assets/logos/` (Nusszopf wordmark/mark, sponsor logos) — these are bespoke brand assets and must be preserved pixel-for-pixel per the visual-fidelity rule, not redrawn.

## Not covered by this pass

- Full prop APIs and internal markup for every "Listed" component above.
- `auth-login`/`auth-password` component trees (separate Next.js apps — not part of `web-nusszopf/projects/ui-library` consumption inspected here; covered by authentication archaeology).
- `ProjectForm/*` individual field components (`ContactField`, `GoalField`, `LocationField`, `MottoField`, `PeriodField`, `ProjectField`, `TeamField`, `TitleField`, `VisibilityField`) beyond their E2E-confirmed `data-test` hooks noted in `docs/design/screens.md`.
