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

**Pending (human):** a listening pass with a real screen reader (NVDA on Windows or VoiceOver on macOS/iOS)
through the journeys. This environment cannot run one. Suggested scope: Journey 2 (register, log in), Journey 3
(create a project with a request), Journey 5 (search and filter), Journey 7 (contact dialog). Record the result
here.

## Bug protocol

| Entry | Classification | State |
|---|---|---|
| BUG-039 names, labels, error association | Fix (A-7) | Implemented |
| BUG-040 keyboard operability, focus indicator | Fix (A-7) | Implemented |
| BUG-041 headings | Fix (A-7) | Implemented |
| BUG-042 contrast: messages on the contact dialog, 4.20:1 | Proposed Fix | **Awaiting the maintainer's approval or waiver** |
| BUG-043 contrast: Home newsletter button, 4.42:1 | Proposed Fix | **Awaiting the maintainer's approval or waiver** |
| BUG-044 contrast: "Ausloggen", 2.10:1 | Proposed Fix | **Awaiting the maintainer's approval or waiver** |
| BUG-045 contrast: dimmed older toasts, ≈2.1:1 | Proposed Fix | **Awaiting the maintainer's approval or waiver** |

The labels of the disabled period inputs (2.72:1) are exempt under WCAG 1.4.3 (inactive components) and are
documented, not filed. The single intentional-change entry for BUG-039–041 is in `docs/rewrite/intentional-changes.md`.

## Other evidence

- `lang="de"` on every page and every e-mail.
- The visual baselines are pixel-identical after all the fixes: the only visible change is the focus outline
  during keyboard use.
- The complete E2E suite passed three times in a row. The last run, on everything as committed, had
  159 passed, 0 failed, and 12 skipped by design (6 search runs gated on environment variables, and the axe
  spec's Firefox/WebKit runs). Pest: 557 passed. Pint and Larastan are clean.
- Also fixed on the way, test-only: races in the E2E page objects that the complete reruns exposed
  (`docs/testing/README.md`, "Finish-line conventions").

## Status

**Done (automated part).** Pending: the maintainer's decisions on BUG-042–045, and the real screen-reader
listening pass.
