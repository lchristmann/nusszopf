# Visual Language

Source: `historical/web-nusszopf/tailwind.config.js`, `projects/ui-library/stories/atoms/*/*.{css,theme.js}`, `projects/webapp/package.json`. These are the hard design-fidelity requirements per `CLAUDE.md` / `.claude/rules/02-visual-fidelity.md` — Tailwind is only the implementation mechanism for the values below, not a license to substitute defaults.

## Typography — Confirmed

Font: **Barlow** (`@fontsource/barlow`, weight/style subset `latin.css` imported in `_app.js`), with the system sans-serif stack as fallback (`fontFamily.sans = ['Barlow', ...defaultTheme.fontFamily.sans]`, `tailwind.config.js`). This is the **only** font family configured — no serif/mono families are defined for content use.

Text variants (`Text.theme.js` → `Text.css`, `@layer components`), i.e. the entire type scale used across the product:

| Variant | Classes | Effective size / weight / line-height (Tailwind defaults) |
|---|---|---|
| `titleLg` | `text-4xl font-semibold leading-tight` | 36px / 600 / 1.25 |
| `titleMd` | `text-3xl font-bold leading-tight` | 30px / 700 / 1.25 |
| `titleSm` | `text-xl font-bold leading-snug` | 20px / 700 / 1.375 |
| `titleSmSemi` | `text-xl font-semibold leading-snug` | 20px / 600 / 1.375 |
| `textLgSemi` | `text-2xl font-semibold leading-snug` | 24px / 600 / 1.375 |
| `textLg` | `text-2xl font-medium leading-snug` | 24px / 500 / 1.375 |
| `textLgThin` | `text-2xl font-normal leading-snug` | 24px / 400 / 1.375 |
| `textMd` | `text-xl font-medium leading-snug` | 20px / 500 / 1.375 |
| `textSmMedium` | `text-lg font-medium` | 18px / 500 / default |
| `textSm` | `text-lg` | 18px / 400 / default |
| `textXs` | `text-base` | 16px / 400 / default |

There is **no `textLg`-vs-`h1` distinction beyond these named variants** — every heading/body text in the app uses one of exactly these 11 variants via `<Text as="..." variant="...">`, never raw Tailwind text-size utilities directly on headings. This is a strict, closed type scale and should be reproduced as such (e.g. as Blade component variants or Tailwind `@apply`-based utility classes with the same names), not approximated with ad hoc sizes.

## Color palette — Confirmed (`tailwind.config.js`, full palette override, not `extend`)

The **entire default Tailwind color palette is replaced** (`theme.colors = {...}`, not `theme.extend.colors`) — Nusszopf does not use Tailwind's default gray/blue/red/etc. scales at all. Only `transparent`, `current`, `black` (`#000`), `white` (`#fff`), and the following named, custom-numbered scales exist:

| Name | Shades (hex) | Apparent role |
|---|---|---|
| `warning` | 100 `#FFEEE5`, 200 `#FDD1B9`, 300 `#F8A87C`, 700 `#B84405` | Errors, destructive actions, alerts |
| `steel` | 50 `#F1F6F9`, 100 `#ECEFF1`, 200 `#CFD8DC`, 300 `#AFBEC5`, 400 `#90A4AE`, 500 `#546E7A`, 600 `#455963`, 700 `#37474F`, 800 `#263238` | Neutral/base UI chrome (nav bar, footers, body text, most page backgrounds) |
| `stone` | 200 `#F2F2F2`, 300 `#E0E0E0`, 400 `#C7C7C7`, 600 `#828282`, 800 `#404040` | Secondary neutral (buttons, links) |
| `livid` | 100 `#EEF5F7`, 200 `#CBE1E6`, 300 `#BAD7DE`, 500 `#64A6B4`, 800 `#213E45` | Info callouts (Home hero info box, no-results box) |
| `lilac` | 100 `#F4F2F6`, 150 `#F2F0F4`, 200 `#E5E1E9`, 300 `#CBC3D3`, 400 `#B1A6BC`, 500 `#89799A`, 600 `#645673`, 800 `#403749` | Primary "app" accent — project screens, create/edit flows, search-result cards |
| `moss` | 200 `#EAF0A8`, 300 `#E0E97C`, 400 `#D5E151`, 450 `#C0CA33`, 800 `#3E410B` | Search screen accent |
| `yellow` | 100 `#FFFFF0`, 200 `#FCFDB5`, 250 `#F7F97B`, 300 `#F6F151`, 400 `#EAD706`, 500 `#D6B300`, 800 `#574800` | Home "fellows"/secondary accent |
| `red` | 100 `#FFE9E5`, 200 `#FFCDC6`, 300 `#FCA99C`, 500 `#F5624D`, 600 `#D51407` | Home "contest" accent |
| `turquoise` | 100 `#E5FFF5`, 200 `#C9F7E6`, 300 `#9BE9CB`, 500 `#4DCBAE`, 700 `#005C4C` | Home "about" accent |
| `blue` | 100 `#EAF3FF`, 200 `#D1E3FC`, 300 `#ABCBF8`, 400 `#87B2ED`, 500 `#6395DC`, 700 `#00378A` | Button color option (usage site not identified in this pass) |
| `pink` | 100 `#FFEEF1`, 200 `#FFCCD5`, 300 `#FF99AA`, 500 `#F1507E`, 700 `#C20A5D` | Home "fellows" section background |

Each section/screen owns a consistent color: e.g. the entire search experience is "moss", the entire project create/edit/detail flow is "lilac", error states are "warning", Home's marketing sections cycle through steel→turquoise→red→pink. **This is a deliberate content-area color-coding system, not incidental** — preserve which color belongs to which product area rather than picking new accent colors per screen.

These exact hex values (not Tailwind's stock scales under the same names) are the ground truth. Any Tailwind CSS 4 reimplementation must define a custom theme with these literal values.

## Spacing, sizing, and other token overrides — Confirmed (`tailwind.config.js`, `theme.extend`)

- Extra border width: `border-3` (3px), on top of Tailwind defaults.
- Extra min-height: `min-h-48` / `min-h-48!` (12rem, with a forced-important variant — the trailing `!` in the key is unusual and likely a workaround for a specificity conflict; note but don't necessarily reproduce the `!important` hack itself as "intended design").
- Extra spacing scale steps: `18` (4.75rem), `84` (21rem), `128` (32rem) — used alongside Tailwind's standard spacing scale.
- Extra ring width: `ring-3` (3px).
- Extra shadow: `shadow-lg-dark` = `0 4px 6px -1px rgba(0,0,0,0.3), 0 2px 4px -1px rgba(0,0,0,0.06)` — a darker/stronger variant of Tailwind's default `shadow-lg`, used at least on the search page's floating scroll-to-top button.
- **Breakpoints are Tailwind's untouched defaults** — `theme.screens` is not overridden anywhere in `tailwind.config.js`, so `sm=640px`, `md=768px`, `lg=1024px`, `xl=1280px` (2xl not observed in use). Confirmed by absence of a `screens` key in the config; see `docs/design/responsive-behavior.md` for how these are actually used.
- `corePlugins: {}` — this is an **empty override object, not a disabling one** (Tailwind's `corePlugins` option only disables plugins when given an array or an object with `false` values; an empty object `{}` changes nothing). Note this so it isn't mistaken for "all core plugins were disabled."

## Animation & motion — Confirmed

Two named keyframe animations defined in `tailwind.config.js` (`theme.extend.animation`/`keyframes`), both `200ms`, `cubic-bezier(0.08, 0.82, 0.17, 1)` (an "ease-out-expo"-style curve):

- `opacityFade` — `opacity: 0 → 1`. Used on the Dialog overlay (`animate-opacityFade`, `Dialog.css`).
- `scaleFade` — `transform: scale(0.85) → scale(1)` combined with `opacity: 0 → 1`. Used on the Dialog content, `sm:` and up only (`sm:animate-scaleFade`).

Additional bespoke animation not in `tailwind.config.js` but in component CSS:

- **Route-change loading bar** (`LoadingIndicator.css`, class `.rainbow`): a fixed, full-width, 2px-tall (`h-2`) bar at the very top of the viewport (`z-index: 100`), with a horizontally-repeating 6-stop gradient (`#f4f651 → #fa7061 → #b1eed7 → #ffccd5 → #8ab2f1 → #f4f651`) that loops via a `background-position` keyframe animation, `2s`, `linear`, infinite. Shown only while a Next.js route transition is pending longer than 350ms (see `docs/design/states.md`).
- **Button/Input/Select/Switch hover & focus rings**: consistent pattern across all interactive atoms — `ring-2 ring-transparent` at rest, becoming a colored ring at 25–50% opacity on `hover`/`focus`, transitioning over `duration-200 ease-out`. This ring-based focus treatment (rather than an outline or border-color change) is a recurring, deliberate interaction signature across the whole component library and should be preserved as the standard focus/hover treatment in the rewrite.

No other transform/parallax/scroll-triggered animation was found in the config or the atom CSS files read in this pass. `framer-motion` is a dependency (`package.json`) but no direct usage was found in the files opened in this pass — **Unknown** whether it's used elsewhere (e.g. in `CarouselSection`, disabled on Home) or a leftover dependency; do not assume rich motion exists beyond what's documented here without further evidence.

## Shape language — Confirmed

- Buttons and form-adjacent action links (`Route`/`Link` with `variant="button"`) are consistently **fully rounded** (`rounded-full`), 2px bordered, never filled solid — the "filled"/"outline"/"clean" `ButtonVariant` names exist in the theme file but the actual CSS (`Button.css`) defines only one visual treatment per color (border + ring), so in practice **all colored buttons render as bordered pill buttons**, not as solid-fill buttons, regardless of the nominal `variant` prop — **Confirmed by reading `Button.css`: `outline` and `filled` map to the identical `nz-btn-<color>` class.** This is a notable historical inconsistency between the theme's declared vocabulary (clean/outline/filled) and its actual CSS (only one real look) — flag for `docs/rewrite/open-questions.md`: was "filled" simply never implemented, or is it applied via inline/Tailwind utility classes at each call site instead of the shared CSS class? Not resolved in this pass.
- Cards and dialogs use `rounded-lg`, never sharp corners or heavy rounding beyond that.
- Inputs/selects use a flat `border-2`, no rounding beyond Tailwind's default (effectively square-ish, not pill-shaped) — a deliberate visual distinction from buttons.

## Not covered by this pass

- Exact icon sizes/weights beyond what's cited inline in `docs/design/components.md`/`navigation.md`.
- Any print stylesheet, dark mode, or high-contrast mode — no evidence of any of these three was found in `tailwind.config.js` or the atom CSS files; treat as **Unknown/likely absent** rather than inferring support.
- Favicon/PWA manifest visual assets (referenced in `_document.js` but not opened).
