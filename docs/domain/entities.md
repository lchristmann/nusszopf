# Entities

Evidence base for this file: `../historical/be-nusszopf/hasura/migrations/*` (schema history, read in chronological order), `../historical/be-nusszopf/hasura/metadata/tables.yaml` (relationships, permissions, event triggers), `../historical/be-nusszopf/hasura/bugs/*` (a documented historical fix), `../historical/be-nusszopf/auth0/rules/*.js` (how rows are created from authentication events). Per the evidence rule order, backend schema/permissions evidence is treated as authoritative for structure; frontend evidence (a separate archaeology pass) is needed to confirm user-facing labels, enum-like text values, and exact form fields, and is cross-referenced under "User-facing terminology" / "Open cross-reference" where still missing.

The historical domain is deliberately small: five tables plus one view. There is no CMS, no admin entity, no notification/message entity, and no payment entity anywhere in the backend evidence.

---

### `Lead`

- Status: Confirmed (table), Inferred (product meaning)
- Historical table: `public.leads`
- Purpose: Inferred — a newsletter/contact "lead", i.e. an email address that has expressed interest (via the public newsletter subscribe form or a similar public flow) and gone through email double opt-in. Confirmed by column shape (`email` unique, `hasConfirmed` boolean) and by the existence of `../historical/emails-nusszopf/src/sendgrid/newsletter/{subscribe,welcome,unsubscribe}.mjml`. Not confirmed to be the newsletter mechanism itself — that requires cross-referencing the frontend `/api/newsletter` route (see Open cross-reference below).
- Identifier: `id` — `uuid`, server-generated (`gen_random_uuid()` since migration `1596830284930_alter_table_public_leads_alter_column_id`).
- Fields:
  - `name` — `text`, nullable. Present from the initial migration (`1596229707412_init`).
  - `email` — `text`, `NOT NULL`, `UNIQUE`. Added in `1596263436768_alter_public_leads`.
  - `hasConfirmed` — `boolean`, `NOT NULL DEFAULT false`. Added in the same migration; tracks double opt-in confirmation.
  - `created_at` — `timestamptz`, `NOT NULL DEFAULT now()`.
  - `privacy` — `boolean`, `NOT NULL DEFAULT false`. Added in `1598252746307_alter_table_public_leads_add_column_privacy`. Inferred meaning: a privacy-policy consent checkbox recorded at submission time (GDPR-style consent), consistent with a `.de`/EU-facing FOSS project.
  - A `test` column was added and dropped again within the same migration file (`1596263436768`) — a discarded experiment, not part of the schema. Not carried forward.
- Required fields at insert (per `user` role insert permission): `id, name, email, hasConfirmed, created_at, privacy`.
- Defaults: `id` auto-generated; `hasConfirmed = false`; `created_at = now()`; `privacy = false`.
- Relationships: `Lead.user` — object relationship to `User` via matching `email` (manual, not a real FK). See `docs/domain/relationships.md`.
- Lifecycle: created unconfirmed → confirmed (`hasConfirmed` flips to `true`) via some external confirmation flow. No soft-delete column; deletion is a hard delete.
- Validation: only DB-level (`NOT NULL`, `UNIQUE` on email). No format validation for email at the database layer — Inferred that this happens client-side.
- Permissions: role `user` can insert (with ownership check `user.id = X-Hasura-User-Id`, i.e. a Lead row can only be inserted while impersonating the linked user through the manual `email` relationship — see `docs/domain/permissions.md` for the caveat this implies), select, update (`created_at`, `hasConfirmed` only), and delete, all filtered to rows whose linked `user.id` matches the caller. **No `anonymous` role has any permission on `leads` at all** — Confirmed from `tables.yaml`; a logged-out visitor cannot submit a lead directly through Hasura, which strongly implies leads are created through a backend/serverless route (see below), not directly from the browser against Hasura.
- Side effects: `event_triggers.sync_leads_sendgrid` fires on `delete` (all columns) and on `update` of `hasConfirmed`, POSTing to `LEADS_TRIGGER_URL` with a shared `secret` header from `EVENT_SECRET`. Inferred purpose: keep a SendGrid contact list in sync (add on confirm, remove on delete).
- Search/indexing: none. `leads` has no search event trigger.
- User-facing terminology: Unknown — needs the web-nusszopf newsletter/contact form archaeology to confirm labels ("Newsletter", "Kontakt", etc.).
- Open cross-reference (for the frontend/API archaeology pass): the Auth0 `syncWithHasura` rule (`../historical/be-nusszopf/auth0/rules/syncWithHasura.js`) calls `POST {webapp}/api/newsletter` with `{ token, action: "auth0SyncHasura" }` when a newly-synced user has `user_metadata.newsletter === "true"`. Since `anonymous`/`user` roles cannot insert into `leads` via Hasura directly, this `/api/newsletter` route (or an equivalent) is almost certainly the actual place `leads` rows get created, using the Hasura **admin secret** server-side. This route lives in `web-nusszopf` (Next.js) and was out of scope for this backend-only pass.
- Evidence: `hasura/migrations/1596229707412_init/up.sql`, `1596263436768_alter_public_leads/up.sql`, `1596830284930_.../up.sql`, `1598252746307_.../up.sql`; `hasura/metadata/tables.yaml` (leads block); `auth0/rules/syncWithHasura.js`.

---

### `User`

- Status: Confirmed
- Historical table: `public.users` (originally created as `public.user`, singular, then renamed to `users` inside migration `1603383923965_fix-lead-user-relationship`).
- Purpose: Confirmed — the authenticated account record mirrored into Postgres/Hasura from an Auth0 identity. Auth0 remains the actual identity/credential store; this table is a synced projection used for authorization (`X-Hasura-User-Id`) and for anything that needs to be queried/joined in GraphQL (own projects, etc.).
- Identifier: `id` — `text`, **not** a UUID. Confirmed to hold the raw Auth0 `user_id` (e.g. `auth0|...`, `google-oauth2|...`), because `auth0/rules/syncWithHasura.js` explicitly normalizes it (`/.+\|.+/g.test(user.user_id) ? user.user_id : `auth0|${user.user_id}``) before inserting.
- Fields:
  - `email` — `text`, `NOT NULL`, `UNIQUE`. Present from creation (`1603373593111_create_table_public_user`).
  - `name` — `text`, nullable. Added in `1609324051844`. Populated from Auth0 `username`/`name`/`nickname`/email-local-part fallback chain (see `syncWithHasura.js`).
  - `picture` — `text`, nullable. Added in `1611486746973`. Populated only for social-login accounts (`userPicture.js` rule triggers only when the Auth0 `user_id` contains `google` or `apple`).
- Defaults: none beyond column defaults; there is no `created_at`/`updated_at` on `users` itself (unlike `projects`/`requests`) — Confirmed absence, not an oversight visible elsewhere in evidence, but worth flagging: there is no way to know from this table alone when an account was created. See `docs/rewrite/open-questions.md`.
- Relationships: `User.lead` (object, via matching `email`), `User.private` (object, 1:1, via `users_private` view on `id`), `User.projects` (array, via `projects.user_id`). See `docs/domain/relationships.md`.
- Lifecycle: row is upserted (`on_conflict: constraint user_pkey, update_columns: []` — i.e. insert-or-ignore, never overwrites an existing row) the first time a user authenticates and `app_metadata.synced_with_hasura` is not yet set on the Auth0 side. Deletion is a hard delete, and deleting a `users` row cascades to delete all of that user's `projects` (`ON DELETE CASCADE`, confirmed in `1606046495095_refactor_cascade_deletes`), which in turn cascades to `requests` and `projects_analytics` for those projects.
- Validation: DB-level uniqueness/NOT NULL only.
- Permissions: role `user` may insert only the `picture` column for their own `id`; may select only `name` and `picture` (**not `email`** — email is deliberately excluded from the base table's select permission for every role, including `user` on their own row); may update only `picture` for their own row; may delete their own row. Role `anonymous` may select `name` and `picture` for **any** row (no filter), i.e. the historical product allowed public read of any user's name/picture, but never their email through this table.
- The `users_private` view exists specifically to expose `email`/`id` back to the owning user only (`select_permissions` role `user`, filter `id = X-Hasura-User-Id`) — a deliberate email-hiding pattern layered on top of the base table.
- Side effects: `event_triggers.clean_up_deleted_user` (on delete, any column) and `clean_up_users_digitalocean` (on update of `picture`) both POST to `USERS_TRIGGER_URL`. Inferred purpose: deleting a user cleans up their Auth0 identity and/or stored assets; updating `picture` deletes the previous picture asset from object storage (DigitalOcean Spaces, per `be-nusszopf` README's hosting section). Not directly confirmed — the webhook target code lives outside this repository.
- Search/indexing: none — `users` has no search event trigger; users are never indexed/searchable.
- User-facing terminology: Unknown — needs frontend cross-reference (likely "Profil"/"Account").
- Evidence: `hasura/migrations/1603373593111_.../up.sql` through `1611486746973_.../up.sql`; `1603383923965_fix-lead-user-relationship/up.sql` (rename); `1611780506064_run_sql_migration/up.sql` (view); `hasura/metadata/tables.yaml` (users, users_private blocks); `auth0/rules/{hasuraIdToken,syncWithHasura,userPicture}.js`.

---

### `Project`

- Status: Confirmed (schema), Inferred (product concept)
- Historical table: `public.projects`.
- Purpose: Inferred from field names — the central content entity of Nusszopf. Given the name "Nusszopf" (a project connecting people, and the domain vocabulary of `goal`, `team`, `location`, `period`, `motto`, and child `requests`), a `Project` is almost certainly a user-run social/community/volunteer initiative that can be published publicly and that other people can respond to or support. This high-level product interpretation is Inferred, not Confirmed, and must be validated against the frontend/E2E archaeology before being treated as settled.
- Identifier: `id` — created as `uuid` with `DEFAULT gen_random_uuid()`, then **altered to `text`** later in the same migration file `1606040421666_feature__projects` (`ALTER TABLE "public"."projects" ALTER COLUMN "id" TYPE text`). Confirmed the column is `text` in the final state, still UUID-shaped in practice since nothing changed how values are generated for existing rows; the type change was made to let `requests.project_id` (text) reference it cleanly.
- Fields:
  - `title` — `text`, `NOT NULL`.
  - `goal` — `text`, `NOT NULL`. Inferred: the project's fundraising/support goal or mission statement.
  - `description` — `text`, `NOT NULL`. Plain-text/rendered description.
  - `descriptionTemplate` — `jsonb`, `NOT NULL`. Inferred: the structured/rich-text-editor document backing `description` (a "template" pairing of rendered text + editor state is a common pattern; also seen on `Request`). Exact shape Unknown without frontend evidence.
  - `location` — `jsonb`, `NOT NULL`. Structured location data (Unknown shape — likely address/coordinates for a map).
  - `period` — `jsonb`, `NOT NULL`. Structured time period (start/end dates, Unknown exact shape).
  - `team` — `text`, nullable.
  - `teamTemplate` — `jsonb`, nullable. Same rendered/structured pairing pattern as `description`/`descriptionTemplate`.
  - `motto` — `text`, nullable.
  - `visibility` — `text`, `NOT NULL DEFAULT 'private'`. Confirmed values in use: `'private'` and `'public'` (from the `select_permissions` filter `visibility: { _eq: public }`). Stored as free text, not a Postgres enum/check constraint — Confirmed there is no CHECK constraint restricting the value set, so nothing at the DB layer prevents a third value.
  - `contact` — `text`, `NOT NULL DEFAULT 'mail@nusszopf.org'`. Inferred: a contact email/address shown on the public project page, defaulting to the site's own mailbox if the creator leaves it blank.
  - `user_id` — `text`, `NOT NULL`, FK to `users.id`.
  - `created_at` — `timestamptz`, `NOT NULL DEFAULT now()`. Added in `1606040421666`.
  - `updated_at` — `timestamptz`, nullable, `DEFAULT now()`, maintained by trigger `set_public_projects_updated_at` (added in `1612459316264_feature_updated_at`).
  - `views` — `numeric`, `NOT NULL DEFAULT 0`, with `CHECK (views <= 10000)` (added in two migrations, `1615634953094` / `1615653786668`). **Later dropped** (`ALTER TABLE "public"."projects" DROP COLUMN "views" CASCADE` inside `1615727014588_feature_visitor_counter`) once view-counting moved to the new `ProjectAnalytics` table. This is schema churn, not a currently-live column — do not carry it forward.
- Defaults: `visibility = 'private'`, `contact = 'mail@nusszopf.org'`, `created_at/updated_at = now()`.
- Relationships: `Project.user` (owner, many:1), `Project.analytics` (1:1 with `ProjectAnalytics`), `Project.requests` (1:many with `Request`). See `docs/domain/relationships.md`.
- Lifecycle: created private by default; can presumably be switched to `public` by its owner (mechanism/UI Unknown from backend alone — no state-machine enforcement exists at the DB level, any value change is allowed by the `update_permissions` for the owner). Deleting the owning user cascades to delete the project; deleting the project cascades to delete its `requests` and its `ProjectAnalytics` row.
- Validation: DB-level `NOT NULL` only; no CHECK constraint on `visibility`'s value set. No max-length constraints on `text` columns.
- Permissions: role `user` can insert a project only with `user_id = X-Hasura-User-Id` (cannot create on someone else's behalf); can select any project that is either `visibility = public` OR owned by them (`limit: 50`); can update/delete only their own project, and the update permission's allowed columns notably **exclude `user_id` and `visibility` is allowed but not separately gated**, i.e. an owner can toggle `visibility` freely through the same update permission used for editing content (no separate "publish" permission/workflow exists at the DB layer). Role `anonymous` may select only `visibility = public` projects (`limit: 50`) and cannot insert/update/delete at all.
- Side effects: `event_triggers.sync_projects_search` fires on insert, delete, and update of a specific column set that **includes `visibility`** — Confirmed this is how a project moving from private→public (or back) gets reflected in the search index, POSTing to `SEARCH_TRIGGER_URL`.
- Search/indexing: indexed via the `sync_projects_search` webhook into Meilisearch (index name/shape Unknown from this repo — see `docs/search/README.md`).
- User-facing terminology: Unknown — needs frontend cross-reference for the German/English product vocabulary actually shown for "goal", "team", "period", "visibility" toggle, etc.
- Evidence: `hasura/migrations/1606040421666_feature__projects/up.sql`, `1606046495095_refactor_cascade_deletes/up.sql`, `1612459316264_feature_updated_at/up.sql`, `1615634953094.../up.sql`, `1615653786668.../up.sql`, `1615727014588_feature_visitor_counter/up.sql`; `hasura/metadata/tables.yaml` (projects block).

---

### `Request`

- Status: Confirmed (schema), Inferred (product concept)
- Historical table: `public.requests`.
- Purpose: Inferred — a specific ask/need posted under a `Project` (e.g. "we need volunteers", "we need materials/donations"), given `category` and its scoping to a single `project_id`.
- Identifier: `id` — `uuid`, `DEFAULT gen_random_uuid()`.
- Fields:
  - `title` — `text`, `NOT NULL`.
  - `description` — `text`, `NOT NULL`.
  - `descriptionTemplate` — `jsonb`, `NOT NULL` — same rendered/structured pairing as on `Project`.
  - `category` — `text`, `NOT NULL`. Confirmed to be free text at the DB layer — **no enum/CHECK constraint defines the allowed category values**; the actual value set is Unknown without frontend evidence and must not be invented.
  - `project_id` — `text`, `NOT NULL`, FK to `projects.id` (`ON UPDATE/DELETE CASCADE`). Originally named `user_id` and pointed at `users` before being renamed/repointed at `projects` within `1606040421666_feature__projects` — an early-design correction, not a live ambiguity.
  - `created_at`, `updated_at` — same pattern as `Project` (added `1606040421666` / `1612459316264`; `updated_at` trigger-maintained).
- Relationships: `Request.project` (many:1, owning `Project`). No relationship back to `User` exists directly — a request's "owner" is only reachable by joining through its project.
- Lifecycle: hard delete; cascades from its parent project's deletion.
- Validation: DB-level `NOT NULL` only.
- Permissions: role `user` may insert a request only where `project.user_id = X-Hasura-User-Id` (i.e. only into your own project); may select **any** request regardless of the parent project's visibility (`filter: {}`, `limit: 250`) for both `anonymous` and `user` — Confirmed this is broader than `Project`'s own visibility filter: a request belonging to a *private* project is nonetheless selectable by anyone who has (or guesses/enumerates) its `id`, since there is no join back to `projects.visibility` in the `select_permissions` filter. This looks like a permission gap relative to `Project`'s own visibility model — see `docs/rewrite/open-questions.md`. May update/delete only where the parent project is owned by them.
- Side effects: `event_triggers.sync_requests_search` on insert/delete/update of `descriptionTemplate, category, description, title`, POSTing to `SEARCH_TRIGGER_URL`.
- Search/indexing: indexed into Meilisearch via the same webhook mechanism as `Project`.
- User-facing terminology: Unknown.
- Evidence: `hasura/migrations/1606040421666_feature__projects/up.sql`, `1612459316264_feature_updated_at/up.sql`; `hasura/metadata/tables.yaml` (requests block).

---

### `ProjectAnalytics`

- Status: Confirmed (schema)
- Historical table: `public.projects_analytics`.
- Purpose: Confirmed — per-project counters, replacing the earlier `projects.views` column.
- Identifier: `project_id` — `text`, `PRIMARY KEY` and `UNIQUE`, FK to `projects.id` (`ON UPDATE/DELETE CASCADE`). This is a 1:1 extension table, not an independent entity with its own id.
- Fields:
  - `views` — `numeric`, default `0`, `CHECK (views <= 1000000 AND views >= 0)`, later made nullable (`ALTER COLUMN views DROP NOT NULL`).
  - `contactRequests` — `numeric` (created as `contact_requests`, renamed to camelCase `contactRequests`), default `0`, `CHECK ("contactRequests" <= 1000000 AND "contactRequests" >= 0)`, later made nullable.
- Relationships: `ProjectAnalytics.project` is only reachable from the `Project` side (`Project.analytics`); no relationships of its own.
- Permissions — **notable finding, treat as a suspected historical defect, not a requirement to reproduce**: both `anonymous` and `user` roles can **insert and update** `views`/`contactRequests` for **any** `project_id`, with `check: {}` / `filter: {}` and no ownership condition whatsoever. There is no Hasura mechanism visible here that restricts writes to "increment only" — any caller (including an unauthenticated one) can set any project's `views` or `contactRequests` to an arbitrary value up to 1,000,000 by issuing a GraphQL mutation directly. This is documented as a suspected bug in `docs/rewrite/open-questions.md` and `docs/security/README.md`, not carried forward as intended behavior.
- Side effects: none of its own (no event triggers on this table).
- Search/indexing: none.
- Evidence: `hasura/migrations/1615727014588_feature_visitor_counter/up.sql`; `hasura/metadata/tables.yaml` (projects_analytics block).

---

### `users_private` (view, not an entity)

- Status: Confirmed
- Purpose: Confirmed — a narrow view (`SELECT id, email FROM users`) that exists solely to expose a user's own `email` back to them, since the base `users` table's select permissions deliberately never include `email` for any role. Not a domain entity in its own right; document as a permission-modeling pattern (see `docs/domain/permissions.md`) rather than a `User` sub-resource in the target implementation.
- Evidence: `hasura/migrations/1611780506064_run_sql_migration/up.sql`; `hasura/metadata/tables.yaml` (users_private block).

---

## Entities confirmed absent

To avoid inventing domain concepts (per project rules), the following are explicitly **not** present anywhere in `be-nusszopf`'s schema, metadata, or migrations, and must not be assumed to exist unless the frontend/E2E archaeology proves otherwise:

- No admin/staff/moderator role or entity — Hasura only ever defines `anonymous` and `user` roles; there is no third role anywhere in `tables.yaml`.
- No comments, likes, follows, or messaging entities between users.
- No payment/donation entity — `contactRequests`/`views` are counters only, not a transaction record. If the historical product supported real donations, it was handled entirely outside Hasura (e.g. an external donation link) — Unknown, needs frontend confirmation.
- No tags/categories table — `Request.category` is a bare text column, not a foreign key to a categories table.
- No notification entity.
- No custom Hasura Actions (`hasura/metadata/actions.yaml` is empty) and no remote schemas, scheduled/cron triggers, or SQL functions (`remote_schemas.yaml`, `functions.yaml`, `cron_triggers.yaml` are all empty `[]`). All non-CRUD behavior happens via **event trigger webhooks** to external services, not inside Hasura itself.
