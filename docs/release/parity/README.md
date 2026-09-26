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
| P-11 Search index recovery | [`P-11-search-recovery.md`](P-11-search-recovery.md) | Done (closed by the maintainer 2026-09-25; four losses, including an empty and a corrupt Meilisearch, each recovered by the documented block to identical answers, privacy intact; P11-01 (BUG-047, `id:asc` tie-break, approved), P11-02 and P11-03 fixed; GHCR/arm64 deferred to P-16, queue failure while reindexing to P-12, not waived) |
| P-12 Queue/scheduler | [`P-12-queue-scheduler.md`](P-12-queue-scheduler.md) | Done (closed by the maintainer 2026-09-26; the production stack's worker, Redis and scheduler restarted, killed and stopped with work waiting, a job through all five attempts, `search:reindex` with the worker down, Redis down and Meilisearch lost; O-2 and P-7 evidence reused; P12-01…P12-06 fixed: a Redis crash lost every queued job, `failed_jobs` was invisible to `/health`, mails for deleted accounts, two `search:reindex` gaps, a misleading queue message; the `failed_jobs` health state approved, at-least-once delivery accepted as documented, the Redis AOF upgrade caveat deferred to P-16's N-1 upgrade, not waived) |
| P-13 E-mail delivery | [`P-13-email-delivery.md`](P-13-email-delivery.md) | Done (closed by the maintainer 2026-09-26; all seven mail types delivered through Resend on the production stack and inspected in Proton Mail; P13-01 fixed, P13-03 (HTML-only) accepted as the intended format; Gmail, Outlook and Apple Mail unverified and deferred to P-16, not waived; a generic SMTP relay with real TLS not tested) |
| P-14 Documentation completion | [`P-14-documentation.md`](P-14-documentation.md) | Done (closed by the maintainer 2026-09-26; the docs were audited against the code and P-1…P-13, twelve findings fixed, among them a dev first-run sequence that left the containers without an `APP_KEY`; no Proposal banner left; the P-13 mail decision recorded everywhere; the first tag's number, the changelog and the release-only checks deferred to P-16, repository hygiene to P-15; a complete fresh-clone run of the dev setup was not performed, the steps were checked individually) |
| P-15 FOSS repository hygiene | [`P-15-foss-hygiene.md`](P-15-foss-hygiene.md) | Done, awaiting the maintainer's sign-off (CONTRIBUTING, SECURITY, CODE_OF_CONDUCT, issue and PR templates, Dependabot and a weekly advisory/secret workflow, license notices in the build, asset provenance, `.env.example` audit, README badges; the whole Git history scanned with gitleaks, no secret; no known dependency vulnerability; publishing the repository, GitHub's private vulnerability reporting, a conduct/security mailbox, `FUNDING.yml`, the named people in the legal example and the mail layout's Google Fonts link are the maintainer's, section 5) |
| P-16 Release preparation and RC | `P-16-release.md` (created by the phase) | Not started |
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

- **P-12 (queue):** from P-11, the queue failing while `search:reindex` runs. Done in P-12 (step 6 of
  `scripts/queue-scheduler-test.sh`): the worker down, Redis down and Meilisearch lost while the documents wait each
  end in a documented, verified state, and two gaps were fixed (P12-04, P12-05).

- **P-16 (release):** as for P-8…P-10, the search-recovery drill ran on locally built images. Run
  `sh scripts/search-recovery-test.sh` on the release candidate, with the images pulled from GHCR, and on arm64.

- **P-16 (tests):** `search.spec.ts`'s recovery test wipes the one shared index, so it can race another engine's
  search test when engines run in parallel on one stack (seen once with 4 workers in P-11). CI is not affected, since it
  runs one engine per job. Give the test its own index prefix, or run it last. Documented as a known limitation in
  `docs/testing/README.md` and `README-DEV.md` (P-14).

- **P-12 (queue):** a welcome or verification mail queued for an account that is deleted before the worker sends it
  failed with `ModelNotFoundException` and stayed in `failed_jobs`. Done in P-12: such a job is now dropped (P12-03).

- **P-13 (e-mail):** done in P-13 for Resend (a controlled provider failure reached `failed_jobs`; the P-12 queue path is
  unchanged). Rate limiting (429), greylisting and a provider outage are still unmeasured, and a mail queued at the
  moment of a worker crash can be delivered twice (`P-12-queue-scheduler.md`, section 6), which with a real provider is a
  second mail. A generic SMTP relay with real TLS was not tested either.

- **P-16 (release):** the seven mails of `scripts/mail-delivery-test.sh` must be received and rendered in Gmail, Outlook
  (desktop and web) and Apple Mail, and the result recorded in `P-13-email-delivery.md` (section 9), including whether the
  inline SVG logo shows. The maintainer deferred it on 2026-09-26; it is not waived.

- **P-14 (documentation):** done in P-14. A fresh dev stack needed the `vendor`/`node_modules` volumes handed to the
  developer's user, and `MEILISEARCH_KEY` in `.env`; P-14 found a third defect of the same first run, the `APP_KEY`
  generated after the containers had started (P14-01). `README-DEV.md` and `docs/development/README.md` now have the
  working sequence and a troubleshooting table.

- **P-16 (release):** run `sh scripts/queue-scheduler-test.sh` on the release candidate with the images pulled from
  GHCR, and on arm64. P-9's `upgrade-test.sh` from the stand-ins `4de0194` and `8c4a2eb` now loses sessions and
  in-flight jobs once, because they run Redis without the append-only file; a real N-1 tag does not.

- **P-16 (release):** what P-14 could not close, because it needs a release or a decision:
  - `CHANGELOG.md` is an empty file, and `release.yml` refuses to publish a release whose tag has no section in it. Its
    first content is the changelog consolidation of P-16 (`docs/release/changelog.md`).
  - The number of the first tag: the register says `0.x` until parity, `release-process.md` suggests `0.1.0-rc.1`, and
    roadmap P-16 names `1.0.0-rc.N`. The maintainer decides when P-16 starts (`docs/release/versioning.md`).
  - Who writes changelog entries stays deferred (register C1).

- **P-15 (repository hygiene):** done in P-15 (`P-15-foss-hygiene.md`). What it leaves is the maintainer's: making the
  repository public and enabling private vulnerability reporting, a conduct/security mailbox, `FUNDING.yml`, the named
  people in the legal example (register C3-C5), and the mail layout's Google Fonts link (register C6). The first
  Dependabot pull requests and the `Security` workflow's first run on GitHub can only be seen once the repository is public.

## Section 7 checklist

Filled in as the phases produce evidence. Numbering follows `master-roadmap.md` §7.

(Not yet evaluated.)

## Sign-off (P-17)

Not signed.
