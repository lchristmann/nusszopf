# Changelog

All notable changes to Nusszopf are documented here, in the
[Keep a Changelog](https://keepachangelog.com/) format. Nusszopf follows semantic versioning with
unprefixed tags; it stays in `0.x` until the rewrite reaches parity with the historical product
(`docs/release/versioning.md`). Entries that need operator action are tagged **Breaking:** or
**Migration required:**.

## [Unreleased]

### Added

- The historical four-step project creation wizard (`/user/project/create?step=N`) and the three-view
  project edit screen (Beschreibung / Gesuche / Einstellungen), replacing the first slice's temporary
  single form (`docs/rewrite/second-slice.md`).
- Project location (place search) and period, team, motto, contact and visibility fields; the project
  detail page now shows location (linked to OpenStreetMap), period, formatted description, team, motto,
  contact and share actions.
- A rich-text editor with the historical six-tool toolbar (bold, italic, underline, bulleted list,
  numbered list, link).
- Project deletion from the edit screen's settings view.
- Icon-based navigation header, radio/checkbox controls, popover field help, toast stacking/animation.
- **Migration required:** set `LOCATIONIQ_KEY` (a [LocationIQ](https://locationiq.com) API key) to let
  authors pick a fixed project location; without it only location-independent projects can be created
  (`docs/deployment/README.md`).
- **Migration required:** run `php artisan scout:sync-index-settings` once after upgrading — projects are
  now also searchable by location, team, motto and author.

- Project requests (Gesuche), historically faithful (`docs/rewrite/third-slice.md`): the wizard's step 3
  and the edit screen's "Gesuche" view create, edit and delete requests through the request dialog
  (title, category, rich-text description) with the historical copy, category colors, context menu, toasts
  and confirmations; the project detail page lists them as cards and opens each in its dialog; a request is
  deleted with its project.
- Requests are searchable: they are indexed into the shared `items` index (a project with requests is found
  through them), only while their project is public.
- **Migration required:** run `php artisan migrate` (new `project_requests` table), then
  `php artisan scout:sync-index-settings` and re-import both searchable models —
  `php artisan scout:import "App\Models\Project"` and `php artisan scout:import "App\Models\ProjectRequest"`.
  The search index is now named `items` (previously `projects`, which can be deleted).

### Changed

- New projects default to **public** visibility on the wizard's last step (the historical wizard default);
  the first slice's form defaulted to private.
- The project `description`/`team` are stored as structured documents (`description_template`,
  `team_template`) with their plain text alongside; projects created by the first slice are read
  transparently and need no data migration.

### Fixed

- Building the production image from a working copy no longer copies the host's `.env`, `vendor` and cached
  framework files into it (`.dockerignore`).
- Creating a project together with its requests is atomic: if a request cannot be written, no project is
  left behind (BUG-027).
- Period validation no longer blocks a flexible period because of stale dates (BUG-022).
- Project dates are shown as the calendar date the author chose, in every time zone (BUG-023).
- The rich-text list buttons announce their real function (BUG-024).
- Two copy typos ("Peronen", "gepeichert") (BUG-025).
- A title, goal or request title made only of spaces is rejected (BUG-026).

### Security

- A request under a private project is no longer readable by anyone but the project's owner: request
  visibility always inherits from its project on every read path (BUG-002; historically the API let any
  caller read any request).
- Rich-text documents and selected places are re-validated on the server against a whitelist before
  they are stored or rendered; links are forced to `https://` and every string is escaped.
