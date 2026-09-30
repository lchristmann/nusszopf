# Historical Source Map

Revisions studied during the archaeology pass recorded in this `docs/` tree (2026-09-18). If any reference repository is updated in `../` after this date, re-verify affected documents before trusting them further — they are snapshots against the commits below, not against "whatever is currently checked out."

| Repository | Revision (commit) | Commit date | Purpose | Notes |
|---|---|---|---|---|
| `historical/web-nusszopf` | `b9159406cc05280ddec2ca379921403e5708d01` | 2023-08-30 | Frontend (Next.js monorepo: `webapp`, `auth-login`, `auth-password`, `ui-library`, `e2e`) | Primary source for `docs/design/*`, `docs/journeys/README.md`, and the frontend half of `docs/authentication/README.md`. |
| `historical/be-nusszopf` | `6bf4bcb8ed33af0534bbed406006b8e10155e394` | 2021-05-20 | Backend: Hasura GraphQL engine config/metadata/migrations, Auth0 rules, Meilisearch infra | Primary source for `docs/domain/*`, `docs/search/README.md`, `docs/security/README.md`, and the backend half of `docs/authentication/README.md`. **Note the ~2.3 year gap** between this commit's date and `web-nusszopf`'s last commit — the frontend repository kept receiving commits for over two years after the backend repository's last commit. This is worth confirming (not yet confirmed in this pass): was `be-nusszopf` genuinely frozen while `web-nusszopf` evolved against an unchanging backend contract, or is this HEAD simply not the backend's true final state (e.g. a later backend revision exists but wasn't checked into this reference checkout)? Record as an open question if it turns out to matter for any specific screen/field. |
| `historical/emails-nusszopf` | `4e1be0fe6217db1e24fd7dd64f80971e616088f3` | 2021-03-19 | MJML email templates (Auth0 + SendGrid) | Primary source for `docs/email/README.md`. |
| `rw4lll/laravel-docker-examples` | `34328ca3682996cb9e031ed3d934e9485f980f2f` | 2026-03-25 | Docker/Laravel infrastructure reference (not product) | Source for `docs/references/laravel-docker-examples.md`, `docs/deployment/README.md`. |

## How to re-verify

```bash
cd ../historical/web-nusszopf && git log -1 --format="%H %ai"
cd ../historical/be-nusszopf && git log -1 --format="%H %ai"
cd ../historical/emails-nusszopf && git log -1 --format="%H %ai"
```

If any hash above no longer matches, treat every document that cites that repository as a **snapshot that needs re-diffing**, not as automatically stale — most historical repositories are archived/finished projects and are not expected to change.
