# Eighth Vertical Slice — Profile, avatars and account deletion

Status: **implemented and verified** (2026-09-23). This slice shipped `/user/profile`: avatar upload
with client-side crop and server-side re-validation/re-encoding, the author avatar image replacing the
initial-on-grey fallback everywhere it's shown, the sponsoring/support/delete-account subsections, and
account deletion with a cascade design that closes the historical orphaned-external-state risk. Scope
and sequencing come from `docs/rewrite/master-roadmap.md`, "Slice 8".

**Out of scope (unchanged)**: the newsletter subsection is intentional scaffolding — no `Lead` model,
double opt-in or consent record until slice 9 (decision A-1); Home, legal pages and the rest of the
public shell (slice 10).

## Historical mechanics (Confirmed against `web-nusszopf`, read in full)

| Item | Evidence | Behavior |
|---|---|---|
| Avatar image | `Avatar.molecule.js`/`.theme.js` | 56px circle, `border-2 border-steel-700`, `bg-steel-700` behind the image; `variant="settings"` dims to `opacity-30` and overlays an edit pencil, but only for a non-social account |
| Avatar upload | `AvatarDialog.js`, `Cropper.organism.js`, `pages/api/upload.js` | Round 1:1 client crop with rotate/zoom controls, compressed to 150×150 JPEG client-side, uploaded via an S3 presigned POST capped at 1 MB by the bucket policy alone — nothing server-side re-validates the bytes; filename `{id}\|nz_v{n}.jpeg`, version parsed back out of the previous value |
| Avatar cleanup | `users.function.js` (`handleUpdateUser`/`handleDeleteUser`), `clean_up_users_digitalocean`/`clean_up_deleted_user` triggers | The previous picture file is deleted from Spaces on replace or account deletion, via an async webhook |
| Account deletion | `docs/domain/workflows.md` "account deletion" | `delete_users` deletes the row immediately; DB cascade removes projects/requests/analytics; an async webhook is relied on for external cleanup (Auth0 identity, avatar file), with only 3 retries and no dead-letter queue |
| Profile page | `profile.js`, `profile.data.js` | `FramedGridCard`, header (title + settings avatar), two-column body: newsletter subsection (subscribe form or unsubscribe button per `lead.hasConfirmed`), sponsoring subsection (Steady link), delete-account subsection, two `InfoCard`s (vCard "Kontakt speichern" link, support `mailto:`) |
| Nav entry | `docs/design/navigation.md` | "Account" menu item (`User` icon, name truncated to 12 chars) linking to `/user/profile`, between "Meine Projekte" and "Log out" |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Avatar storage is the local `public` disk, not S3/DigitalOcean Spaces | **Replace** (register B4, already decided) | Local disk in v1; `avatars/{user}-v{n}.jpg` reproduces the historical versioned-filename scheme |
| 2 | A dedicated `users.avatar_version` counter replaces parsing the version back out of the stored filename | **Replace** (implementation technique) | Same product behavior (old versions replaced, not accumulated); more robust than regex-parsing a string, the same category as BUG-007's `visibility` CHECK constraint |
| 3 | `web` mounts `laravel-storage` read-only and nginx serves `/storage/...` directly | **New wiring** (register B8) | Closes a real gap the roadmap's architecture audit (§6) flagged: `compose.prod.yaml`'s `web` had no storage volume at all. Distinct from the Vite-assets "no shared volume" decision (`docs/deployment/README.md`) — that reasoning is about *build* assets seeded from an image once; avatars are genuine runtime data with no image to reseed from |
| 4 | Every avatar upload is decoded, center-cropped and re-encoded server-side (BUG-031) | **Fix** | The historical upload endpoint trusted the client's crop/compress step entirely, checking only a ≤1 MB size condition. `App\Support\AvatarUploader` does not trust it |
| 5 | Client-side crop uses `cropperjs`, not `react-easy-crop` | **Replace** (implementation technique) | Smallest client-side package covering the same round-crop/rotate/zoom feature set (`docs/design/components.md`, `Cropper`) |
| 6 | Account deletion deletes every owned `Project` one at a time through Eloquent, not a raw DB cascade | **Fix** (resolves a documented open question) | A raw `ON DELETE CASCADE` raises no Eloquent model events and would silently orphan the Meilisearch documents `Project::booted()`'s `deleting` hook otherwise removes. Full reasoning: `App\Support\AccountDeleter`, `docs/rewrite/open-questions.md` → "Account deletion and orphaned external state" |
| 7 | The "Kontakt speichern" vCard `InfoCard` is not reproduced — *superseded in slice 10: the card is back, linking the generated vCard* | **Replace** (dependency/identity dropped) | Extends the sixth slice's identical decision for the same link in the mail footer (`resources/views/components/mail/layout.blade.php`) — publishing the original nusszopf.org's own contact identity from every self-hosted instance would misattribute a stranger's operator identity |
| 8 | The Steady sponsoring link and the `mail@nusszopf.org` support `InfoCard` stay as literal historical defaults | **Preserve** (documented gap, not blocking) | Same treatment as the Instagram link/`NUSSZOPF_CONTACT`/`MAIL_FROM_ADDRESS` elsewhere in the app — passive external links, not an artifact actively vouching for an identity the way a downloadable vCard is |
| 9 | The newsletter subsection renders the historical copy but every control is inert (`disabled`) | ~~**Intentional scaffolding**~~ Closed in slice 9 (the subsection is live) | No `Lead` model until slice 9; unlike the registration checkbox (part of a real, working outer form), this subsection has no outer action to piggyback on, so the honest choice is a visibly non-interactive placeholder rather than a button that does nothing when clicked |
| 10 | `Avatar.skeleton.js`'s loading placeholder is not reproduced | **Replace** (architecture) | Blade/Livewire renders `auth()->user()` synchronously on first paint; there is no async client-side fetch for this data the way the historical SPA had, so there is no equivalent loading state to reproduce |

## Implementation

- **Migration** `database/migrations/2026_09_23_090000_add_avatar_version_to_users_table.php`: `users.avatar_version` (unsigned int, default 0).
- **`App\Models\User`**: `avatarUrl()` (Google's absolute URL as-is, or `Storage::disk('public')->url($picture)` for a local path; `null` renders the initial-on-grey fallback) and `isSocialAccount()` (`google_id !== null`).
- **`App\Support\AvatarUploader`**: GD decode → center-square-crop → cap at 150×150 → re-encode JPEG(60) (corrected in P-6, PERF-02: the slice had used 512×512 at 85, larger than the historical avatar) → store `avatars/{user}-v{n}.jpg` → delete the previous file only after the new one is written.
- **`App\Support\AccountDeleter`**: deletes every owned `Project` through Eloquent (de-indexing via existing model events), then the avatar file, then the `users` row, in one transaction.
- **`App\Policies\UserPolicy`**: `update`/`delete`, self-only (`docs/security/authorization-matrix.md`).
- **`App\Livewire\Profile\Profile`** (`WithFileUploads`): `saveAvatar()` (validates, delegates to `AvatarUploader`, dispatches `toast`/`avatar-saved`) and `deleteAccount()` (authorizes, deletes a *separate* freshly-fetched `User` instance — see "A real bug found and fixed" below — logs out, flashes a toast, redirects to `/search` — Home since slice 10).
- **UI**: `resources/views/components/avatar.blade.php` (`variant="settings"`/`"project"`), `avatar-dialog.blade.php` (crop dialog), `resources/js/avatar-cropper.js` (Alpine + `cropperjs`), five new Feather-style icons (`edit-3`, `rotate-cw`, `zoom-in`, `zoom-out`, `upload`), a round-crop-mask CSS recipe in `resources/css/app.css`. `project-detail.blade.php`'s author block now renders `<x-avatar variant="project">` instead of an inline initials block. `nav-header.blade.php` gained the "Account" menu item.
- **Infra**: `docker/php/Dockerfile` bakes a `public/storage` symlink into both production images; `docker-compose.yaml`'s `web` mounts `laravel-storage` read-only; `docker/nginx/default.conf` serves `/storage/...` directly with a long, immutable cache lifetime; `scripts/smoke-test.sh` gained a step proving `web` serves a file `php-fpm` wrote to the public disk.

## A real bug found and fixed during implementation

`Profile::deleteAccount()` originally deleted the account, *then* called `Auth::logout()`. This
silently **resurrected the just-deleted row**: `Auth::logout()`'s remember-token cycling calls
`save()` on the guard's own cached `User` instance — the same object `AccountDeleter::delete()` had
just called `delete()` on, which flips that instance's `exists` flag to `false` — so the subsequent
`save()` became an `INSERT`, not an `UPDATE`, reinserting the exact row that was deleted, with all its
original attributes still in memory. Caught by a Feature test (`DeleteAccountTest`) that checked the
row was actually gone after the Livewire call, not just that a redirect happened. Fixed by feeding
`AccountDeleter` a **separate**, freshly-queried `User` instance, leaving the guard's own cached
instance untouched for the logout that follows (`app/Livewire/Profile/Profile.php`'s docblock has the
full explanation). A second, smaller issue in the same method: redirecting to `route('home')` (itself
a redirect to `/search`, `routes/web.php`'s temporary scaffolding) silently lost the flashed success
toast, since a flashed session value survives only one subsequent request, not two — fixed by
redirecting to `route('search')` directly.

## Test map

| Layer | File | Covers |
|---|---|---|
| Feature | `tests/Feature/Profile/AvatarUploadTest.php` | first upload, version increment + old-file cleanup on replace, Google-URL avatars never trigger local file deletion, a non-image upload is rejected server-side (BUG-031) before it reaches storage, an oversized/non-square source is center-cropped and capped, `UserPolicy` self-only, the edit affordance is hidden for a social account |
| Feature | `tests/Feature/Profile/DeleteAccountTest.php` | cascade completeness (projects/requests/analytics), every owned project is deleted through Eloquent (not a raw cascade) — proven via a `Project::deleting` probe, the avatar file is removed, `UserPolicy` self-only, the account is actually logged out |
| Feature | `tests/Feature/Profile/ProfilePageTest.php` | auth required, own account only, the newsletter scaffold renders inert, the sponsoring/support links render, the vCard link does not, the nav header "Account" item links to the page |
| Feature | `tests/Feature/Projects/ProjectDetailContentTest.php` | the author's real avatar image renders once they have one; the initial-on-grey fallback otherwise |
| E2E | `tests/E2E/specs/user/profile.spec.ts` | upload → crop → save → replace (version increments, previous file 404s) end to end in a real browser; delete account → redirected + toast → logged out for real (no fixed sleeps, per the resolved open question about the historical test's unexplained `cy.wait(2000)`) |

Manually verified in a browser against the running dev stack (`compose.dev.yaml`): the Profile page
layout at desktop width, the avatar dialog's file picker/round crop mask/rotate/zoom controls filling
the dialog correctly, a full upload round trip (toast sequence, dialog closing, the settings avatar
updating to the new image at `opacity-30` with the edit pencil visible over it), and `/storage/...`
being served by `web` after `php artisan storage:link` (already documented as development step 8,
`docs/development/README.md`) — the equivalent production wiring is `scripts/smoke-test.sh`'s new step.

## Remaining gaps

- Newsletter subscribe/unsubscribe is inert scaffolding until slice 9 — tracked there, not here. **Resolved in slice 9** (`docs/rewrite/ninth-slice.md`).
- `Avatar.skeleton.js`'s loading placeholder has no equivalent (decision 10) — not a gap so much as a
  category that doesn't apply to a server-rendered page; noted for completeness.
- Playwright coverage runs only against `compose.dev.yaml`; the production-image smoke test
  (`scripts/smoke-test.sh`) checks the storage-serving wiring specifically but not the full upload UI
  (closed in P-7: the whole Playwright suite now runs against the production images, `docs/release/parity/P-07-production-e2e.md`).
