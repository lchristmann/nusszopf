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
| P-7 Production Compose verification | [`P-07-production-e2e.md`](P-07-production-e2e.md) | Not started |
| P-8 Fresh install | [`P-08-fresh-install.md`](P-08-fresh-install.md) | Not started |
| P-9 Upgrade/migration | [`P-09-upgrade.md`](P-09-upgrade.md) | Not started |
| P-10 Backup/restore drill | [`P-10-backup-restore.md`](P-10-backup-restore.md) | Not started |
| P-11 Search index recovery | [`P-11-search-recovery.md`](P-11-search-recovery.md) | Not started |
| P-12 Queue/scheduler | [`P-12-queue-scheduler.md`](P-12-queue-scheduler.md) | Not started |
| P-13 E-mail delivery | [`P-13-email-delivery.md`](P-13-email-delivery.md) | Not started |
| P-14 Documentation completion | [`P-14-documentation.md`](P-14-documentation.md) | Not started |
| P-15 FOSS repository hygiene | [`P-15-foss-hygiene.md`](P-15-foss-hygiene.md) | Not started |
| P-16 Release preparation and RC | [`P-16-release.md`](P-16-release.md) | Not started |
| P-17 Final sign-off | this page, below | Maintainer only |

## Carried forward to later phases

- **P-7 (production Compose verification):** re-measure the search round trip and the render cost per card on the
  production images (P-6, recommendation 4). The other three P-6 recommendations stay open and non-blocking.

- **P-16 (release candidate testing):** the real-iPhone (iOS Safari) and real-Android (Chrome) smoke pass that P-5 could
  not perform. The maintainer deferred it on 2026-09-24; it is not waived. P-16 must run the eight-step checklist in
  [`P-05-browsers-devices.md`](P-05-browsers-devices.md) ("Real-device pass") on real devices before release, confirm
  DEV-01 on a real iPhone (item 3), and record the device, OS and browser versions there.

- **P-12 (queue):** a welcome or verification mail queued for an account that is deleted before the worker sends it
  fails with `ModelNotFoundException` and stays in `failed_jobs`. Observed during P-3 after quick
  register-then-delete runs and a database reset. It is noise for an operator who reads `failed_jobs`; decide
  there whether such jobs should be dropped instead.

## Section 7 checklist

Filled in as the phases produce evidence. Numbering follows `master-roadmap.md` §7.

(Not yet evaluated.)

## Sign-off (P-17)

Not signed.
