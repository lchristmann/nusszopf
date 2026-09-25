# Parity report — finish-line evidence

This is the report that `docs/rewrite/master-roadmap.md` §7.7 (item 24) requires: one page per
finish-line phase (P-1…P-16), each linking its evidence. The maintainer's sign-off (P-17) goes at the
end of this page once every phase is complete. Nothing here is signed until then.

Status values: **Done** (the phase's exit evidence from roadmap §4 exists and is linked),
**Done (automated part)** (everything a machine can check is done; the part the roadmap assigns to a
person is listed as pending), **In progress**, **Not started**.

| Phase | Page | Status |
|---|---|---|
| Doc debts (§2.3, B-1…B-3) | [`00-doc-debts.md`](00-doc-debts.md) | Done |
| P-1 Parity audit | [`P-01-parity-audit.md`](P-01-parity-audit.md) | Done |
| P-2 Visual regression | [`P-02-visual-regression.md`](P-02-visual-regression.md) | Done (maintainer review approved 2026-09-23) |
| P-3 Accessibility | [`P-03-accessibility.md`](P-03-accessibility.md) | Done (closed by the maintainer 2026-09-24; screen-reader listening pass is a post-1.0 opportunity, not performed) |
| P-4 Security review | [`P-04-security.md`](P-04-security.md) | Done (closed by the maintainer 2026-09-24; one High, four Medium, six Low/Info findings, all fixed; SEC-10 approved) |
| P-5 Browser/device verification | [`P-05-browsers-devices.md`](P-05-browsers-devices.md) | Done (closed by the maintainer 2026-09-24 on emulated evidence; DEV-01 and DEV-02 fixed; the real-device pass is deferred to P-16, not waived) |
| P-6 Performance sanity | [`P-06-performance.md`](P-06-performance.md) | Done (closed by the maintainer 2026-09-24; PERF-01–04 fixed; four recommendations kept open and non-blocking, production re-measurement in P-7) |
| P-7 Production Compose verification | [`P-07-production-e2e.md`](P-07-production-e2e.md) | Done (closed by the maintainer 2026-09-24; whole suite green on the production images; P7-01 fixed; limitations deferred to P-8/P-12/P-13/P-16) |
| P-8 Fresh install | [`P-08-fresh-install.md`](P-08-fresh-install.md) | Done (closed by the maintainer 2026-09-25; bare host to healthy in under 3 min of machine time; P8-03 fixed; the GitHub/GHCR download and the second-person install deferred to P-16, not waived) |
| P-9 Upgrade/migration | [`P-09-upgrade.md`](P-09-upgrade.md) | Done (closed by the maintainer 2026-09-25; tested from `4de0194` and `8c4a2eb`, the closest builds to an N-1, because no release exists; P9-01 fixed; the true N-1 test and the real release download, GHCR pull and arm64 checks deferred to P-16, not waived; the restore drill stays in P-10) |
| P-10 Backup/restore drill | [`P-10-backup-restore.md`](P-10-backup-restore.md) | Done (closed by the maintainer 2026-09-25; restored onto an empty Docker host: data, files and search identical, the Chromium suite green; the rollback from `8c4a2eb` works and can be upgraded again; P10-01…P10-05 fixed; the GHCR pull, arm64 and a physically separate host deferred to P-16, not waived; encryption and stale-backup alerting out of v1 scope by decision B2) |
| P-11 Search index recovery | [`P-11-search-recovery.md`](P-11-search-recovery.md) | Evidence complete, awaiting the maintainer (2026-09-25; four losses, including an empty and a corrupt Meilisearch, each recovered by the documented block to identical answers; P11-01 (BUG-047, `id:asc` tie-break, for approval), P11-02 and P11-03 fixed) |
| P-12 Queue/scheduler | [`P-12-queue-scheduler.md`](P-12-queue-scheduler.md) | Not started |
| P-13 E-mail delivery | [`P-13-email-delivery.md`](P-13-email-delivery.md) | Not started |
| P-14 Documentation completion | [`P-14-documentation.md`](P-14-documentation.md) | Not started |
| P-15 FOSS repository hygiene | [`P-15-foss-hygiene.md`](P-15-foss-hygiene.md) | Not started |
| P-16 Release preparation and RC | [`P-16-release.md`](P-16-release.md) | Not started |
| P-17 Final sign-off | this page, below | Maintainer only |

## Carried forward to later phases

- **P-7 (production Compose verification):** re-measure the search round trip and the render cost per card on the
  production images (P-6, recommendation 4). Done in P-7. The other three P-6 recommendations stay open and
  non-blocking, and so does P-7's recommendation 5 (OPcache timestamps).

- **P-16 (release candidate testing):** the real-iPhone (iOS Safari) and real-Android (Chrome) smoke pass that P-5 could
  not perform. The maintainer deferred it on 2026-09-24; it is not waived. P-16 must run the eight-step checklist in
  [`P-05-browsers-devices.md`](P-05-browsers-devices.md) ("Real-device pass") on real devices before release, confirm
  DEV-01 on a real iPhone (item 3), and record the device, OS and browser versions there.

- **P-16 (release):** P-8 ran against files prepared the way a release is and images loaded locally, because no release
  exists yet (P8-01). P-16 must check the real path: the `releases/latest/download/install.sh` URL, `install.sh` without
  `NUSSZOPF_BASE_URL`, the pull from GHCR (and its time), arm64, and an install by a second person on a real host with
  an ACME certificate (`P-08-fresh-install.md`).

- **P-9 (upgrades):** done in P-9. `redis:alpine` moved from 8.8.1 to 8.10.2 during one pull, so it is pinned to
  `redis:8-alpine` (P9-03). `postgres:16-alpine` was already pinned to a major version.

- **P-10 (backup/restore):** P-9 made the `pg_dump` line of "Backups" work (P9-02: the shell has no `$DB_DATABASE`)
  and applied the same fix to "Restore" step 1 without running it. The drill must run the restore as written. Restoring
  is also the rollback path of an upgrade (`operations.md`, "Rollback"), so the drill should include restoring a
  pre-upgrade backup together with `docker-compose.yaml.previous` and `.env.previous`. Done in P-10: the old restore could not run as written (P10-02), and on an upgraded
  database it left a mix of schemas that could not be upgraded again (P10-03). Restore and rollback are now one
  drilled block (`scripts/restore-test.sh`).

- **P-16 (release):** run `sh scripts/restore-test.sh --rollback-from <previous tag>` next to the upgrade test, pull
  the images from GHCR onto the new host, and restore once on a physically separate machine (`P-10-backup-restore.md`,
  section 7).

- **P-16 (release):** P-9 had no published release to start from. Run `sh scripts/upgrade-test.sh <previous tag> --suite`
  from the first real tag to the release candidate. Also check the real download path:
  - `install.sh --upgrade` fetched from `releases/download/<version>/`;
  - `--upgrade` without a version, which moves to `latest`;
  - `docker compose pull` fetching the Nusszopf images from GHCR (`P-09-upgrade.md`, "Limitations").

- **P-12 (queue):** a welcome or verification mail queued for an account that is deleted before the worker sends it
  fails with `ModelNotFoundException` and stays in `failed_jobs`. Observed during P-3 after quick
  register-then-delete runs and a database reset. It is noise for an operator who reads `failed_jobs`; decide
  there whether such jobs should be dropped instead.

## Section 7 checklist

Filled in as the phases produce evidence. Numbering follows `master-roadmap.md` §7.

(Not yet evaluated.)

## Sign-off (P-17)

Not signed.
