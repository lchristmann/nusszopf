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

## Authorization filtering at search time — **Resolved** (pre-implementation review pass, 2026-09-18)

Interpretation (a) is confirmed correct: **the indexing webhook itself filters at write time**, before anything reaches Meilisearch. Source: `web-nusszopf/projects/webapp/src/pages/api/events/search.js` (the webhook `SEARCH_TRIGGER_URL` points to) and `src/utils/functions/search.function.js`, read in full.

- The `sync_projects_search` trigger does fire unconditionally (including for private projects), but the receiving handler's `_upsertProject` explicitly checks `data.new.visibility === PROJECT.visibility.public` before calling `index.addDocuments`/`updateDocuments` — a private project is never written to the index at all, on insert or update.
- **Public → private transitions actively clean up the index**: `_upsertProject`'s second branch (`data.old.visibility === public && data.new.visibility === private`) explicitly calls `index.deleteDocuments([...requestIds, data.new.id])` for the project and every one of its requests — unpublishing a project removes it and its requests from search immediately, not just stops future syncs.
- **`Request` indexing inherits the parent project's visibility independently of the `Request` table's own (gapped) Hasura permissions** (`BUG-002`): `addRequest`/`updateRequest` call `_syncProject`, which fetches the parent project via `getProjectCrop` (an **admin-secret** GraphQL call, `api.function.js`'s `fetchWithAdminAuth` — a trusted server-to-server credential, not the visibility-filtered `user`/`anonymous` role) and only proceeds if `projectCrop.visibility === public`. A request under a private project is therefore **never indexed**, regardless of the Hasura-level permission gap on `requests.select_permissions`. The indexer's own visibility check is a second, independent enforcement point that happens to close the gap for the search surface specifically, even though the underlying Hasura permission itself remains a real defect (BUG-002) that must still be fixed at the API layer (a direct/hand-crafted GraphQL query could still read a private project's requests through Hasura itself — the indexer's correctness doesn't fix that).
- **No frontend code path was found that queries `Request` independently of its parent `Project`** — every request read observed in this archaeology goes through `projects_by_pk.requests` (nested), which is naturally scoped because `projects_by_pk` itself returns nothing for a private project a non-owner queries (see `docs/rewrite/open-questions.md`, "Does `/projects/{id}`'s SSR enforce `visibility`..."). So BUG-002's permission gap, while real and worth fixing defensively, was never actually exploited through any code path the historical product itself exercised.

**Conclusion for Nusszopf 2**: the target invariant ("a user cannot discover content through search that they are not allowed to discover through the application") was **historically true in practice**, enforced correctly at indexing time even though the underlying `Request` read-permission was too broad. Nusszopf 2's Scout integration must reproduce the same indexing-time gate (only sync a `Project`/`Request` to the index when the project is public) — see `docs/architecture/README.md`'s Search section, which already specifies this — and should also apply a defense-in-depth query-time scope, since Scout drivers/queries in Laravel are easier to accidentally call without the indexing-time gate than the historical bespoke webhook was.

## Indexing triggers — update and delete semantics

- Update-triggering columns are a deliberate subset for each entity (see above) — e.g. `Project.contact`, `.title`, etc. are watched, but `.user_id`/`.id`/`.created_at` are not (an update touching only unwatched columns does not re-fire the sync). This is Confirmed from the `update.columns` lists and is a meaningful optimization/behavior to preserve: not every project update requires reindexing, only ones touching search-relevant or visibility-relevant fields.
- Delete fully removes the row's `columns: '*'` in the trigger payload, consistent with instructing the webhook to delete the corresponding search document.
- There is no explicit "reindex everything" batch job, cron trigger, or management command anywhere in this repository (`cron_triggers.yaml` is empty) — Confirmed absence. A full reindex, if ever performed historically, must have been a manual/ad-hoc operation, not an automated one. The rewrite should provide a proper reindex command (this is an improvement opportunity, not a fidelity requirement, since there's no historical UX to match here).

## Failure behavior

- Confirmed: Hasura retries a failed webhook delivery 3 times over ~30 seconds, then gives up. There is no visible mechanism for detecting or recovering from a permanently-failed sync (e.g. Meilisearch down for longer than the retry window) — an entity could silently drift out of sync with the search index indefinitely. This is worth explicit improvement in the rewrite (e.g. a durable queue/job with longer backoff), which the project rules permit since it is a genuine infrastructure robustness gap, not a product-behavior change.

## What the rewrite must still determine before implementation

1. ~~Whether `Project` and `Request` were indexed as one combined Meilisearch index or two, and under what document shape~~ — **Resolved** (pre-implementation review pass, 2026-09-18): one combined index (`items`), confirmed directly from `search.function.js`. Every document — whether representing a `Project` (when it has zero requests) or a `Request` (when a project has one or more) — carries `itemsId` (the row's own id, Meilisearch's primary key), `groupId` (always the parent project's id, used by the frontend's `groupBy` to nest request hits under their project card), and `req_type` (`REQUEST_CATEGORY.none` for a bare project document, the request's real category otherwise). A project with requests has **no standalone project document** — `_updateProjectAndRequests` deletes the placeholder project document once real request documents exist for it (`index.deleteDocuments([projectCrop.id])`), so the "project" is represented purely through its request documents (which each carry the full set of `pro_*` fields alongside their own `req_*` fields) once it has at least one request.
2. ~~How visibility filtering was actually enforced at query time~~ — **Resolved**, see the "Authorization filtering at search time" section above: enforced at indexing time, not query time, in `search.function.js`.
3. The actual ranking rules, filterable/sortable attributes actually configured in production beyond the one `prod_setup.md` note — still Unknown; not resolved by this pass (out of scope, no further evidence available in-repo).
4. The real value sets for `Project` fields like `descriptionTemplate`/`location`/`period` as they appear in search results — **Partially resolved** by this pass: `search.function.js`'s `_parseProjectToDocument` confirms the indexed (not necessarily rendered) shape — `pro_location_text` is `location.searchTerm` (empty string if remote), `pro_period_from`/`pro_period_to` are Unix-ms timestamps derived from `period.from`/`period.to` (empty string if flexible), `pro_location_geo` is the raw `location.data.geo` object (empty object if remote). Exact search-result-card rendering of these fields is still Unknown pending frontend `HitCard` internals (not opened in this pass).

Do not resolve any of the above by invention; escalate as open questions if the frontend archaeology also cannot resolve them.

## Second slice — what Nusszopf 2 indexes for a project

`Project::toSearchableArray()` now carries the historical `_parseProjectToDocument` field set
(`search.function.js`, **Confirmed**): `title`, `goal`, `description` (plain text), `team` (plain
text), `motto`, `author` (the owner's name), `location_text` (the place text, empty when remote),
`location_remote`, `location_geo` (`{lat, lon}`, empty when remote), `period_flexible`,
`period_from`/`period_to` (Unix timestamps, null when flexible) and `updated_at`. Field names are
unprefixed (the `pro_` prefix existed to share an index with requests). The searchable attributes
are `title, goal, description, location_text, team, motto, author`
(`config/scout.php`; run `php artisan scout:sync-index-settings` after upgrading).

Synchronization stays the model-save path of the first slice: every wizard/edit write is an Eloquent
save observed by Scout (queued via `SCOUT_QUEUE`), so a changed searchable field re-indexes, a
private → public save indexes, a public → private save removes, and deleting a project from the edit
screen's settings view removes it. The historical "only reindex when a watched column changed"
optimisation is not reproduced (an unchanged form saves nothing, so no spurious reindex happens
from the edit screen); regression tests are in `tests/Feature/Search/ProjectSearchSyncTest.php`.

## Third slice — requests in the index

`Project` and `ProjectRequest` share **one index, `items`** (`Project::SEARCH_INDEX`, prefixed by
`SCOUT_PREFIX`), as the historical indexer did (**Confirmed**, `search.function.js`). Each document is
keyed by its own id and carries `group_id` (the project's id) and `req_type`:

- a **project with requests** has no document of its own; each **request** has one — the project's
  fields (as above, unprefixed) plus `req_title`, `req_description`, `req_type` (its category, one of
  `companions | rooms | materials | financials | others`) and `group_id`; its `updated_at` is the
  project's (`_parseRequestToDocument`);
- a **project without requests** has a project document with `req_type: "none"` and `group_id` its own id
  (`_addProjectOrRequests` / `_updateProjectAndRequests`).

Only **public** projects are indexed, requests included: a request's `shouldBeSearchable()` is "its project
is public", the project's is "public and no requests". Synchronization is still the model-save path, extended
by two hooks: a save of a `Project` re-syncs all its requests' documents (they follow its visibility and
carry its fields and `updated_at`) and its deletion removes them first (the database cascade raises no
model events); a save or delete of a request of a **public** project touches the project's `updated_at`
(historically `_syncProject`), which re-syncs the project. Turning a project private removes it and all its
requests; turning it public again restores them. Deleting the last request brings the project's document back.

The historical open question "what happens to requests when a project turns private" is **Resolved,
Confirmed** by `_upsertProject`'s second branch (they are removed) and reproduced.

The search page now reads the index's `group_id`s and lists the *visible* projects in relevance order, once
each — a project found only through a request's text appears, a stale document of a private or deleted
project does not (`Project::visible()` on hydration, BUG-002 defence in depth). The grouped hit card with its
matching requests, the category filter (`req_type` becomes filterable then) and paging are the
search-completion slice's. A page is at most Meilisearch's default 20 *documents*, so a project with many
requests takes several of them until that slice's paging exists.

**Upgrade**: `php artisan scout:sync-index-settings`, then re-import both models —
`php artisan scout:import "App\Models\Project"` and `php artisan scout:import "App\Models\ProjectRequest"`
(the previous `projects` index is no longer used and can be deleted). Test data left behind in a shared
development index is why the real-Meilisearch tests use a per-run search word.
