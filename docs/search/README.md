# Search Specification

Evidence base: `../historical/be-nusszopf/hasura/metadata/tables.yaml` (event triggers named `sync_projects_search` / `sync_requests_search`), `../historical/be-nusszopf/meilisearch/*` (docker-compose, nginx CORS proxy, package.json), `../historical/be-nusszopf/docs/meilisearch/*.md`. This repository configures and runs Meilisearch and defines *when* the index gets updated (via Hasura event triggers), but does not contain the indexer/webhook code itself, nor the frontend search UI/queries. Several important specifics (index names, exact indexed field set, ranking rules actually applied, query-time authorization filtering) are therefore Unknown here and must be resolved by the frontend (`web-nusszopf`) archaeology before being finalized.

## Searchable entities (Confirmed at the trigger level)

Only two tables have search-related event triggers:

- `Project` — event trigger `sync_projects_search`, fires on `insert`, `delete`, and `update` of: `descriptionTemplate, location, period, teamTemplate, contact, description, goal, motto, team, title, visibility, updated_at`.
- `Request` — event trigger `sync_requests_search`, fires on `insert`, `delete`, and `update` of: `descriptionTemplate, category, description, title`.

Both webhook to the same target, `SEARCH_TRIGGER_URL` (a single environment variable shared by both triggers — Confirmed from `hasura/docker-compose.yml` and both trigger definitions), with a shared-secret `secret` header sourced from `EVENT_SECRET`. This strongly implies **one indexing webhook handles both entity types**, likely distinguishing them by table name/payload shape (Hasura event trigger payloads include the table name). Retry policy on both: 3 retries, 10s apart, 60s timeout (`retry_conf`), after which a failed sync is silently dropped — no dead-letter/alerting visible.

`Lead` and `User` have **no** search-related event trigger — Confirmed, neither is ever indexed or searchable.

## What is Confirmed vs. Unknown about the index itself

- Confirmed: a Meilisearch instance exists, run via Docker Compose locally (`meilisearch/docker-compose.yml`, image `getmeili/meilisearch:v0.19.0`) and via a DigitalOcean droplet + systemd service in staging/production (`meilisearch/digitalocean/meilisearch.service`, `nginx.{stage,prod}.conf`), fronted by an nginx CORS proxy (`meilisearch/nginx-cors-proxy.conf`) so a browser can call the Meilisearch HTTP API directly with `X-Meili-Api-Key`.
- Confirmed (from `docs/meilisearch/prod_setup.md`, "Custom Step 3", literally describing the manual production setup steps): at least one index named **`items`** was created manually via a Postman request (`{"uid": "items", "primaryKey": "itemsId"}`), with ranking rules updated to `[current_ranking_rules, desc(updated_at)]` (i.e. the existing default Meilisearch ranking rules, plus a final tie-break by descending `updated_at`). This is the only concrete index configuration evidence found in this repository.
- Inferred, not Confirmed: whether `items` is a single combined index holding both `Project` and `Request` documents (distinguished by a type field) or whether that documentation is outdated/incomplete and real production actually used separate `projects`/`requests` indices. The single shared `SEARCH_TRIGGER_URL` webhook is suggestive of a single combined index or a single indexer service fanning out to per-type indices, but does not settle which.
- Unknown: the exact set of fields sent to Meilisearch per document (the trigger's watched-column list is a reasonable proxy for "fields that matter for search", but the webhook payload/transform code is not in this repository).
- Unknown: filterable/sortable attribute configuration beyond the one ranking-rule note above (no `filterableAttributes`/`sortableAttributes` configuration is present in this repository).
- Unknown: typo-tolerance configuration (Meilisearch's defaults were presumably used, since nothing overrides them here).
- Unknown: pagination behavior as exposed to the frontend (Meilisearch itself defaults to a `limit`/`offset` model; how the UI wraps that — page numbers, infinite scroll, etc. — is frontend evidence).
- Unknown: how the GEO-Search feature mentioned in `docs/meilisearch/considerations.md` ("GEO-Search — currently not implemented") relates to `projects.location` (jsonb) — the note is explicit that geo-search was *not* implemented historically, so a location-based search UI, if one exists in the frontend, was not backed by Meilisearch's native geo feature.

## Authorization filtering at search time (critical open question)

This is the most important unresolved point for search fidelity, and is explicitly flagged rather than guessed:

- The `sync_projects_search` trigger fires on **every** insert/update/delete, including for `visibility = 'private'` projects, and on **every** insert/update/delete of a `Request`, regardless of its parent project's visibility (see `docs/domain/permissions.md` for the corresponding Hasura-level gap on `Request`).
- This means either: (a) the indexing webhook itself filters out private projects/requests before writing to Meilisearch, or (b) private content does get indexed, and visibility filtering happens only at query time in the frontend (a much weaker guarantee, since the Meilisearch API and its key may be reachable directly by a browser, per the CORS proxy setup), or (c) private content is indexed but the frontend never issues a query capable of surfacing it.
- Given (b)/(c) would constitute a real privacy leak (a private project's content becoming discoverable via the public search API), this must be resolved with direct evidence (indexer webhook code, if it can be found, or explicit behavioral testing against a historical deployment) before the rewrite decides how to filter Meilisearch results by visibility. Recorded in `docs/rewrite/open-questions.md` as a priority item — this is a security-relevant unknown, not just a nice-to-have detail.

## Indexing triggers — update and delete semantics

- Update-triggering columns are a deliberate subset for each entity (see above) — e.g. `Project.contact`, `.title`, etc. are watched, but `.user_id`/`.id`/`.created_at` are not (an update touching only unwatched columns does not re-fire the sync). This is Confirmed from the `update.columns` lists and is a meaningful optimization/behavior to preserve: not every project update requires reindexing, only ones touching search-relevant or visibility-relevant fields.
- Delete fully removes the row's `columns: '*'` in the trigger payload, consistent with instructing the webhook to delete the corresponding search document.
- There is no explicit "reindex everything" batch job, cron trigger, or management command anywhere in this repository (`cron_triggers.yaml` is empty) — Confirmed absence. A full reindex, if ever performed historically, must have been a manual/ad-hoc operation, not an automated one. The rewrite should provide a proper reindex command (this is an improvement opportunity, not a fidelity requirement, since there's no historical UX to match here).

## Failure behavior

- Confirmed: Hasura retries a failed webhook delivery 3 times over ~30 seconds, then gives up. There is no visible mechanism for detecting or recovering from a permanently-failed sync (e.g. Meilisearch down for longer than the retry window) — an entity could silently drift out of sync with the search index indefinitely. This is worth explicit improvement in the rewrite (e.g. a durable queue/job with longer backoff), which the project rules permit since it is a genuine infrastructure robustness gap, not a product-behavior change.

## What the rewrite must still determine before implementation

1. Whether `Project` and `Request` were indexed as one combined Meilisearch index or two, and under what document shape (needs frontend query evidence).
2. How visibility filtering was actually enforced at query time (needs indexer/webhook code or observed behavior).
3. The actual ranking rules, filterable/sortable attributes actually configured in production beyond the one `prod_setup.md` note.
4. The real value sets for `Project` fields like `descriptionTemplate`/`location`/`period` as they appear in search results (needs frontend evidence for result card rendering).

Do not resolve any of the above by invention; escalate as open questions if the frontend archaeology also cannot resolve them.
