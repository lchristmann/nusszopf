## What and why

<!-- What this changes and why. Link the issue if there is one. -->

## Checklist

- [ ] I read the relevant page under `docs/` and, for product behavior, the historical evidence it cites.
- [ ] The checks pass locally, in this order (`docs/development/README.md`):
  `composer lint:check`, `composer larastan`, `composer test`, and the Playwright projects the change can affect.
- [ ] New or changed behavior has tests, and a bug fix has a regression test.
- [ ] `docs/` is updated in this change, not afterwards.
- [ ] If this fixes a historical defect: it has a `BUG-NNN` entry in `docs/rewrite/bugs.md` and an approved entry in
  `docs/rewrite/intentional-changes.md`, made before the fix.
- [ ] If it changes what an operator has to do (settings, migrations, upgrades): it is described in `docs/deployment/`
  and `docs/release/breaking-changes.md`.
- [ ] I did not add secrets, personal data, or assets whose license I have not checked (`docs/legal/provenance.md`).
