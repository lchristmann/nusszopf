# Breaking Changes

## Policy for Nusszopf (decided with the changelog format; first exercised by P-9)

- A MAJOR version bump is the only place a breaking change may occur (see [`versioning.md`](versioning.md)).
- Every breaking change must have a `CHANGELOG.md` entry tagged `**Breaking:**` (see [`changelog.md`](changelog.md)) stating, at minimum:
  - what changed
  - who is affected (which configurations/features)
  - the required operator action, as literal copy-pasteable commands where possible
  - whether the change has database implications (a migration that cannot be reversed, a column/table removal, a changed default)
  - whether the change has configuration implications (a renamed/removed/required environment variable)
  - the exact upgrade procedure for that version, if it differs from the standard procedure in [`upgrades.md`](upgrades.md)
  - rollback considerations specific to that change (e.g. "this migration is not reversible; restore from backup to roll back")
- The GitHub Release for a MAJOR version should surface these entries prominently rather than burying them in a flat bullet list, so an operator skimming release notes cannot miss them.

## Operator action in practice (P-9, 2026-09-25)

`install.sh --upgrade` (`docs/handbuch/deployment.md`) already brings the new `docker-compose.yaml` and
lists every setting `.env` lacks. So a new *optional* setting, or a changed compose file, needs no changelog tag.
A **Migration required:** entry is needed when:
- a new setting must be set and has no default (`docker compose` then refuses to start);
- a default changes in a way an existing `.env` does not pick up, because that `.env` names the old value;
- a pin of `postgres`, `redis` or `meilisearch` changes. Meilisearch cannot open a database written by another
  version. The steps are then to stop the stack, remove the `meilisearch-data` volume, start the stack, and run
  `search:reindex`. This is inferred from Meilisearch's documentation and was not exercised in P-9;
- the search documents change, so `search:reindex` is needed;
- the previous release cannot run on the new schema, so only the backup takes an operator back.

## Status

The policy above is Nusszopf's own: the `**Breaking:**`/`**Migration required:**` tags are part of the decided changelog format (`docs/release/changelog.md`), and P-9 tested the triggers listed under "Operator action in practice". No release has needed the tags yet (`1.0.0` is the first stable release). One trigger remains inferred: the steps for a changed Meilisearch pin were not exercised.
