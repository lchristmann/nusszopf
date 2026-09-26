# Doc debts before the finish-line phases (2026-09-23)

Scope: `docs/rewrite/master-roadmap.md` §2.3 and decisions B-1/B-2/B-3, plus slice-era wording that the
finished slices made stale. Docs-only; no completed slice was reopened.

## Checked

| Debt | Finding | Action |
|---|---|---|
| ui-avatars.com replacement has no `intentional-changes.md` entry | Entry exists ("Author avatar fallback no longer calls ui-avatars.com") | none |
| BUG-013/014/015 still Unknown (B-1) | Classified Preserve / Preserve (dead vocabulary) / Preserve in `bugs.md` | none |
| Password policy not in the register (B-2) | Recorded in `decisions-register.md`; open question Resolved | none |
| `php ^8.3`, MIT in `composer.json` (B-3, A-8) | `^8.5`, `GPL-3.0-or-later` | none |
| `docs/rewrite/README.md` phase list | Said "in progress" for the finished feature phase, "not started" for parity | updated |
| `operations.md` "Nothing here is implemented" | Already promoted; only backup/restore waits for P-10 | resolved in P-10; the rest of `docs/` was stale-checked in P-14 (`P-14-documentation.md`) |
| Roadmap §1 and §2.3 described the 2026-09-21 state | Stale | §1 marked as a snapshot, §2.3 rewritten as "all reconciled" |
| §7.1.6: "Intentional scaffolding" rows in slice docs | Open rows in `second-slice.md`, `seventh-slice.md` (2), `eighth-slice.md`, `golden-master-review.md` (7) | each struck through with the slice that closed it |
| Slice limitations closed by later slices | `ninth-slice.md` (Home form, error page), `fourth-slice.md` (contact scaffold), `third-slice.md` row 9, `journeys/README.md` Journey 6 note | annotated as closed |

## One decision made here

`seventh-slice.md` listed "resend the verification e-mail only next to the Persönlich error, until Profile
(slice 8)". Slice 8 did not add a Profile resend, and the historical Profile has no verification
affordance (verification was never enforced historically). Adding one would be invented UI. Decision A-3
gates only the personal contact, so the resend stays where that gate applies. Row closed as final.

## Remaining

Nothing in this scope. `operations.md`'s backup/restore banner was resolved by P-10.
