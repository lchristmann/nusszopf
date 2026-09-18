# Responsive Behavior

Source: `tailwind.config.js` (breakpoints), page/component files across `webapp/src/pages/**` and `ui-library/stories/**` (responsive class usage), `e2e/cypress.json` (test viewport). Breakpoints are Tailwind's **unmodified defaults** — confirmed by the absence of a `screens` key in `tailwind.config.js` — so all `sm:`/`md:`/`lg:`/`xl:` prefixes below mean 640px / 768px / 1024px / 1280px respectively.

The historical E2E suite runs at a fixed **1440×800** viewport (`cypress.json`: `viewportWidth: 1440, viewportHeight: 800`) — i.e. desktop-only automated coverage; no evidence of mobile-viewport E2E runs was found. **Confirmed** gap: mobile-specific behavior in this product was verified manually or not at all, not by the historical E2E suite.

## Breakpoint usage patterns — Confirmed

- **`lg` (1024px) is the dominant structural breakpoint**, not `md`. The core `FramedGridCard` layout (used by every authenticated app screen) collapses from a 2-column body (`lg:col-span-5` per column) to a single full-width column below `lg`. The `NavHeader` height changes at `lg` (`h-10` → `lg:h-12`, 40px → 48px). The Home hero switches from a stacked column layout to a side-by-side row at `lg` (`flex-col lg:flex-row`).
- **`sm` (640px) governs "mobile sheet vs. desktop card"** for the `Dialog` organism (full-screen below `sm`, centered rounded card at `sm`+) and general content max-widths (`Frame`'s `sm:max-w-xl`/`sm:max-w-2xl`).
- **`md` (768px)** is used comparatively rarely and mostly for the `Footer`'s row/column switch (`flex-col md:flex-row`) and some vertical spacing adjustments (`py-6 md:pt-12`).
- **`xl` (1280px)** is used for a further widening of section padding/spacing on Home (e.g. `xl:pt-32 xl:pb-32`) rather than any structural/layout change — i.e. `xl` is fine-tuning, not a new layout tier.

## Specific responsive behaviors — Confirmed

| Element | Behavior |
|---|---|
| `NavHeader` | Same structure (logo, search icon, conditional "my projects" icon, hamburger) at every breakpoint — **no separate mobile vs. desktop navigation pattern**, only icon-size and bar-height changes (logo 25px→30px, bar 40px→48px at `lg`). |
| Search results grid (`Masonry`) | Explicit per-breakpoint column count passed directly in `search.js`: **1 column ≤639px, 2 columns 640–1023px, 3 columns ≥1024px** (`breakpointCols={{ default: 3, 1023: 2, 639: 1 }}`) — note these are **max-width** breakpoints as consumed by `react-masonry-css`, i.e. inverted from Tailwind's min-width convention; don't assume they align 1:1 with `sm`/`lg`. |
| User's own projects grid (`Masonry`) | No explicit `breakpointCols` override passed in `user/projects.js` — falls back to the component's own default (Unknown exact default without reading `Masonry.organism.js`; likely similar 1/2/3 pattern — **Inferred**, not confirmed). |
| `FramedGridCard.Body.Col` | `variant="twoCols"` → `col-span-12 lg:col-span-5` (full width until `lg`, then roughly-half). `variant="oneCol"` → `col-span-12 lg:col-span-10 lg:col-start-2` (full width until `lg`, then a centered 10/12-wide column). |
| Project-create/edit "create project" CTA on the projects list | **Duplicated markup, not just hidden/shown via CSS alone**: a full-width, larger (`size="large"`) button variant is rendered specifically for `<lg` (inside a `text-center lg:hidden` wrapper) in addition to the smaller button in the header row that's `hidden lg:block`. This is two distinct DOM elements swapped by breakpoint, not one element being resized — worth replicating exactly since the mobile CTA is visually more prominent (full-width, larger size), not just a smaller version of the desktop one. |
| Home hero info card | `<ol>` numbered list forces `lg:w-4/5` and centers itself only at `lg` (`flex lg:justify-center`) — a deliberate width constraint change, not fluid at all sizes. |
| Home 3-column feature sections (About / Fellows options) | Stack to full-width single column below `lg`; at `lg`+ become 3 columns with asymmetric padding per column (first column gets right-padding only, middle gets both sides, last gets left-padding only) to create even gutters. |
| Dialog | Full-viewport height sheet, top-padded (`pt-10`), no rounding, no cap on width below `sm`; becomes a centered `max-w-xl` rounded card with vertical margin (`sm:my-12`) at `sm`+. Scale-in entrance animation (`scaleFade`) is **only applied at `sm`+** — on mobile the dialog only fades (`animate-opacityFade` on the overlay), it doesn't scale in. |
| `SkeletonHits` | Three skeleton columns exist in the markup at all times, but the 2nd is `hidden` below `sm` and the 3rd is `hidden` below `lg` — matching the real masonry's column-count breakpoints so the loading state's shape matches the eventual content's shape at every breakpoint. |
| Profile page | Two-column `FramedGridCard.Body.Col` layout (newsletter/sponsoring vs. delete-account/support) stacks to one column below `lg`; text alignment also flips (`text-center sm:text-left` in one subsection) between mobile and `sm`+. |

## Not covered by this pass

- Exact responsive behavior of `ui-library` organisms not opened in depth (`Combobox`, `Cropper`, `Tab`, `Swiper`, `Menu` positioning on small screens beyond what `reakit`'s `placement: 'bottom-end'` implies).
- Any responsive behavior specific to `auth-login`/`auth-password` (separate apps, out of scope for this pass).
- Real device/touch-specific behavior (this pass only inspected Tailwind breakpoint class usage in source, not runtime/device testing — the historical product itself was not run in a browser during this archaeology).
