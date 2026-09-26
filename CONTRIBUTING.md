# Contributing to Nusszopf

Thank you for helping. Nusszopf 2 is a **faithful reimplementation** of the historical Nusszopf
(`github.com/nusszopf/web-nusszopf`, `be-nusszopf`, `emails-nusszopf`), not a redesign. Most of what looks like a
design question here has already been answered by the historical product or by a recorded decision, so a little
reading first saves everyone a round trip.

By taking part you agree to the [Code of Conduct](CODE_OF_CONDUCT.md). A security problem goes to
[`SECURITY.md`](SECURITY.md), never to a public issue.

## Before you start

1. Read `docs/rewrite/decisions-register.md` (what is already decided, and what still needs a human call) and
   `docs/rewrite/bugs.md` (every classified historical defect). Do not reopen a decided item, and do not settle an open
   one yourself: raise it in an issue.
2. Read the topic page for the area you touch (`docs/domain/`, `docs/design/`, `docs/security/`, ...; the index is
   `docs/README.md`). Where a page says **Unknown**, the answer is in `../historical/` or it goes to
   `docs/rewrite/open-questions.md`; do not fill the gap with a guess.
3. Open an issue first for anything larger than a fix or a documentation change, so you do not build something that
   cannot be merged.

## What fits, and what needs approval

- **Welcome without ceremony:** fixes to the documentation, tests, tooling, CI, and to the installation, upgrade and
  backup procedures; bugs where Nusszopf 2 differs from the historical product.
- **Needs the maintainer's approval first:** any change to product behavior or design, including fixing a defect the
  historical product had. The rules are in `CLAUDE.md`, "Product fidelity rules" and "Bug classification workflow":
  the defect gets a `BUG-NNN` entry in `docs/rewrite/bugs.md` and an entry in `docs/rewrite/intentional-changes.md`
  **before** the fix, and the fix comes with a regression test. The visual design is a hard requirement
  (`docs/design/`); Tailwind is a way to reproduce it, not a reason to change it.
- **Not accepted:** SaaS concepts (tenants, billing, metering, mandatory hosted services) and anything that makes
  self-hosting harder.

## Setting up

Everything runs in Docker; the host needs only Docker, Docker Compose and Git. Follow
[`README-DEV.md`](README-DEV.md) ("Getting started"), which also has the troubleshooting table.

## Before you open a pull request

Run what CI runs, in this order (`docs/development/README.md`):

```shell
docker compose -f compose.dev.yaml exec workspace composer lint:check
docker compose -f compose.dev.yaml exec workspace composer larastan
docker compose -f compose.dev.yaml exec workspace composer test
docker compose -f compose.dev.yaml exec playwright npx playwright test
```

The last one is the browser suite; run the projects your change can affect (`docs/testing/README.md`). `composer pint`
fixes formatting. A change is finished when its tests exist (a happy path alone is not enough), the relevant `docs/`
page is updated **in the same change**, and the states of a screen (loading, empty, error, success) are all covered.

## Pull requests

- Fork the repository, branch from `main`, and open the pull request against `main`. The template lists what a reviewer
  checks.
- Keep a pull request to one concern. A bug fix is not permission to redesign what is around it.
- Commit messages follow the style of the history: `type(scope): summary`, for example `fix(queue): ...` or
  `docs: ...`, in the imperative, with the reason in the body when it is not obvious.
- Do not edit `CHANGELOG.md` and do not change version numbers: the release process (`docs/release/release-process.md`)
  is the maintainer's. Describe anything an operator has to do differently in the pull request; how changelog entries
  are written is still an open point (`docs/release/changelog.md`).
- Do not commit secrets, `.env` files or personal data, and do not add fonts, images or code whose license you have not
  checked: `docs/legal/provenance.md` lists what is here and where it comes from, and `NOTICE` names the third-party
  licenses.

## Licensing of contributions

Nusszopf is licensed under the **GNU General Public License v3.0 or later** (`LICENSE`). By submitting a contribution
you license it under the same terms. There is no contributor license agreement.

## AI assistants

If you use an AI coding assistant, point it at `CLAUDE.md` and `.claude/rules/`: they hold the constraints above in the
form such tools read. You remain responsible for what you submit; the checks above apply to it in full.
