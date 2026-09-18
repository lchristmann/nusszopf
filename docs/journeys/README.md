# User Journeys

Source: `historical/web-nusszopf/projects/e2e/cypress/integration/*.spec.js` (Cypress 6.8.0, run against `https://web.dev.nusszopf.org`, viewport 1440×800). Per the Evidence rule, E2E tests are the highest-value evidence of actual product behavior — these journeys are transcribed as directly as possible from the actual test steps, not paraphrased, and are intended to be the acceptance criteria for the equivalent Playwright suite.

**Important structural finding, Confirmed:** `cypress.json` sets `"testFiles": ["main.spec.js"]`, and `main.spec.js` does nothing but set a cookie-preservation rule and `import` the five other spec files (`_landingpage`, `_search`, `_auth`, `_projects`, `_settings`). The leading underscore on those five filenames is a convention to exclude them from Cypress's own file-glob discovery (so they only run when explicitly imported by `main.spec.js`). All five files' tests **share one continuous session and one continuous dataset** — they are not independent, isolated specs; `_auth` creates the account, `_projects` creates/updates/deletes a project using that same logged-in session, `_settings` deletes that same account at the very end. **This is a single, ordered, stateful end-to-end script, not a suite of independent test cases.** Any Playwright rewrite should decide deliberately whether to preserve this "one long story" structure or split it into independent, seeded test cases — record this as an architecture decision, don't silently change it.

## Journey 1 — Landing page CTAs (`_landingpage.spec.js`)

**Actors:** anonymous visitor. **Precondition:** none (fresh visit to `/`).

1. Visit `/`.
2. Click the element `[data-test="route_search-page"]` (last match on the page). **Expect:** browser location becomes exactly `https://web.dev.nusszopf.org/search`.
3. (Fresh `/` visit again, `beforeEach`.) Click `[data-test="route_create-project-page"]`. **Expect:** browser location **contains** `https://auth.nusszopf.org/login` — i.e. an anonymous visitor trying to create a project is sent to the separate Auth0-hosted login app, not to an in-app login screen or a client-side redirect to `/api/login` first. **Confirmed** — this specific CTA on the landing page links directly to the external auth domain, bypassing the `webapp`'s own `/api/login` handler. Cross-reference `docs/authentication/README.md` for whether this is the same eventual destination as `/api/login` or a genuinely different entry point.

Note: the `data-test` selectors `route_search-page` and `route_create-project-page` were **not** found during the frontend archaeology pass over `webapp/src/pages/index.js` (which uses CMS-driven `Link`/`Route` components without these exact test ids observed in the code read in this pass) — either they live inside CMS-driven markup not opened, or inside `HowToSection`/`CarouselSection` container internals not opened in this pass. Flag as **Unknown — needs a follow-up read of `assets/data/*.data.js` and `containers/home/**` before this journey can be fully re-verified against source.**

## Journey 2 — Registration, logout, login (`_auth.spec.js`)

**Actors:** anonymous visitor → registered user. **Precondition:** `before()` (not `beforeEach`) visits `/` once — the whole file runs as one continuous session, per the structural note above.

**Register:**
1. Open the nav hamburger (`btn_burger_nav-header`) → click **Login/Signup** (`btn_login_nav-header`). This routes to the external Auth0-hosted `auth-login` app (see `docs/authentication/README.md`).
2. On that app, click the **last** tab in a `@reach/tabs` tab list (`[data-reach-tab-list]`) — i.e. switch from the default (login) tab to the registration tab.
3. Fill `username` = `e2etest`, `email` = `nusszopf.e2e.test@mailto.plus`, `password` = `asdf1234A!`, check the privacy-consent checkbox (`name="privacy"`, forced click — likely because it's visually hidden behind a styled label, consistent with the `Checkbox` atom pattern seen in `webapp`).
4. Submit. **Expect:** location becomes exactly `https://web.dev.nusszopf.org/user/projects` — i.e. **registration logs the user in immediately and lands them on their projects dashboard**, matching the `returnTo: '/user/projects'` hardcoded in `webapp/src/pages/api/login.js`. **Confirmed**, no email-verification gate blocks this landing (see `docs/authentication/README.md` for whether verification happens asynchronously afterward).

**Logout:**
1. Open hamburger → click **Logout** (`btn_logout_nav-header`). **Expect:** location becomes exactly `https://web.dev.nusszopf.org/` (home).

**Login:**
1. Open hamburger → click **Login/Signup** again (same entry point serves both login and registration, differentiated only by which tab is active — this is the same nav menu item regardless of auth state, its label is static "Login/Signup"-equivalent copy).
2. On the (now default, first tab) login form: fill `emailOrName` = `e2etest` (**Confirmed** — the login form accepts either the username or the email in a single combined field, not two separate fields/tabs), `password` = `asdf1234A!`.
3. Submit. **Expect:** location becomes exactly `https://web.dev.nusszopf.org/user/projects` — same landing destination as registration.

## Journey 3 — Project lifecycle (`_projects.spec.js`)

**Actors:** the user created in Journey 2 (still logged in — no separate login step in this file). **Precondition:** implicitly continues the session from Journey 2.

### Create

1. From wherever the previous test left off, click `[data-test="route_create-project_projects-page"]` (first match).
2. **Step 1** (Description): type `description` = "Test Description", `title` = "Test Title", `goal` = "Test Goal"; click the "flexible period" radio and the "remote location" radio (both `{ force: true }` — likely custom-styled radios with a visually-hidden native input, consistent with the `Radiobox` atom); click **Next** (`btn_create-or-next_navigation`).
3. **Step 2** (Description cont'd): type `team` = "Test Team", `motto` = "Test Motto"; click **Next**.
4. **Step 3** (Requests): click **"Create" inside the requests step** (`btn_create_requests-step`) which opens the `edit-request-dialog`; inside it, type request `description` = "Test Description", `title` = "Test Title", select category `companions` from `select_request-category`; click **Create/Save** (`btn_create-or-save_edit-request-dialog`); then click **Next** on the wizard itself.
5. **Step 4** (Settings): no field interactions in the test — just click **Create** (same `btn_create-or-next_navigation` button, now acting as the final submit — **Confirmed** the wizard reuses one button element/label pattern for "Next" and final "Create", differentiated only by step).
6. **Expect:** redirected to `https://web.dev.nusszopf.org/user/projects`; the new project's edit card (`route_edit-project_projects-page`) contains a title element (`text_title_project-edit-card`) with text exactly "Test Title", and — nested inside the same card — a request preview (`text_title_preview-request-card`) also reading "Test Title" (**Confirmed**: the request's own title in this test run happens to equal the project's title, both literally "Test Title", since both fields were filled with that same string — this is a test-data coincidence, not evidence that the UI always shows the project title in that slot; don't over-read it as "request preview shows project title").

   **Comment in source, Confirmed real historical workaround:** `// current workaround: slate-editor has to be edited first, else it would not work — https://github.com/ianstormtaylor/slate/issues/3476` — the description rich-text field must be interacted with before other fields or the form breaks, due to a known upstream `slate` bug. Record in `docs/rewrite/open-questions.md`: does the replacement rich-text implementation need an equivalent ordering workaround, or is this purely an artifact of the old `slate` version that goes away with a modern editor?

7. **Search indexing check:** visit `/search`, wait 2000ms (a fixed sleep — **Confirmed** the historical test itself relies on a hardcoded delay rather than an explicit "wait for index ready" signal, i.e. search indexing was known/assumed to be asynchronous with unpredictable latency even in the historical implementation), click the search button (`btn_search_search-input`) with an empty query, and expect the first hit card's title (`route_title_hitcard`) to read "Test Title" — i.e. **an empty search returns all/recent projects**, this isn't testing query matching, just that the newly created project is indexed and appears.

### Update

1. Visit `/user/projects`, click the (only) project's edit card. Append " Updated" to the title field, click **Save** (`btn_save_project-view`).
2. Switch the edit-page's view selector (`select_view_edit-project-page`) to **"Gesuche"** (Requests). Open the request's contextual menu (`menu_edit-request-card`) and click the first menu item (`menuitem-0`, forced click) — **Inferred** this is "Edit" (position 0 in a menu that Journey/testing elsewhere shows also has a delete item at position 1 — see Delete below). Inside the resulting `edit-request-dialog`, append " Updated" to the request title, save.
3. Navigate back to the projects list via the nav header's "my projects" icon (`btn_user-projects_nav-header`). **Expect:** both the project-card title and the request-preview title now read "Test Title Updated" — confirming both edits persisted and are reflected on the list view (not just the edit view).
4. Search re-check (same 2000ms-wait pattern): the top hit now reads "Test Title Updated".

### Delete

1. **Delete a request**: open the project's edit page → "Gesuche" tab → request's menu → **`menuitem-1`** (forced click) — **Confirmed by elimination**: since `menuitem-0` was used for edit above, `menuitem-1` is the **delete** action in that same per-request contextual menu. Navigate back to the projects list; a native `window:confirm` is stubbed to auto-accept (`cy.on('window:confirm', () => true)`) — **Confirmed** deleting a request triggers a native confirm dialog (see `docs/design/states.md`). **Expect:** the request preview element no longer exists on the project card.
2. **Delete the project itself**: open the project's edit page → "Einstellungen" (Settings) tab → click **Delete** (`btn_delete_settings-view`); native confirm auto-accepted. **Expect:** the project's edit card no longer exists in the list.
3. **Search re-check**: type "Test Title Updated" into the search input, click search. **Expect:** no hit cards — confirming the delete also removed the project from the search index, not just from the relational data.

## Journey 4 — Account settings (`_settings.spec.js`)

**Actors:** the same user, now with zero projects (continues directly from Journey 3). **Precondition:** logged in.

1. Visit `/user/profile`. **Expect:** the avatar's username element (`username_avatar`) reads exactly "e2etest" — i.e. the profile page's avatar displays the **username**, not a separate display name, when no display name was set during registration (registration only collected `username`/`email`/`password`, no separate "name" field — cross-reference `docs/authentication/README.md`).
2. Wait 2000ms (fixed sleep, reason not stated in source — possibly waiting for user/session data to finish loading before the delete button is interactive; **Unknown**, not explained by a code comment). Click **Delete account** (`btn_delete-account_settings-page`); native confirm auto-accepted. **Expect:** redirected to `https://web.dev.nusszopf.org/` (home) — i.e. account deletion logs the user out and returns them to the landing page, consistent with the `logout()` call observed in `webapp/src/pages/user/profile.js`.

This is the last test in the combined session — the test-created account and its data are fully cleaned up by the suite itself (register → exercise → delete), which is why the suite must run start-to-finish as one script rather than as independent cases, per the structural note above.

## Journey 5 — Search (`_search.spec.js`) — **NOT actually covered**

**Confirmed, significant finding:** this spec is wrapped in `xcontext` (Cypress/Mocha's "skip this whole block" — the tests never run even in a full suite execution) and its three test bodies are literal no-op placeholders:

```js
it('User can search for projects', () => { expect(true).to.equal(true) })
it('User can filter for specific projects', () => { expect(true).to.equal(true) })
it('User can contact a project', () => { expect(true).to.equal(true) })
```

Actual search behavior in the historical product is exercised **only incidentally**, as a side effect of the create/update/delete assertions inside `_projects.spec.js` (does a specific project's title appear/disappear from an otherwise-empty query). There is **no historical E2E coverage at all** for: searching by a real query term, filtering (`FilterPopover`), or contacting a project's owner from a search result. This is a genuine, confirmed gap in historical test coverage — record it in `docs/rewrite/open-questions.md` and treat these three flows as needing behavior confirmed from implementation (`docs/design/screens.md`'s Search section) rather than from E2E evidence, and ensure the new Playwright suite actually covers them (closing the historical gap, not reproducing it).

## Not covered by this pass

- Any journey through `auth-login`/`auth-password` beyond what these specs drive externally (tab switch, field names) — full internal behavior belongs to the authentication archaeology.
- Newsletter subscribe/unsubscribe journeys, avatar upload/crop, contact-a-project-owner dialog flow, image upload — no E2E evidence exists for these; they would need to be derived from implementation only (see `docs/design/screens.md`) and flagged as **Unknown/Inferred**, not asserted as tested historical behavior.
