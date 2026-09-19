# Second Vertical Slice — Project wizard and edit screen

Status: **implemented and verified** (2026-09-19). This slice replaced the first slice's
single-form scaffolding (`ProjectForm`) with the historical four-step creation wizard, the
historical three-view edit screen, the rich-text editor, place search and period handling, and
extended the project detail screen with the content those fields introduce. It is the
specification of what was built and why; the historical evidence it rests on is cited inline.

**In scope**: the wizard, the edit screen, all project fields, `?step=N`, validation, the rich-text
editor, location, period, team/motto/contact/visibility, owner/non-owner behavior, the project
detail content those fields drive, search synchronization, the icon/asset pipeline the screens need,
tests, browser coverage, the golden-master review.

**Out of scope (unchanged)**: `ProjectRequest`, newsletter (BUG-011 stays deferred), password reset,
Google login, avatar upload, account deletion, `ProjectAnalytics`/visitor counter, the project-report
link, the contact form dialog.

## Historical mechanics (Confirmed against `web-nusszopf`, read in full)

Sources: `pages/user/project/create.js`, `pages/user/project/[id]/edit.js`,
`containers/user/CreateProjectSteps/*`, `containers/user/EditProjectViews/*`,
`containers/user/ProjectForm/*`, `assets/data/{create-project,project-form,edit-projects-views,project}.data.js`,
`utils/services/{projects,location}.service.js`, `utils/helper.js`,
`ui-library/stories/organisms/{Stepper,RichTextEditor,Combobox}`, `molecules/{Progressbar,InfoCard}`,
`atoms/{Radiobox,Select,Checkbox,Button}`, `webapp/src/components/FieldTitle`.

### Steps

| # | Progress label | Left column | Right column | Validation |
|---|---|---|---|---|
| 0 | `Beschreibung 1/2` | title, goal, description (rich text) | location, period | title, goal, description, location, period |
| 1 | `Beschreibung 2/2` | team (rich text) | motto | team ≤ 6000, motto ≤ 200 |
| 2 | `Gesuche` | request intro + "Gesuch erstellen" | created requests / info card | none — zero requests is valid |
| 3 | `Einstellungen` | visibility | contact | none |

Progress is `(step + 1) / 4` of the bar (25/50/75/100 %). The header shows the title as typed
(`Neues Projekt` while empty). The last step's button reads `Erstellen`, every other `Weiter`;
`Zurück` appears from step 1 on.

### Navigation

- One form state for the whole wizard, no draft, no autosave. Leaving discards everything — no
  confirmation (the edit screen's view switch *does* confirm; that asymmetry is historical).
- The step is `?step=N`; browser back/forward move through steps (without validating).
- **Every entry starts at step 0.** `useStepper` pushes `?step=0` as soon as it mounts, whatever
  `?step=` said; a refresh therefore also returns to step 0. Values outside `0..3` or non-numeric
  are ignored by the stepper (the current step stays).
- **Forward is gated by the current step's schema; backward never validates.**
  *Correction to `first-slice.md`*: that document attributed the gate to `getNextStep`'s
  `requiredSchema`. Reading the code shows no step ever receives a `requiredSchema` prop (they get
  `validationSchema`), so `getNextStep` never blocks. The gate is Formik's own
  `validationSchema={stepper.currentChild.props.validationSchema}`: the *current step's* schema is
  what `submitForm` validates, and `onSubmit` only runs when it passes. The observable behavior
  (forward gated per step, backward free) is exactly as documented; only the mechanism differed.
- Formik shows a field's error only once the field is *touched* (blurred, or every field after a
  failed submit); touched is reset when a step is left.
- The last submit re-validates step 0 and 1 (`isValidSync`) and otherwise toasts
  "Bitte überprüfe deine Eingaben oder versuche es später erneut."

### Edit screen

`/user/project/{id}/edit`: a header with the saved title and a view `Select` (`Beschreibung`,
`Gesuche`, `Einstellungen`), each view a separately saved form.

- **Beschreibung** — every wizard step-0/1 field in one form (left: title, goal, description,
  motto; right: location, period, team), validated as a whole, saved with `Speichern`.
- **Einstellungen** — visibility + contact, `Speichern`, and *Projekt löschen* (native `confirm`,
  "Möchtest Du das Projekt wirklich löschen?", then My Projects).
- **Gesuche** — intentional scaffolding (below).
- Saving an unchanged form does nothing (`formik.dirty`); a view switch with unsaved edits asks
  "Möchtest Du die Seite wirklich verlassen? Deine Änderungen gehen dann verloren." and discards.
- Only the owner reaches it; everyone else — including for a public project — gets a 404 (BUG-021).

## Data

### Persistence

| Field | Column | Stored form |
|---|---|---|
| title / goal | `title` (≤40) / `goal` (≤150) | strings |
| description | `description_template` + `description` | ProseMirror JSON + plain-text projection |
| team | `team_template` + `team` | same |
| motto | `motto` (≤200) | string |
| location | `location` | `{ remote, searchTerm, data }`; remote clears `searchTerm`/`data` |
| period | `period` | `{ flexible, from, to }`; flexible clears both |
| visibility | `visibility` | `public`/`private` (wizard default **public**; DB default stays `private`) |
| contact | `contact` | the owner's e-mail when "Persönlich", else `mail@nusszopf.org` |

`location.data` is `{ key, postcode, city, countryCode, geo{lat,lon}, osm{id,type} }` — exactly the
LocationIQ mapping in `location.service.js` minus the display-only `value`. It is sanitized on the
server (`LocationSearch::sanitize`): a selection without a city is rejected, and `osm` is kept only
when `type ∈ {node, way, relation}` and `id` is numeric, because the detail page builds
`https://www.openstreetmap.org/{type}/{id}` from it.

#### Period storage — correction to `docs/domain/entities.md`

`entities.md` recorded the period as "literal `dd.MM.yyyy` strings — not ISO 8601". That is true of
the **form** and of the validation, but **not of what is persisted**:
`serializeProjectDescription` runs `parseDateISOString`, i.e. `formatISO(parse(dd.MM.yyyy))`, so
the stored `period.from`/`period.to` are ISO-8601 date-times at local midnight
(`2027-03-01T00:00:00+01:00`); the edit screen and detail page convert them back
(`toLocaleDateString('de-DE')`, un-padded: `1.3.2027`). `entities.md` is corrected. The rewrite
stores the same ISO-8601 form (`ProjectDate::toStored`, at midnight in the application time zone)
and displays the stored calendar date (BUG-023). The form accepts `d.m.yyyy`/`dd.mm.yyyy` with a
two- or four-digit year (two digits resolve to 1950–2049, as date-fns' `yy` did); impossible dates
(31.2.) fail as "Nicht im Format dd.mm.yyyy". *Inferred*: date-fns' `isMatch(…, 'dd.MM.yyyy')` also
accepted 1–3-digit years; this is not reproduced (such a date is not a usable period).

### Rich text

- **Package**: TipTap (`@tiptap/core` + individually listed extensions), the "any Livewire-compatible
  package configured down to the six-tool toolbar" decision made concrete
  (`docs/rewrite/architecture-decisions.md`). Slate is not used.
- **Toolbar**: bold, italic, underline, unordered list, ordered list, link — nothing else. No
  headings, quotes, code, strike, rules, hard breaks; no formatting hotkeys and no markdown-style
  input rules (Slate defined none); lists cannot nest. Enter, Backspace and undo/redo work.
- **Link**: the native `window.prompt('Gib die URL des Links ein.')`; with a collapsed cursor the URL
  becomes the link text; a pasted URL becomes a link (`withLinks`).
- **Stable representation**: the ProseMirror JSON in `App\Support\RichText`. Every incoming document is
  re-normalized server-side to the whitelist (unknown nodes/marks/attributes dropped) before
  validation and persistence — the browser is not a trust boundary.
- **Validation**: serialized length ≤ 6000 (`Maximale Zeichenlänge erreicht`); the description may not
  be a lone empty paragraph (`Gib eine Beschreibung ein`). *Inferred*: historically the limit
  measured `JSON.stringify` of the Slate tree (structural overhead included); the TipTap tree has
  comparable overhead, so the same 6000 is applied to its JSON length.
- **Rendering**: server-side (`RichText::toHtml`), reproducing `serializeJSX`: bold `font-medium`,
  italic `<i>`, underline `<u>`, lists `ml-8 list-disc|list-decimal`, links forced to `https://` +
  whatever follows the last `//` (which also neutralises `javascript:`), `target=_blank`, and every
  string escaped. First-slice projects without a document render from their plain text.
- **Search text**: block text joined by a space, whitespace collapsed — `description`/`team` hold it.
- BUG-019 check: the new editor has no field-interaction-order coupling (state is one property
  written on every change).

### Location

LocationIQ `autocomplete` with the historical parameters (`countrycodes=de`,
`accept-language=de`, `limit=5`, `tag=place:city,place:town,place:village`, `dedupe=1`,
`normalizecity=1`), queried **server-side** so the key is never sent to the browser (a
replacement of the historical client-side call, behavior unchanged). The field debounces 500 ms;
typing discards a selection; a failed lookup keeps the previous suggestions; a suggestion must be
picked (`Wähle einen Ort aus der Liste aus`); Enter never submits; the right element is a search
icon, or a clear (X) button once there is text. `LOCATIONIQ_KEY` is configuration
(`docs/deployment/README.md`); without it no suggestions appear and a fixed location cannot be
chosen (remote projects are unaffected). Development and CI use the bundled `locationiq-stub`
service, never the live API.

## Architecture

- `App\Livewire\Projects\ProjectWizard` — step, navigation, creation. `ProjectEdit` — view selector,
  per-view save, delete. Both use `App\Livewire\Concerns\ManagesProjectFields` (state, rules,
  location handling, serialization), the analogue of the historical shared `ProjectForm/*` fields.
- Validation is intentionally *not* Livewire's `validate()`: it validates only the fields on screen,
  on blur and on submit, replacing the error for exactly those fields (Formik's touched/errors
  semantics).
- Blade field components under `resources/views/components/project-form/` (one per field), plus the
  atoms/molecules they need: `radiobox`, `progressbar`, `info-card`, `select`, `rich-text-editor`,
  `field-title` (now with its real popover), `icon`, `checkbox` (icon glyph).
- No workflow engine, form builder or repository was introduced; the trait is the only shared
  abstraction, justified by the historical code sharing the same field components between create and
  edit.
- Alpine (bundled by Livewire) is used only inside the editor, the place-search list and the two
  small disclosure/dirty-tracking widgets — not as an architectural layer.

## Intentional scaffolding (remaining)

| Item | Why | Ends with |
|---|---|---|
| Wizard step 3 and edit view "Gesuche": the "Gesuch erstellen" button is present but disabled; the created-requests area shows the "no requests" state | The four-step structure is required, `ProjectRequest` is out of scope | The ProjectRequest slice |
| Detail: "Kontaktieren" is a `mailto:` for every project; for "Über Nusszopf" it goes to `mail@nusszopf.org` | Historically it opens the contact form dialog (server-sent e-mail), which is the e-mail slice | The e-mail/contact slice |
| Detail: no visitor counter, no "Projekt melden" link | `ProjectAnalytics` is out of scope | Later slices |
| Author avatar is an initial-on-grey circle (same colors) instead of the ui-avatars.com image | External service dropped (self-hosting); avatar upload is out of scope | Avatar slice |

## Spec corrections made while implementing

1. `first-slice.md`: stepper gating mechanism (above); the wizard's default visibility is `public`
   (the DB default is `private`) — `first-slice.md`'s table said "private at creation".
2. `entities.md`: period storage (above).
3. `docs/design/components.md` had `FieldTitle`/`Popover`/`Radiobox`/`Combobox`/`Progressbar` as
   "Listed, not Read" — now read and reproduced.
4. `screen-specs.md`: the wizard's step contents, the edit screen's view structure and the
   "loading" toasts (a `loading` toast is shown on submit and stays its 3 s — nothing replaces it).

## Test map

| Layer | File | Covers |
|---|---|---|
| Unit-ish | `tests/Feature/Support/RichTextTest.php`, `ProjectDateTest.php` | the whitelist, escaping, https forcing, the empty rule; date parsing/storage/display |
| Livewire | `tests/Feature/Projects/ProjectWizardTest.php` | step order/labels/progress, every step's validation, blur semantics, history-restored steps, no draft, creation shape, defaults, sanitization, location autocomplete, defensive re-validation |
| Livewire | `tests/Feature/Projects/ProjectEditTest.php` | owner/non-owner/guest, loading, first-slice projects, per-view save, save-only-when-changed, settings, tamper, view switch, delete |
| Feature | `tests/Feature/Projects/ProjectDetailContentTest.php` | location/period/rich text/team/motto/contact/banner/author |
| Search | `tests/Feature/Search/ProjectSearchSyncTest.php` | the indexed document, edit → re-index, publish ⇄ hide, delete, location/team/motto findable (real Meilisearch) |
| E2E | `tests/E2E/specs/user/project-journey.spec.ts` | the complete journey |
| E2E | `tests/E2E/specs/user/project-wizard.spec.ts` | `?step=`, history, refresh, Enter, blur, period, toolbar, place search, popover, access, phone layout |
| E2E | `tests/E2E/specs/visitor/first-slice-journey.spec.ts` | the first-slice journey, now through the wizard |

All E2E specs run on Chromium, Firefox and WebKit against the real Compose stack.
