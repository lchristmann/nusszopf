# Bugs and Uncertainty

Every suspected historical defect must be classified in `docs/rewrite/bugs.md` (Fix/Preserve/Unknown/Replace) before it is touched. Fix a bug only after its `docs/rewrite/intentional-changes.md` entry exists, with a regression test in the same change.

Never resolve a documented Unknown (`docs/rewrite/open-questions.md`) by guessing. Investigate against `../historical/` and update the entry, or escalate to the user.

Authorization must never be inferred casually — apply `docs/security/authorization-matrix.md` exactly, or extend it first.
