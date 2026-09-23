# P-3 Accessibility verification (2026-09-23)

Exit evidence (`master-roadmap.md` §4): an automated axe run per screen, a manual keyboard and screen-reader
pass on the journeys, and every finding triaged through the bug protocol. The bar is decision A-7 (§7.3.12:
"no known keyboard trap or unlabeled control on the journeys").

## Automated axe run

`tests/E2E/specs/a11y/axe.spec.ts`, 45 screens and states (listed in `docs/testing/accessibility.md`).

- **Found:** three critical violations (the avatar dialog's unnamed buttons and unlabelled file input, and the
  place combobox's missing `aria-controls`), one serious (the unnamed suggestion list), and a missing `h1` on
  eight states (moderate).
- **Now:** zero violations apart from colour contrast, which is measured and not gated (below).
- **Label in Name** (WCAG 2.5.3, added to the same spec): the scan found 10 names that did not contain their
  visible text. There are none now.

## Keyboard pass

`tests/E2E/specs/a11y/keyboard.spec.ts` drives the journeys' interactive parts by keyboard only, in Chromium,
Firefox and WebKit. Before the fixes, a probe of every dialog, popover and menu found:

- the nav menu and the filter popover neither took focus nor closed on Escape;
- the card menus did not return focus to their button;
- the request editor ignored Escape;
- WebKit sometimes left focus outside a freshly opened dialog;
- the password toggle was unreachable;
- keyboard focus was invisible (the historical design removes every outline).

All of these are fixed. The spec passed 36 of 36 runs (3 engines × 4 tests × 3 repetitions) and runs in every E2E job.

## Screen-reader review

The accessibility tree of Home, search, the project page, login, the legal pages, the 404 page, My Projects, the
wizard, Profile and the edit screen was reviewed as a screen reader would announce it (`docs/testing/accessibility.md`).
It found five problems, all fixed:

- the section titles were paragraphs;
- the visitor counter read as four loose digits;
- the location link's name hid the place;
- the card menus had identical names;
- the label mismatches above.

**Not satisfied yet — pending (maintainer):** this review is not the screen-reader pass A-7 requires. The
maintainer performs a listening pass with a real screen reader (NVDA on Windows or VoiceOver on macOS/iOS) through
the journeys, separately. Suggested scope: Journey 2 (register, log in), Journey 3 (create a project with a
request), Journey 5 (search and filter), Journey 7 (contact dialog). Record the result here.

## Bug protocol

| Entry | Classification | State |
|---|---|---|
| BUG-039 names, labels, error association | Fix (A-7) | Implemented |
| BUG-040 keyboard operability, focus indicator | Fix (A-7) | Implemented |
| BUG-041 headings | Fix (A-7) | Implemented |
| BUG-042 contrast: messages on the contact dialog, 4.20:1 | Fix, approved by the maintainer 2026-09-23 | Implemented: `warning-750`, 4.6:1 |
| BUG-043 contrast: Home newsletter button, 4.42:1 | Fix, approved by the maintainer 2026-09-23 | Implemented: `steel-800` text, 6.0:1 |
| BUG-044 contrast: "Ausloggen", 2.10:1 | Fix, approved by the maintainer 2026-09-23 | Implemented: `warning-900`, 4.6:1 |
| BUG-045 contrast: dimmed older toasts, ≈2.1:1 | Preserve, waived by the maintainer 2026-09-23 | Historical dimming kept |

The labels of the disabled period inputs (2.72:1) are exempt under WCAG 1.4.3 (inactive components) and are
documented, not filed. Each fix keeps the historical hue: the two warning tones are the lightest shades of
`warning-700`'s own hue that reach 4.6:1 on their background. `docs/rewrite/intentional-changes.md` has one entry for
BUG-039–041 and one for the three colours.

Since the contrast decisions, the axe spec gates colour contrast: any failure except the disabled labels and the
waived toasts fails the run. Restoring the old "Ausloggen" colour was confirmed to fail it.

## Other evidence

- `lang="de"` on every page and every e-mail.
- The visual baselines are pixel-identical after all the fixes: the only visible change is the focus outline
  during keyboard use.
- The complete E2E suite passed three times in a row before the contrast fixes, and again after them
  (2026-09-24): 159 passed, 0 failed, and 12 skipped by design (6 search runs gated on environment variables,
  and the axe spec's Firefox/WebKit runs). The visual suite passed 69 of 69 at the stricter threshold, with only
  the Home baselines rebaselined for BUG-043. Pest: 557 passed. Pint and Larastan are clean.
- Also fixed on the way, test-only: races in the E2E page objects that the complete reruns exposed
  (`docs/testing/README.md`, "Finish-line conventions").

## Status

**Done except the screen-reader pass.** The axe run, the keyboard pass and the bug protocol, including the
maintainer's contrast decisions, are complete. **Open (maintainer):** the real NVDA/VoiceOver listening pass through
the journeys. P-3 is complete only once it is recorded here.
