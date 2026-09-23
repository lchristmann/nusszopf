# Visual regression

Nusszopf 2 must look like the historical Nusszopf (`CLAUDE.md`, visual fidelity). This suite compares
every screen at three widths against the **running historical app**, not against memory or a
description. It has two parts:

- **Baselines** (`tests/Visual/baselines/`): screenshots of Nusszopf 2, reviewed against the
  historical app. CI compares every run with them pixel for pixel. A difference fails the build.
- **Reference captures** (`tests/Visual/reference/`): the same screens taken from the historical
  webapp. Use them to review a baseline before committing it.

Tooling follows register decision B9: Playwright's built-in `toHaveScreenshot`, Chromium only. The
screenshots are taken inside the pinned `mcr.microsoft.com/playwright` image (`compose.dev.yaml`'s
`playwright` service), so fonts and anti-aliasing are identical locally and in CI.

## What is captured

`tests/Visual/screens.ts` lists 23 screens, each captured at 375×812 (phone), 768×1024 (tablet) and
1440×900 (desktop), full page:

- Home, search (results and no hits);
- project detail as visitor, as owner and with a personal contact;
- the request dialog and the contact dialog;
- My Projects, the create wizard, and edit (Beschreibung, Gesuche, Einstellungen);
- Profile, the three legal pages, newsletter unsubscribe and the 404 page;
- login, register, forgot password and set new password.

The visitor counter is masked, because every visit raises it in both apps.

Both apps show the same fixed dataset, `tests/Visual/reference-data.json`: one user, three projects
(public with requests, public with a personal contact, private) and fixed dates.

## Running it

The development stack must be up (`docker compose -f compose.dev.yaml up -d`).

```sh
# 1. Reset the database to the reference dataset. This destroys everything else in the dev database.
tests/Visual/reseed.sh

# 2. Compare Nusszopf 2 with the baselines.
docker compose -f compose.dev.yaml exec -T playwright npx playwright test -c playwright.visual.config.ts

# After an intended visual change: rewrite every baseline, then review it (below) before committing.
docker compose -f compose.dev.yaml exec -T playwright npx playwright test -c playwright.visual.config.ts --update-snapshots=all
```

Always pass `--update-snapshots=all`. Plain `--update-snapshots` only rewrites the screenshots that fail
the comparison, so small changes would stay stale.

The suite needs its own dataset, so it is separate from the E2E suite. `playwright-report-visual/`
shows the diff of a failing comparison.

## Reviewing against the historical app

`tests/Visual/historical-harness/run.sh` starts the historical app next to the dev stack. It:

1. copies `../historical/web-nusszopf` and `be-nusszopf` into `.historical-harness/` (gitignored), so
   the historical checkouts stay untouched;
2. installs their locked dependencies with Node 12;
3. builds the webapp and the two Auth0-hosted apps (`auth-login`, `auth-password`);
4. starts them with the historical Hasura 1.3.3 (which applies the historical migrations), PostgreSQL 12
   and Meilisearch 0.19;
5. seeds the same reference dataset (`seed.mjs`), including the search index documents, built exactly
   like `search.function.js` builds them.

Two changes are made to the historical code, only inside that copy:

- `auth0.stub.js` replaces the Auth0 SDK with a fake session: the cookie `nzfake=<user id>` signs in,
  and a Hasura JWT is minted with the harness key.
- The Next.js build target becomes `server`, so `next start` can serve it.

The login and password apps need no change: without Auth0's query parameters they render their forms
and never contact Auth0.

```sh
tests/Visual/historical-harness/run.sh                  # about 2 minutes the first time
docker compose -f compose.dev.yaml exec -T -e VISUAL_TARGET=historical playwright \
    npx playwright test -c playwright.visual.config.ts   # writes tests/Visual/reference/
python3 tests/Visual/sbs.py /tmp/nz-review               # historical | Nusszopf 2, side by side
python3 tests/Visual/metrics.py                          # per screen: share of differing pixels, height difference
tests/Visual/historical-harness/run.sh down
```

The reference captures are reproducible: two runs of the harness produce identical files.

## Accepted differences

Every remaining difference was reviewed. Each one is either one of the following documented decisions
or sub-pixel text rendering (under 2.3 % of the pixels on every screen except the legal pages).

| Difference | Why | Decision |
|---|---|---|
| No "Powered by Vercel", Auth0 or "JWT Auth" badges in the footer; the band keeps its 81px height | The app is not hosted on Vercel, and Auth0 is gone | `docs/rewrite/tenth-slice.md` decision 5; seventh slice |
| The no-picture avatar's initial is drawn in Barlow, not by ui-avatars.com | The external image service is dropped | `intentional-changes.md`, "Author avatar fallback no longer calls ui-avatars.com" |
| The legal pages show the operator's Markdown, here the labelled examples | Decision A-4; the historical pages had CMS arrays | `tenth-slice.md` decision 6 |
| Login has no "Oder einloggen mit" row unless Google is configured, and never a disabled Apple button | Google is optional (B-6); Apple was never functional | BUG-012; `seventh-slice.md` |
| Forgot password and set new password are routes of their own, in the same card | One app instead of three | `seventh-slice.md` decision 1 |

## Found and fixed by the first review (finish-line phase P-2, 2026-09-23)

- `Text.atom` defaults to `textMd` and hyphenates non-heading text, and the ui-library reset dims
  placeholders and makes `em` semibold. None of this was ported.
- `Button` has no display class of its own, but ours forced `inline-flex`, which overrode `block`.
- Search and My Projects lacked their `Page` classes (white search footer). The search results frame had
  stopped being a flex item of `<main>`.
- Masonry dealt cards top-to-bottom (CSS columns) instead of left to right (`nzMasonry`). My Projects'
  gap is 20px.
- My Projects showed an invented "Meine Projekte" title instead of the owner's `Avatar`. Profile had an
  extra frame and the wrong card colours.
- The fallback avatar was 4px too small; the report link lacked its button padding; the footer band was
  6px short.
- The auth screens lacked the `FramedCard` with the big logo, the `bg-steel-100` submit buttons and the
  page colours. They answered with field errors where the historical apps show toasts.

See `docs/release/parity/P-02-visual-regression.md` for the evidence.
