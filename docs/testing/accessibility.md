# Accessibility

The bar is decision A-7 (`docs/rewrite/decisions-register.md`). The historical product had no accessibility
target, so this is new. A release blocks unless all of the following hold:

- an axe scan of every reachable screen and state shows zero critical or serious violations (colour contrast
  excluded);
- the product is fully operable by keyboard: dialogs and popovers trap focus, close on Escape and restore
  focus;
- accessible names are correct and German, and every error message is tied to its field;
- the page is `lang="de"` and has sensible headings;
- keyboard focus shows a visible `:focus-visible` indicator.

Contrast failures inherited from the historical palette are measured and documented. Each proposed fix is a
bug entry that the maintainer approves or waives, and the palette changes only with that approval (done:
BUG-042–045).

## The automated checks

| Check | Where | Runs |
|---|---|---|
| axe on 45 screens and states; fails on any critical/serious violation, and on any contrast failure except the two documented exceptions (disabled inputs' labels, dimmed older toasts) | `tests/E2E/specs/a11y/axe.spec.ts` | Chromium, in the E2E job |
| "Label in Name" (WCAG 2.5.3): every `aria-label` contains its element's visible text (axe's own rule is experimental) | same spec | Chromium |
| Keyboard: trap, Escape and focus return for every dialog and popover; the menu pattern; the request editor's Escape; the eye toggle; the focus outline; error association | `tests/E2E/specs/a11y/keyboard.spec.ts` | Chromium, Firefox, WebKit |
| `aria-invalid`/`aria-describedby` rendered on the server | `tests/Feature/Views/FieldErrorTest.php` | Pest |

The axe spec writes each state's full result, contrast included, to `test-results/a11y/<state>.json`.

The 45 states:
- **Public:** Home; search with results, the filter open, and no hits; the project page as visitor, with the
  request dialog, and with the contact dialog before and after a failed submit; the nav menu; the three
  legal pages; the 404 page.
- **Newsletter:** unsubscribe by e-mail, before and after a failed submit; both confirmation pages.
- **Authentication:** login and register, each before and after a failed submit; forgot password and set new
  password, each before and after a failed submit.
- **Signed-in:** My Projects, empty and filled, with a card menu open; the signed-in nav menu.
- **Wizard:** all four steps; step 1's errors; the info popover; the place suggestions; the request editor
  with errors.
- **Editing:** the owner's project page; the edit screen's three views; the request editor.
- **Profile:** the page itself, the avatar dialog, and the newsletter consent error.

## How it is implemented

- **Focus indicator:** `resources/css/app.css` (end of file). It applies to links, buttons, summaries,
  selects, menu items and tabs, and only on `:focus-visible`. Text fields keep their historical focus ring;
  checkboxes and radios outline their glyph.
- **Focus handling:** Alpine's `x-trap` on every dialog and popover. `nzDialogFocus`/`nzFocusInto`
  (`resources/js/app.js`) move focus in explicitly and give it back, because WebKit sometimes activates the
  trap before the element is laid out.
- **Error association:** `App\Support\FieldError`.
  - `x-input-error for="field"` gives the message the id `error-<field>`.
  - `x-input`, `x-password-field`, `x-checkbox`, `x-radiobox` and `x-select` add `aria-invalid` and
    `aria-describedby` while `$errors` has their field (their `name`, their `wire:model`, or `error-for`).
  - The rich-text editor follows the message in the DOM.

## Colour contrast (measured and decided 2026-09-23)

| Where | Before | Decision | Now |
|---|---|---|---|
| Validation messages in the contact dialog | warning-700 on lilac-200, 4.20:1 | BUG-042, fixed | `warning-750`, 4.6:1 |
| Home newsletter button | steel-700 on blue-400, 4.42:1 | BUG-043, fixed | `steel-800`, 6.0:1 |
| "Ausloggen" in the nav menu | warning-700 on steel-400, 2.10:1 | BUG-044, fixed | `warning-900`, 4.6:1 |
| Older toasts (dimmed to 50 %) | ≈2.1:1 | BUG-045, waived (historical, transient) | unchanged |
| Labels of the disabled period inputs ("Von", "Bis", the place hint) | 50 % opacity, 2.72:1 | exempt: WCAG 1.4.3 excludes inactive components | unchanged |

The maintainer approved the three fixes, each keeping the historical hue. The two new warning tones are theme tokens
in `resources/css/app.css`. Since then the axe spec gates colour contrast: anything but the two exceptions above fails.
Everything else measured passes, for example body text on every background, the popovers (livid-800 on
livid-300, 7.54:1) and messages on white (4.85:1).

## Screen-reader review

The accessibility tree of the main journey screens was reviewed as a screen reader would announce it: landmarks
(`navigation`, `main`, `contentinfo`), one `h1` per page, `h2` section titles, German names, and dialogs announced
by name. That review found, and this pass fixed:

- the section titles, which were paragraphs;
- the visitor counter's loose digits;
- the location link, whose name hid the place;
- the identical menu names;
- the label mismatches.

**No real screen-reader listening pass has been performed.** This review of the accessibility tree does not
replace one. The maintainer closed P-3 without it (2026-09-24). A listening pass with NVDA or VoiceOver through
Journeys 2, 3, 5 and 7 is a post-1.0 manual verification opportunity, not a release blocker
(`docs/release/parity/P-03-accessibility.md`).
