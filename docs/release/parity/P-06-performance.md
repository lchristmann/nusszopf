# P-6 Performance sanity (2026-09-24)

Exit evidence (`master-roadmap.md` §4): an N+1 check on the list screens, the page weight of the Vite bundle, and
search latency on a realistic seeded dataset, with no invented budgets. The roadmap limits the historically relevant
areas to search debounce, masonry, the server-rendered pages and the image size limit.

This is not a benchmark programme. No numbers below are targets. Each result is labelled **Measured** or
**Recommendation**. Findings that were fixed are called PERF-01 to PERF-04. Like P-4's SEC-xx and P-5's DEV-xx, they
are this page's own identifiers: all four are defects of Nusszopf 2, not historical behaviour, so `docs/rewrite/bugs.md`
has no new entry.

**Where the measurements come from:** the development stack on one Linux workstation, with `APP_DEBUG=true`, Xdebug
loaded (mode `off`) and nothing cached, measured with Chromium. Production differs: it has no debug mode and serves
Livewire's minified script. Where that matters, the production figure is computed from the file production actually
serves. P-7 will run the whole suite against the production images.

## Findings

| ID | Finding | Severity | State |
|---|---|---|---|
| PERF-01 | The rich-text editor and the avatar cropper, 98 % of the app's JavaScript, loaded on every page | Medium | Fixed |
| PERF-02 | Avatars stored at 512×512, quality 85, instead of the historical 150×150, quality 60 | Low | Fixed |
| PERF-03 | `search:reindex` ran two queries per project | Low | Fixed |
| PERF-04 | An editor's `destroyed` flag leaked to a shared scope and stopped later editors from starting (found while fixing PERF-01) | High while it existed; never released | Fixed before commit |

### PERF-01: the editor and cropper in the main bundle (Medium)

- **Measured:** `app.js` was 420.5 KB raw, 131.2 KB gzipped, and every page loaded it, including Home, Search and the
  project page. Of that, TipTap/ProseMirror accounted for about 360 KB and cropperjs for 39 KB. The app's own code is
  about 7 KB.
- **Historical:** Next.js split the code per page, so the editor code (Slate) came only with the pages that import
  it. That the historical public pages never loaded Slate is **Inferred**: the ui-library's barrel imports may have
  pulled some of it into a shared chunk.
- **Fix:**
  - `resources/js/lazy/tiptap.js` holds the TipTap imports. `rich-text-editor.js` loads that chunk with `import()` when
    an editor starts, and the editor component carries a `<link rel="modulepreload">`, so the chunk downloads alongside
    the page.
  - `avatar-cropper.js` loads cropperjs when a picture is picked.
  - The cropper's CSS stays in `app.css`.
- **After:** `app.js` is 10.7 KB raw, 4.6 KB gzipped. The TipTap chunk (373 KB raw, 117.6 KB gzipped) loads only on
  the wizard and the edit screen, and the cropper chunk (38 KB raw, 12.4 KB gzipped) only in the avatar dialog.
- **Regression test:** `tests/E2E/specs/visitor/page-weight.spec.ts` asserts two things:
  - Home, Search, a project page, Login and Profile run no script containing TipTap or cropperjs code;
  - the wizard loads TipTap but not the cropper.

  The spec checks the content of the scripts, not file names, so it fails even if the libraries are folded back into
  another bundle. With cropperjs imported statically into `app.js`, it failed on `/`.

### PERF-02: avatars larger than the historical ones (Low)

- **Measured:** the crop dialog uploaded 512×512 JPEGs at quality 0.85, and `AvatarUploader` stored them at up to
  512×512, quality 85. The avatar is shown at 56 px (`w-14`), on Profile and My Projects and, for the author, on every
  public project page. A photo-like test image is 51 KB at 512×512/q85 and 2.8 KB at 150×150/q60.
- **Historical (Confirmed):** `Cropper/utils/index.js` normalises the source to at most 1000 px, and compressorjs then
  stores 150×150 at quality 0.6.
- **Contradiction reconciled:** the BUG-031 entry in `intentional-changes.md` says the historical outcome, "a small,
  square, JPEG avatar", is unchanged. The 512 px implementation contradicted that. The entry, `bugs.md`,
  `eighth-slice.md` and `P-04-security.md` now say 150×150.
- **Fix:**
  - the dialog crops to 150×150 at 0.92, nearly lossless like the historical intermediate canvas;
  - the server caps at 150×150 and re-encodes at quality 60 (`AvatarUploader::MAX_DIMENSION`, `JPEG_QUALITY`), so there
    is one lossy step, as historically.

  Avatars stored before this change keep their size. There is no production data (A-2).
- **Regression tests:** `tests/Feature/Profile/AvatarUploadTest.php`:
  - a 512×512 upload is stored as a 150×150 JPEG;
  - a 2000×1000 upload becomes 150×150;
  - a 100×120 upload becomes 100×100, never scaled up.

  The visual baselines are unchanged: 69 of 69 are pixel-identical, since the reference dataset has no photo avatars.

### PERF-03: `search:reindex` ran two queries per project (Low)

- **Found by** the lazy-loading guard below. `Project` had no `makeAllSearchableUsing()`, unlike `ProjectRequest`. The
  bulk import therefore loaded each project's owner separately (for the `author` field) and ran a `requests()->exists()`
  query per project.
- **Measured** (2,000 projects, synchronous import of the projects): 2,340 queries in 997 ms before the fix, and
  9 queries in 161 ms after.
- **Fix:** `Project::makeAllSearchableUsing()` eager-loads `user` and `withExists('requests')`, and `shouldBeSearchable()`
  uses that flag when it is present.
- **Regression tests:** the guard, and the Meilisearch-backed `ReindexSearchTest`, which CI runs against a real engine
  and which fails without the fix.

### PERF-04: the editor's `destroyed` flag leaked (found and fixed within P-6)

The lazy editor start (PERF-01) added a `destroyed` flag so that a removed editor does not start after its chunk
arrives. The flag was not declared on the component's data object. Alpine writes an undeclared property onto an
enclosing scope, so once any editor on the page was removed, every later editor saw `destroyed === true` and never
started. The request dialog on the edit screen had no editable text. `project-requests.spec.ts` caught it in all three
engines. The flag (and `errorObserver`, which had the same pattern before P-6 but no visible effect) is now declared on
the data object, and the spec passes. This was never committed.

## Checks

### N+1 on the list screens (Measured)

`tests/Feature/Performance/QueryCountTest.php` renders each list screen with one item and with twenty, and requires
the same number of queries both times:

| Screen | Queries |
|---|---|
| My Projects | 2 |
| Search | 2 |
| Project page | 5 |
| Edit screen, "Gesuche" | 2 |
| Sitemap | 1 |

No screen issued a query per item. Without My Projects' eager load of the requests, the test reports 21 queries
instead of 2 and fails.

`Model::preventLazyLoading()` is now on everywhere except production (`AppServiceProvider`). A relation lazily loaded
on a model from a list throws in development and in the tests. It found PERF-03. The whole Pest suite and every E2E
project ran with it on, and neither the logs nor `failed_jobs` show a violation. The queue worker was included, since
Scout syncs run there.

### Bundle and page weight (Measured)

Cold cache, Chromium, bytes transferred, development stack:

| Page | Before | After | Of which script, after |
|---|---|---|---|
| Home | 450 KB | 301 KB | 165 KB |
| Search | 436 KB | 287 KB | 165 KB |
| Project page | 386 KB | 237 KB | 165 KB |
| Login | 389 KB | 240 KB | 165 KB |
| My Projects | 387 KB | 238 KB | 165 KB |
| Profile | 383 KB | 234 KB | 165 KB |
| Wizard | 385 KB | 372 KB | 302 KB (TipTap) |
| Edit screen | 390 KB | 378 KB | 302 KB (TipTap) |

The rest of each page: 15 KB of CSS, 8–32 KB of HTML, and 44–89 KB of fonts (Barlow in two to four weights, latin
subset). The browser fetches the latin-ext and Vietnamese subsets only for characters that need them.

About 160 KB of the 165 KB is `livewire.js`: the development stack serves its unminified build because
`APP_DEBUG=true`. Production serves `livewire.min.js`, 261 KB raw, which is **100 KB** with nginx's current gzip level
and 86 KB at level 6. Production pages are therefore about 60 KB lighter than the table.

Static assets are gzipped, and the hashed files are cached for a year as immutable (`docker/nginx/default.conf`).

### Search latency on a seeded dataset (Measured)

**Dataset:** `Database\Seeders\PerformanceDatasetSeeder`: 200 owners, 2,000 public projects, and 4,946 requests (0–5
per project), a quarter of the projects in a place. That makes 5,281 search documents. How much data the historical
instance held is **Unknown**, since there is no production data (A-2). This is a deliberate overestimate for a
community instance, not evidence. To reproduce:

```sh
docker compose -f compose.dev.yaml exec php-fpm php artisan migrate:fresh --seed --seeder="Database\Seeders\PerformanceDatasetSeeder"
docker compose -f compose.dev.yaml exec php-fpm php artisan search:reindex
```

**`ProjectSearch::search`** (Meilisearch plus the database hydration and grouping), 25 runs each:

| Query | Hits (projects) | Median | p95 |
|---|---|---|---|
| empty query, page 1 | 26 | 8.8 ms | 9.6 ms |
| a common word | 18 | 11.0 ms | 17.0 ms |
| a place ("Leipzig") | 18 | 9.1 ms | 10.2 ms |
| a typo ("Leipzg") | 18 | 8.9 ms | 9.3 ms |
| empty, filter "materials" | 39 | 9.6 ms | 10.2 ms |
| empty, filter "none" + "rooms" | 42 | 9.5 ms | 11.7 ms |
| empty, page 2 | 44 | 14.8 ms | 15.5 ms |
| empty, page 5 | 99 | 29.2 ms | 33.4 ms |
| empty, page 10 | 191 | 56.0 ms | 58.2 ms |

Of the empty page-1 query, 4.8 ms is the engine and 3.5 ms is the database and grouping.

**End to end in the browser** (submit to Livewire response, development stack):

- A search takes 220–280 ms (median).
- The response is 140 KB raw, but 9.7 KB on the wire: half of the raw size is repeated inline SVG icons, the same
  pattern as the historical react-feather icons.
- The framework's own floor on this stack is about 20 ms (time to first byte of `/login`).
- Most of the rest is rendering the cards, about 2 ms per card (mount alone 10 ms; mount plus 26 cards 77 ms).

**"Mehr laden" deep in the results.** Each click re-renders every result shown so far, because the fourth slice chose
`limit = 50 × pages` from offset 0 (a decided design, not re-opened here). So the cost grows with depth:

| Page | Round trip | Raw response |
|---|---|---|
| 2 | 319 ms | 264 KB |
| 5 | 315 ms | 613 KB |
| 10 | 597 ms | 1.2 MB |

Raw responses gzip to roughly a fifteenth.

### Historically relevant behaviour

| Area | Historical | Nusszopf 2 (Measured or checked) |
|---|---|---|
| Search submit | Explicit submit; `throttle(500 ms)`; no new search while one is loading (`SearchInput.js`) | Explicit submit. The fourth slice replaced the throttle with Livewire's one request at a time. Measured: 10 Enter presses in a row sent **3** requests, so that premise holds. |
| Place suggestions | `useDebounce(500)` (`LocationField.js`) | `wire:model.live.debounce.500ms`: the same |
| Rich-text input | Formik `setFieldValue` throttled to 300 ms, client-only | `$wire.$set(…, false)`: client-only, no request per keystroke |
| Visibility toggle | `throttle(1000)` | Throttled once per second (`screen-specs.md`); unchanged |
| Masonry | react-masonry-css, laid out in JavaScript | `nzMasonry`. One layout of 191 cards takes 3.2 ms (5.7 ms at 4× CPU throttling). |
| Server-rendered pages | `getServerSideProps` on the project page and the newsletter confirmation pages | The project page ships its title, texts and requests in the first HTML (no deferred load); the newsletter confirmations are plain controller responses |
| Image size | Avatar 150×150 at q0.6 | Now the same (PERF-02) |

## Recommendations (not done; for the maintainer)

1. **nginx gzip level.** `default.conf` does not set `gzip_comp_level`, so nginx compresses at level 1.
   - **Measured:** at level 6, `app.css` shrinks from 15.2 to 12.6 KB, and `livewire.min.js` from 100 to 86 KB; the
     TipTap chunk shrinks by about 17 % too.
   - **Proposal:** a one-line `gzip_comp_level 5;`, or pre-compressed files served with `gzip_static`. Left out because
     it is tuning, not a defect.
2. **Deep "Mehr laden".**
   - **Measured:** at 4× CPU throttling, the click to page 11 (550 documents) spent 1.2 s in long tasks, mostly
     Livewire morphing the re-rendered list.
   - **Proposal:** revisit the fourth slice's paging decision only if real instances grow that large, for example by
     rendering only the new cards.
3. **Bulk writes and the index.** `Project`'s `saved` hook calls `$requests->searchable()`, which ignores
   `withoutSyncingToSearch()`.
   - **Measured:** seeding 2,000 projects through the models queued thousands of small sync jobs.
     `PerformanceDatasetSeeder` turns model events off instead.
   - At runtime it affects nothing (a project has a handful of requests); keep it in mind for any future bulk-import
     command.
4. **Re-measure on the production images** in P-7. The search round trip and the render cost per card above come from
   the debug stack.

## Verification

| Run | Result |
|---|---|
| `composer test` (Pest) | 605 passed |
| `composer larastan`, `composer lint:check` | clean |
| Playwright, desktop projects | 168 passed, 0 failed, 12 skipped |
| Playwright, device projects (after `cache:clear`, see P-5) | 106 passed, 0 failed, 6 skipped |
| Visual regression, after `tests/Visual/reseed.sh` | 69 passed, pixel-identical |
| `QueryCountTest` without My Projects' eager load | fails, 21 queries against 2 |
| `page-weight.spec.ts` with cropperjs imported statically | fails on `/` |
| `failed_jobs` after the suites | only the known P-12 item (mails for accounts deleted before sending) |

The skips are the same as in P-5: environment-gated search specs, the axe scan outside Chromium, and the avatar gesture
test in WebKit.

## Status

**Done.** Awaiting the maintainer's review. PERF-01 to PERF-04 are fixed with regression coverage. The recommendations
above are open for a decision and none of them blocks the phase.
