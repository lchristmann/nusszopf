# P-2 Visual regression vs. historical (2026-09-23)

Exit evidence (`master-roadmap.md` §4): screenshot baselines for each screen × {phone, tablet, desktop},
with the diffs reviewed by a human. §7.3.11: every diff is fixed or recorded as an accepted,
documented difference.

## Reference source (the decision the roadmap asked for early)

The running historical app itself. The roadmap called running it "Unknown feasible", because it needs
Auth0 and Hasura. It is feasible: `tests/Visual/historical-harness/run.sh` builds the historical webapp
and both Auth0-hosted apps from `../historical/` and starts them with the historical Hasura 1.3.3
schema and Meilisearch 0.19. A cookie-driven fake session stands in for Auth0.

Storybook was not used. It shows components only, not screens.

## Evidence

- **Suite:** 23 screens × 3 widths = 69 baselines (`tests/Visual/baselines/`), compared with zero
  tolerance.
  - Two consecutive runs on a freshly seeded database produced 0 differing pixels.
  - The CI job `visual` runs the comparison.
- **Reference:** 69 historical captures of the same screens and dataset (`tests/Visual/reference/`).
  Two harness runs produced byte-identical files.
- **Distance after the fixes:** `python3 tests/Visual/metrics.py` gives the share of pixels differing by
  more than 40 grey levels.

  | Screens | Differing pixels | Height |
  |---|---|---|
  | Home at all 3 widths | ≤ 0.18 % | identical |
  | Every app screen | ≤ 2.3 % (anti-aliasing, the avatar glyph, the missing badges) | identical, except My Projects +1 px (sub-pixel rounding) and Register desktop −11 px (no badge row) |
  | Legal pages | 7–18 % | differ: other content by design (A-4) |

## Review

Claude compared every pair side by side (`tests/Visual/sbs.py`) and zoomed into each region that
differed.

Fixed (two commits, `d8627a9` and `9b81dad`, plus `b46ffdc` for the edit skeleton):

- the `Text` default variant and hyphenation;
- the ui-library reset and the Tailwind 2 preflight defaults;
- the `Button` display class;
- the Search and My Projects page props;
- the masonry order and gap;
- the invented My Projects header;
- the Profile frame and colours;
- avatar size and dimming;
- report-link padding;
- footer height;
- the margin collapse on the search frame;
- the legal hyphenation;
- the whole auth-screen shell and its toasts;
- `noindex` on project pages (BUG-038).

The accepted differences, each tied to an existing decision, are listed in
`docs/testing/visual-regression.md`, "Accepted differences".

## Pending (human)

The roadmap requires a person to review the diffs. To do so, run the three commands in
`docs/testing/visual-regression.md` → "Reviewing against the historical app" (the side-by-side images
are written to a folder of your choice), or open `tests/Visual/reference/` next to
`tests/Visual/baselines/`. Record the result here.

## Status

**Done (automated part).** The maintainer's review of the side-by-sides is pending.
