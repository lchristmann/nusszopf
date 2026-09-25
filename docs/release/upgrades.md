# Upgrades

## Reference finding (Waffle Dashboard, Confirmed)

The documented operator upgrade procedure (`docs/WAFFLE-INSTALLATION-GUIDE.md` → "Waffle Upgrade Guide") is deliberately minimal:

1. Edit the image version tag(s) in `docker-compose.yaml` to the desired release.
2. `docker compose down` then `docker compose up -d`.
3. Data is preserved because it lives in named Docker volumes (Postgres data volume, Laravel storage volume), not in the containers.

No explicit backup-before-upgrade step is called out in the upgrade guide itself, even though a full backup/restore procedure exists elsewhere in the same document (see `docs/references/waffle-dashboard.md`). No explicit migration step is documented — it is implied that migrations run automatically when the new `php-fpm` container starts, but the guide does not state where in the container lifecycle this happens (**Unknown** — would require inspecting the Dockerfile/entrypoint, which belongs to infrastructure archaeology, not this FOSS-lifecycle archaeology).

## Recommendation for Nusszopf (Inferred, needs approval)

Nusszopf's operator-facing upgrade guide (to live in `docs/deployment/README.md` and be cross-referenced here) should be more explicit than the reference, because Nusszopf is meant to be operable by "a person who has never seen the source code" (`CLAUDE.md` → Self-hosting):

1. **Read the release notes / `CHANGELOG.md` for the target version first**, specifically for any `**Breaking:**`/`**Migration required:**` tagged entries (see [`breaking-changes.md`](breaking-changes.md)).
2. **Take a backup** (database dump + storage volume archive) before upgrading — make this an explicit, non-optional step in the documented procedure, not just something covered in a separate backup section the operator has to know to go read.
3. Update the image tag(s) in the operator's Compose file/`.env` to the new version.
4. Pull the new image(s), then bring the stack down and back up (or use `docker compose up -d` alone if the entrypoint is designed to apply migrations safely on start — this must be an explicit, documented, and tested behavior of Nusszopf's own entrypoint, not assumed by analogy to the reference).
5. Verify health (application health check endpoint, queue/worker running, scheduler running) before considering the upgrade complete.
6. If verification fails, roll back: point the compose file back at the previous image tag, restore from the pre-upgrade backup if a migration partially applied, and consult [`breaking-changes.md`](breaking-changes.md) for that version.

## Implemented (operational track O-1, 2026-09-22)

The recommended procedure exists and is verified as `docs/deployment/operations.md`, "Upgrades": change `NUSSZOPF_VERSION`, `docker compose pull`, `docker compose up -d`. The entrypoint of the `php-fpm` container
applies pending migrations (`migrate --force --isolated`) and rebuilds the caches before it serves, so migrations are an explicit, tested behavior of Nusszopf's own entrypoint; `queue-worker` and `scheduler` wait for it.
Verifying health afterwards is `docker compose ps` and `php artisan nusszopf:health`.

## Tested (phase P-9, 2026-09-25)

`docs/release/parity/P-09-upgrade.md` has the evidence. P-9 upgraded two earlier builds to the current one, with
populated data, and changed the procedure:
- **The operator's files are part of a release.** P-9 upgraded from `8c4a2eb` by setting only `NUSSZOPF_VERSION`, as
  the procedure then said. The old `docker-compose.yaml` stayed, so the new release ran without its storage mount (new
  avatars answered 404) and without its `./legal` mount (finding P9-01).
- **`install.sh --upgrade <version>` is the upgrade step.** It replaces `docker-compose.yaml` and
  `.env.production.example` with the release's, and sets `NUSSZOPF_VERSION`. It keeps the rest of `.env`, and keeps the
  previous files as `*.previous`. It lists empty required settings, unsafe values from earlier templates, and settings
  that are new. `docker compose pull` and `docker compose up -d` follow, as before.
- **Recommendation step 6 is settled as follows.** Rollback means restoring the pre-upgrade backup, never
  `migrate:rollback`. Starting the previous release on the newer schema is allowed only where the release notes say
  so. Details: `docs/deployment/operations.md`, "Rollback".

**Compatibility rules for release authors.** They follow from the above and from `versioning.md`:
- Migrations are forward-only and additive within a major version. Every release since `8c4a2eb` is, and its four
  migrations ran on populated data in about 40 ms in total.
- Operator action beyond the three upgrade commands is a **Migration required:** changelog entry
  (`breaking-changes.md`). Examples: a new required setting without a default, a changed pin of PostgreSQL, Redis or
  Meilisearch, or a reindex because the search documents changed.
- A release whose previous release cannot run on its schema says so in its notes. Operators then know that only the
  backup takes them back.
- Before tagging, run `sh scripts/upgrade-test.sh <previous tag> --suite` (`release-process.md`).

## Status

Confirmed: the reference upgrade mechanics (edit the tag, `down`/`up`, the volumes persist). Confirmed by P-9 on
populated installations, which were release-equivalent builds, because no release has been published yet: Nusszopf's
own procedure, `docs/deployment/operations.md`, "Upgrades" and "Rollback". Repeating it from the first real tag to the
next is part of P-16. The "Recommendation" section above is kept as the reasoning that led to it.
