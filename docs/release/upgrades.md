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

## Status

Confirmed: reference upgrade mechanics (edit tag, `down`/`up`, volume persistence). Everything under "Recommendation for Nusszopf" is Inferred and depends on the entrypoint/migration design decided during infrastructure implementation — do not treat it as final until that design exists and this page is revisited.
