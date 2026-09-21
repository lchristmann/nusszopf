# Third Vertical Slice — Project Requests (Gesuche)

Status: **implemented and verified** (2026-09-21). This slice replaced the two scaffolds of the second
slice — the wizard's step 3 and the edit screen's "Gesuche" view — with the historical request
workflow, gave the project detail page its request cards and dialog, and indexes requests for search.
It is the specification of what was built and why; the historical evidence is cited inline. Scope and
sequencing come from `docs/rewrite/master-roadmap.md`, "Slice 3".

**In scope**: the `ProjectRequest` model, table, factory and policy; the create/edit dialog; the
wizard's step 3; the edit screen's "Gesuche" view with its context menu; the detail page's cards and
view dialog; deletion with the project; BUG-002; indexing of requests; tests; browser coverage.

**Out of scope (unchanged)**: the search screen's grouped hits, category filter and paging (slice 4);
the server-sent contact e-mail (slice 6 — "Kontaktieren" stays the `mailto:` scaffold of the second
slice, in the request dialog too); `ProjectAnalytics`, the report link, the My Projects card's request
preview (slice 5, `PreviewRequestCard`); the home carousel's request cards (`CarouselRequestCard`); the
search hit's `HitRequestCard` (slice 4).

## Historical mechanics (Confirmed against `web-nusszopf` and `be-nusszopf`, read in full)

Sources: `containers/user/{CreateProjectSteps/RequestsStep,EditProjectViews/RequestsView,EditRequestDialog,RequestForm/*}.js`,
`containers/projects/RequestDialog/*`, `components/RequestCard/*`, `ui-library/stories/organisms/{Dialog,Menu}`,
`assets/data/{request-form,request-card,request-dialog,edit-request-dialog,create-project,edit-projects-views,project}.data.js`,
`assets/icons/Request.js`, `utils/services/projects.service.js`, `utils/hasura/{fragments,mutations,queries}`,
`utils/functions/search.function.js`, `pages/projects/[id].js`, `e2e/cypress/integration/_projects.spec.js`,
`hasura/migrations/*` and `tables.yaml`.

### The request

| Field | Historical | Nusszopf 2 |
|---|---|---|
| `title` | text, form: required, `max(40)`, input `maxLength` 30 | `varchar(40)`; input `maxlength` 30, rule `max:40` — both kept (BUG-028) |
| `category` | free text; select `companions / rooms / materials / financials / others`, placeholder `-` | `CHECK` on the five values (as BUG-007 did for `visibility`); the placeholder is the empty value |
| `descriptionTemplate` / `description` | rich-text document (Slate) / its plain-text projection, ≤ 6000 characters serialized, not a lone empty paragraph | `description_template` (ProseMirror, `App\Support\RichText`) / `description`; `RichTextRule` |
| `created_at` | `timestamptz default now()`, shown as `toLocaleDateString('de-DE')` in the viewer's zone | `created_at`, shown as `j.n.Y` in the application's time zone (**Replace**: the server cannot know the viewer's zone) |
| `updated_at` | trigger-maintained | `updated_at` |
| `project_id` | FK, `ON DELETE CASCADE` | FK, cascade |

Categories (`REQUEST_CATEGORY` in `enums.js` also has `none`, "nothing chosen"; it is not a value a
request can have — it only tags a bare project document in the index). Labels: Mitstreiter:innen,
Räume, Materialien, Finanzielles, Sonstiges. Colors (`RequestCard.theme.js`, `RequestDialog.theme.js`,
`Menu.theme.js`, `Link.theme.js`): red, yellow, turquoise, blue, pink respectively — card
`*-200` with a border, dialog `*-200`, dialog button `*-300`, links `nz-link-stone-*`.

Validation copy (`request-form.data.js`): "Gib einen Titel ein", "Maximal 40 Zeichen", "Wähle eine
Kategorie aus", "Gib eine Beschreibung ein", "Maximale Zeichenlänge erreicht". As in every historical
form an error shows only after the field lost focus or a failed submit.

### The dialog (`EditRequestDialog`)

A stone-colored `Dialog` (`bg-stone-200`): full-screen on phones, a `max-w-xl` rounded card from `sm`
up. Titel* (info "Wie soll das Gesuch heißen?", placeholder "Wer oder was wird gesucht?"), Kategorie*
(a `Select` whose background follows the chosen category, `bg-stone-400` until one is chosen),
Beschreibung* (the stone `RichTextEditor`), and "Erstellen"/"Speichern" + "Abbrechen". **It has no
overlay-click or Escape dismissal** (`onDismiss={undefined}`): only the X and "Abbrechen", which ask the
native `confirm()` ("Willst Du wirklich abbrechen? Dein Gesuch wird nicht gespeichert." / "…Deine
Änderung wird nicht gespeichert.") when the form is dirty. Submitting an unchanged edit just closes it.

### Step 3 of the wizard (`RequestsStep`)

Left: "Projektgesuche", the intro, and "Gesuch erstellen" (plus-circle, `bg-stone-300`). Right: with
requests, "Erstellte Gesuche" and the `edit` cards in creation order; without, the info card "Gesuche
für das Projekt kannst Du entweder jetzt oder später erstellen." Requests live in the wizard's single
form state (`requests: []`); nothing is written until the last step. Delete needs no confirmation here.
Zero requests is valid, the step has no schema. On the last step the project is inserted and then its
requests (BUG-027: now one transaction); the request's `created_at` is the database's, not the
dialog's.

### The edit screen's "Gesuche" view (`RequestsView`)

Left: the same intro and "Gesuche erstellen"; right: "Aktuelle Gesuche" and the `edit` cards, **newest
first** (`requests(order_by: {created_at: desc})`), or the info card "Alles zopfig! Derzeit gibt es
keine Gesuche." Each write is immediate, with its own toasts — create: "Gesuch erstellen..." →
"Gesuch wurde erstellt."; update: "Änderungen speichern..." → "Gesuch wurde aktualisiert."; delete: the
native `confirm()` "Möchtest Du das Gesuch wirklich löschen?", then "Wird gelöscht..." → "Gesuch wurde
gelöscht."; errors "Sorry, das Gesuch konnte nicht erstellt/aktualisiert/gelöscht werden." The dialog
closes after every write, successful or not. The `edit` card is a button opening the dialog plus a
`MoreHorizontal` menu (`data-test` `menu_edit-request-card`, items `menuitem-0` Bearbeiten and
`menuitem-1` Löschen) in the category's `Menu.theme.js` color.

### The detail page (`pages/projects/[id].js`)

"Projektgesuche" and the `view` cards (a whole-card button, category color, `Request` icon, title,
"Erstellt am …", chevron), newest first, or the info card. A card opens the `RequestDialog`
(category-colored, icon + title, "Erstellt am …", the rich text with `stone-*` links,
"Kontaktieren"/"Schließen"; Escape and the overlay dismiss it). "Kontaktieren" is `mailto:` for a
personal contact and the contact form for "Über Nusszopf" — until slice 6 it is the `mailto:` of the
project's contact for both, as on the project's own button.

### Search (`search.function.js`, `docs/search/README.md`)

One index (`items`) holds a document per request; a project **with** requests has no document of its
own, a project **without** one has a project document (`req_type: none`). Every document carries
`group_id` (the project's id) and the project's own fields; a request's document adds `req_title`,
`req_description` and `req_type` (its category) and takes the project's `updated_at`. Only public
projects are indexed; turning a project private removes it and all its requests; creating, editing or
deleting a request of a **public** project bumps that project's `updated_at`
(`_syncProject` → `apiUpdateProject`), which is also what the detail page's "Aktualisiert am" shows.
Nusszopf 2 does all of this from the model events Scout already observes (below).

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | BUG-002 — request visibility inherits from the project | **Fix** (implemented) | `ProjectRequestPolicy`, `ProjectRequest::visible()`; see `bugs.md`, `intentional-changes.md` |
| 2 | BUG-027 — project and requests created atomically | **Fix** (implemented) | one transaction |
| 3 | BUG-028 — title field 30 / rule 40 | **Preserve** | both kept |
| 4 | BUG-026 extended to the request title | **Fix** | whitespace-only is empty |
| 5 | BUG-013 — native `confirm()` for deleting a request and for cancelling a dirty dialog | **Preserve** | as everywhere |
| 6 | `category` `CHECK` constraint | **Replace/hardening** | as BUG-007; the value set is unchanged |
| 7 | Dates shown as `j.n.Y` in the application's time zone | **Replace** | historically the viewer's zone |
| 8 | A request write bumps only a *public* project's `updated_at` | **Preserve** | `ProjectRequest::booted()` |
| 9 | "Kontaktieren" in the request dialog is the `mailto:` scaffold | **Deferred** | slice 6 replaces both buttons |
| 10 | The search page still shows one project card per hit group | **Done in slice 4** | nesting, filters and paging: `fourth-slice.md` |
| 11 | A wizard request is edited/deleted by list index, an edit-screen request by id, resolved through the project | Implementation | see Authorization |

## Implementation

- **Model** `App\Models\ProjectRequest` (`project_requests`: uuid, `project_id`, `title`, `category`,
  `description`, `description_template`, timestamps; index `(project_id, created_at)`); `Project::requests()`;
  `ProjectRequestFactory`. Requests are ordered `created_at desc, id desc` (UUIDv7 breaks ties).
- **Authorization** `ProjectRequestPolicy` (auto-discovered): `view` ⇄ `ProjectRequest::visible()`,
  both delegating to `Project::scopeVisible()`; `create(user, project)`, `update`, `delete` are the
  project owner's. The Livewire components resolve a request only through
  `$project->requests()->find()` and authorize before writing; a foreign, malformed or unknown id is
  "not found" (a 404 on a write), never another project's row. The edit screen keeps BUG-021's 404 for
  every non-owner; the detail page keeps its 404 for a project the viewer may not see.
- **Dialog state** `App\Livewire\Concerns\ManagesRequestDialog` (the wizard and the edit screen share
  it, as they share `ManagesProjectFields`): three properties, blur-and-submit validation, an
  `unchanged` short-circuit; the host supplies `requestFormValues()` and `storeRequest()`. The dialog is
  a form of its own, rendered outside the wizard's form.
- **Components** (`resources/views/components/`): `dialog` (Dialog organism; focus trap and scroll lock
  through `x-trap.noscroll`), `menu` (Menu organism), `request-card` (`edit`/`view`),
  `request-edit-dialog`, `request-view-dialog`; `rich-text-editor` gained the `stone` color and a blur
  action, `select` a bindable wrapper class; icons `request`, `more-horizontal`, `chevron-right`.
- **Detail page**: the cards and their dialogs are pre-rendered and shown by an Alpine `openRequest`
  state (no round trip, as historically); they are public information of an already visible project and
  are read through `ProjectRequest::visible()` regardless.
- **Search**: `Project` and `ProjectRequest` share the `items` index (`Project::SEARCH_INDEX`);
  `Project::shouldBeSearchable()` is "public and no requests"; `ProjectRequest::shouldBeSearchable()` is
  "its project is public". `Project::booted()` re-syncs a project's request documents on every save
  (visibility, refreshed fields, `updated_at`) and removes them on `deleting`; `ProjectRequest::booted()`
  touches a public project on save/delete. The search page reads the index's `group_id`s and lists the
  visible projects in relevance order, once each.
- **Operations**: run `php artisan scout:sync-index-settings` after upgrading (the index is now `items`
  and its searchable attributes include `req_title`, `req_description`) and re-import both models —
  `php artisan scout:import "App\Models\Project"` and `"App\Models\ProjectRequest"`.

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Projects/ProjectRequestModelTest.php` | relationship, cascade (project and owner), category `CHECK`, categories/labels, the public-project touch (and none for a private one) |
| Feature | `tests/Feature/Projects/ProjectRequestAuthorizationTest.php` | every ability allow/deny, the `visible()` scope for guest/stranger/owner, following the project's visibility (BUG-002) |
| Livewire | `tests/Feature/Projects/ProjectRequestWizardTest.php` | step 3 states, the dialog, create/edit/delete in the list, validation copy, zero requests, back/forth, persistence in order, private projects, atomicity (BUG-027), tampering |
| Livewire | `tests/Feature/Projects/ProjectRequestEditTest.php` | empty state, order, create/edit/delete with toasts, unchanged save, cascade with the project, non-owner, forged/foreign/malformed ids |
| Feature | `tests/Feature/Projects/ProjectRequestDetailTest.php` | empty state, cards in order and color, dialog content, escaping, private → 404 without leaks, turning private |
| Search (real Meilisearch) | `tests/Feature/Search/ProjectRequestSearchTest.php` | document shape, replacing the project document, delete/edit sync, private ⇄ public, deletion, never indexed for a private project, search page grouping, stale documents |
| E2E | `tests/E2E/specs/user/project-requests.spec.ts` | Journey 3 completed on Chromium, Firefox and WebKit; the phone layout |

## Remaining gaps

None inside the slice. Deliberate follow-ups: the search screen's nesting of matching requests and the
category filter (slice 4), the contact dialog (slice 6), the My Projects card's request preview
(slice 5).
