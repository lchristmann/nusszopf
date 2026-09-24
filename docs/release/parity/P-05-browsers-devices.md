# P-5 Browser/device verification (2026-09-24)

Exit evidence (`master-roadmap.md` §4): a manual matrix on iOS Safari and Android Chrome for the journeys, with the
findings fixed. The bar is decision A-7 (`docs/rewrite/decisions-register.md`): the latest two stable Chrome/Edge,
Firefox and Safari on desktop, plus current iOS Safari and Android Chrome. The CI gate is Chromium, Firefox and
WebKit, and a person runs a manual smoke test on a real iPhone and a real Android phone for each release candidate.

Scope: the gap between the desktop CI engines and phones and tablets, meaning viewport, touch, the mobile user
agent, and browser support. The visual comparison (P-2), accessibility (P-3) and performance (P-6) are out of scope.

## Summary

- **No real device was available** (no Android device on `adb`, no iOS tooling on this Linux host). Everything
  below was measured with Playwright's device emulation: real WebKit and Chromium engines with the device's viewport,
  scale factor, touch events and mobile user agent. The real-device pass is still open (see the checklist below).
- Two defects were found, and both are fixed: **DEV-01**, where menus stayed open after a tap on blank page in
  Safari, and **DEV-02**, a viewport meta tag that drifted from the historical one.
- The whole journey suite now also runs on an emulated iPhone and Android phone in CI, together with a touch spec
  for the interaction points.

## Browser support: historical and current

**Historical (Confirmed by absence):** `web-nusszopf` (at `b915940`) has no `browserslist` in any `package.json`
and no `.browserslistrc`. Its targets were therefore the defaults of its tools:

- Next.js 10.2's Babel preset;
- the `defaults` query for `autoprefixer` and `postcss-preset-env` (`postcss.config.js`);
- Tailwind CSS 2.1, which does not support IE 11.

The exact browser list that resulted is **Inferred**, not recorded anywhere. The only explicit compatibility code is
`smoothscroll-polyfill` (`pages/_app.js`), which gave Safari before 15.4 a smooth `scrollTo`. The viewport was
`width=device-width,minimum-scale=1,initial-scale=1` (`pages/_app.js`). The Cypress suite ran only at 1440×800 in one
browser (`docs/journeys/README.md`).

**Nusszopf 2 (Confirmed):**

- Vite 8's default build target is baseline "widely available": Chrome/Edge 111, Firefox 114 and Safari 16.4.
- Tailwind CSS 4.3 needs Safari 16.4, Chrome 111 and Firefox 128. The built CSS uses `@property` 55 times and
  `color-mix()` 58 times.
- Both targets fall inside A-7's bar (the latest two stable versions, current iOS and Android), so nothing needs
  a polyfill or a lower target.
- The search page's scroll-to-top button uses native `scrollTo({ behavior: 'smooth' })`, supported since Safari 15.4,
  which makes the historical polyfill obsolete (a Replace in effect, no behaviour change).
- The viewport meta tag differed; that is DEV-02 below.

## Device matrix (emulated)

| Playwright project | Device profile | Engine | Viewport | Runs |
|---|---|---|---|---|
| `mobile-safari` | iPhone SE (3rd gen): the narrowest current iPhone | WebKit | 375×667, touch, mobile | every journey spec + touch spec |
| `mobile-chrome` | Galaxy S24: a current, narrow Android phone | Chromium | 360×780, touch, mobile | every journey spec + touch spec |
| `tablet-safari` | iPad Mini | WebKit | 768×1024, touch, mobile | touch spec |
| (exploratory, not kept) | iPhone 15, Pixel 7 | WebKit, Chromium | 393, 412 | whole suite once |

The keyboard, axe, ARIA and CSP specs stay desktop-only (`playwright.config.ts`, `DESKTOP_ONLY`). They do not depend
on the device, and the keyboard spec assumes a physical keyboard.

`tests/E2E/specs/devices/touch.spec.ts` drives the interaction points by touch (`tap()` sends real touch events;
the pinch and drag go through the Chrome DevTools Protocol):

- the nav menu, the search filter and the card menus open on a tap, work, and close on a tap on blank page;
- the wizard, the request editor, the edit screen's view `<select>`, and the contact dialog;
- the avatar cropper zooms on a pinch and moves on a one-finger drag, like react-easy-crop did (Chromium only,
  because Playwright cannot send multi-touch to WebKit);
- no screen or open state scrolls sideways: every public screen, My Projects (empty and filled), the four wizard
  steps, the three edit views, the project page, Profile, and every opened dialog, menu and popover;
- every open menu, popover and dialog fits within the screen width.

## Findings

Both findings are defects of Nusszopf 2, not historical behaviour. DEV-01 and DEV-02 are this page's identifiers, as
P-4 used SEC-xx. No historical defect was found, so `docs/rewrite/bugs.md` has no new entry.

| ID | Finding | Severity | Evidence | State |
|---|---|---|---|---|
| DEV-01 | iOS Safari: the nav menu, the card menus and the search filter do not close on a tap on blank page | Medium | Confirmed in emulated WebKit; Inferred on real iPhones | Fixed |
| DEV-02 | The viewport meta tag drops the historical `minimum-scale=1` | Low | Confirmed | Fixed |

### DEV-01 — Menus stay open after a tap outside them on iOS (Medium)

- **Found:** in the touch spec, the card menu on My Projects stayed open after a tap on the page beside it, in
  WebKit on both the iPhone and the iPad profiles. Chromium closed it.
- **Cause (Confirmed by an event trace):** WebKit on iOS synthesises `mousedown` and `click` from a tap only when the
  tapped element or one of its ancestors listens for clicks. On blank page, WebKit sent `pointerdown`, `touchstart`
  and `touchend`, and no `click`. Chromium sent all five. Alpine's `x-on:click.outside` listens on the document, so
  it never saw the tap. This affects every `click.outside` in the app: the nav menu (`nav-header.blade.php`), the
  card and request menus (`menu.blade.php`), and the search filter popover.
- **Historical behaviour:** the historical menus and the filter popover are Reakit `Menu`/`Popover`
  (`Menu.organism.js`, `NavHeader.organism.js`, `FilterPopover.js`), whose default `hideOnClickOutside` closes them on
  an outside click (Confirmed). React 17 attaches its click listener to the app's root container, which gives every
  element in the page a listening ancestor, so iOS delivered those clicks. That they closed on real iPhones is
  **Inferred**: nobody tested the historical app on a phone.
- **Fix:** `resources/js/app.js` adds an empty click listener on `<body>`, which does what React's root listener did.
  After the fix, WebKit sends `mousedown` and `click` for the same tap. Nothing else changes: taps on controls already
  produced clicks, and a click on blank page does nothing but reach the outside handlers.
- **Regression test:** `touch.spec.ts`: the nav menu, the filter and the card menu must close on a tap on a spot the
  spec picks because nothing interactive is under it. With the listener removed, the card-menu test fails on
  `mobile-safari` and `tablet-safari`, and the nav/filter test fails on `tablet-safari`. With it, all pass.
- **Needs a real iPhone:** emulated WebKit reproduces the rule, but the fix counts as proven only once a real iPhone
  closes a menu on a tap beside it (checklist item 3).

### DEV-02 — Viewport meta tag differs from the historical one (Low)

- **Found:** the layout sent `width=device-width, initial-scale=1`. The historical `pages/_app.js` sends
  `width=device-width,minimum-scale=1,initial-scale=1`.
- **Effect:** without `minimum-scale=1`, a phone can zoom out below 100 % when anything is wider than the screen. The
  sweep found nothing wider than the screen today, so nobody sees the difference. It is still a drift from the
  historical page head.
- **Fix:** `resources/views/components/layout.blade.php` carries the historical value verbatim.
- **Regression test:** `tests/Feature/Seo/SeoTagsTest.php`.

### Not defects

- **The journey spec clicked a button that phones hide.** My Projects renders "Projekt starten" twice, `hidden lg:block`
  in the header and `lg:hidden` in the body, exactly like `pages/user/projects.js`. `project-journey.spec.ts` took the
  first one, which only exists at desktop width because the Cypress suite ran at 1440 px. The spec now takes the
  visible one. The app was correct.
- **Chrome drops a tap that comes within milliseconds of a synthetic gesture.** After the pinch and drag, a tap on
  "Speichern" sent `touchstart` and `touchend` but no `click` in about 1 run out of 3. Traced as follows:
  - nothing called `preventDefault()`;
  - cropperjs had no gesture in progress;
  - the dialog did not scroll, and `touch-action` was `none`;
  - with a 1 s pause before the tap, 6 of 6 runs passed; with the spec's final 500 ms pause, every run since has
    passed (6 of 6).

  The spec waits 500 ms, and the reason is written next to the wait. No finger can lift and tap again that fast.
- **Checked and in order:**
  - no sideways scrolling at 360, 375 and 768 px on any screen or state;
  - every text field, textarea, `<select>` and the rich-text editor is 18 px, like the historical `text-lg` inputs,
    so iOS never zooms in when a field gets focus;
  - dialogs are full-screen sheets on phones and a centred card from `sm` upward (`docs/design/responsive-behavior.md`);
  - the filter popover and the menus stay inside the screen;
  - the period fields are `dd.mm.yyyy` text inputs, as historically, not native date pickers;
  - the avatar input accepts `image/png, image/jpeg`, as historically;
  - the masonry deals its cards into one column on phones;
  - the scroll-to-top button works;
  - the native `confirm()` dialogs appear when deleting.

## Real-device pass: not performed, deferred to P-16

A-7 requires a manual smoke on a real iPhone and a real Android phone for each release candidate. No device was
available for this phase. On 2026-09-24 the maintainer **deferred** this pass to P-16 (release candidate testing). It
is a deferral, not a waiver: P-16 must run the checklist below on real devices before release. Emulation cannot show:

- the on-screen keyboard and autocorrect in the rich-text editor;
- a real pinch on iOS in the cropper;
- iOS converting a HEIC photo to JPEG for the `image/png, image/jpeg` input;
- the native share sheet;
- the page scrolling under an open dialog on iOS;
- native pickers for `<select>`;
- `mailto:` opening the mail app;
- the collapsing browser toolbars.

**To reach the dev stack from a phone:** the phone and the dev machine must be on the same network. Open
`http://<machine's LAN IP>:8080`; pages and assets follow the address you open. For the mailed links (password reset,
newsletter), set `APP_URL=http://<LAN IP>:8080` in `.env` first and restart `php-fpm` and `queue-worker`.
Mailpit is at `http://<LAN IP>:8025`.

**Checklist** (iOS Safari and Android Chrome; tick each on both):

1. Home → the search call to action: open the filter, tick a category, search. Only matching projects remain, and
   the cards stack in one column.
2. Register (the privacy checkbox can be ticked with a tap), log out, log in.
3. **DEV-01:** open the nav menu, then tap blank page beside it; it closes. The same for a card menu on My Projects
   and for the search filter.
4. Create a project with the wizard: type into the rich-text description with the on-screen keyboard, use bold and
   a list, pick a place from the location suggestions, add a request in the request editor, create the project.
5. Edit the project: switch views with the view `<select>` (native picker), save, delete a request (native confirm).
6. Project page: "Teilen" opens the share sheet; "Kontaktieren" opens the contact dialog (or the mail app for a personal contact); while a dialog is open, the page
   behind it does not scroll.
7. Profile: change the avatar with a photo from the camera roll (on iOS a HEIC photo), pinch and drag in the
   cropper, save.
8. Delete the account.

Record the device, OS version and browser version for each tick here when the pass is done.

## Verification (2026-09-24, local dev stack, `E2E_MAILPIT_URL` set)

| Run | Result |
|---|---|
| Playwright, desktop projects (`chromium`, `firefox`, `webkit`) | 165 passed, 0 failed, 12 skipped |
| Playwright, device projects (`mobile-safari`, `mobile-chrome`, `tablet-safari`) | 104 passed, 0 failed, 6 skipped |
| `touch.spec.ts` ×5 on the three device projects, 6 workers | 50 passed, 0 failed, 10 skipped |
| DEV-01 check: `touch.spec.ts` with the fix removed | card menu fails on `mobile-safari` and `tablet-safari`; nav/filter fails on `tablet-safari` |
| `composer test` (Pest) | 602 passed |
| Visual regression (`playwright.visual.config.ts`, after `tests/Visual/reseed.sh`) | 69 passed: pixel-identical, so neither fix changes what is drawn |
| `composer lint:check` | passed |

The skips are by design:

- the two search specs gated on `SEARCH_PAGE_SIZE=5` and the reindex command, on each project that runs them
  (CI sets both);
- the axe scan on Firefox and WebKit;
- the avatar gesture test on the WebKit projects.

**Newsletter budget.** The historical per-IP limit for the newsletter forms is 10 requests per 15 minutes. In one local
run of all six projects, the desktop projects use 9 of it, so the newsletter specs then fail on the phone projects
(observed; they pass after `php artisan cache:clear`). CI is not affected, because every project is its own job with
its own stack. Locally, run the desktop and the device projects separately and clear the cache between them
(`docs/testing/README.md`).

## Status

**Done.** Closed by the maintainer on 2026-09-24 on the automated and emulated evidence above. The emulated matrix runs in
CI on every push, and both findings are fixed with regression tests.

**Limitation carried forward:** no real device was tested in P-5. The real-iPhone and real-Android pass (the eight-step
checklist above, including the DEV-01 confirmation in item 3) is **deferred to P-16**. P-16 is responsible for running it
on each release candidate before release, and for recording the device, OS and browser versions here.
