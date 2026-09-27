# Nusszopf project site

The static, single-page public site: what Nusszopf is, a link to the public demo
(<https://nusszopf.org>, the hero's primary button), a self-hosting quick start, the technology
stack, releases, and licensing. This is **not** the application — it has no PHP, no database, no
Livewire, and is not part of the Docker images or the release process. It is deployed separately to
GitHub Pages by `.github/workflows/site.yml`.

## Why this exists

`resources/views/home.blade.php` (the app's `/` route) is a verbatim historical reproduction of the
original Nusszopf community platform's landing page — it is not, and should not become, a page for
discovering and installing the FOSS software project. This site is that page instead, kept
deliberately small and separate from the application.

## How it stays in sync with the app

- **Visual language**: `src/main.css` imports `../resources/css/tokens.css` and
  `../resources/css/components.css` directly (by relative path, not a copy). Those two files are
  extracted verbatim from the application's `resources/css/app.css`, which imports them back — so
  both builds compile from the exact same source. Do not fork or duplicate them here; change the
  shared files once.
- **Self-hosting quick start**: the code block in `index.html` is transcluded from the repository
  root `README.md` at build time (see the `quickstart` plugin in `vite.config.js`), from between the
  `<!-- quickstart:start -->` / `<!-- quickstart:end -->` markers there. Edit the snippet only in
  `README.md`; the build fails if the markers go missing, rather than silently shipping a stale copy.
- **Current release**: deliberately not hardcoded anywhere. The Releases section links to GitHub's
  own Releases and Changelog pages, and shows a live `shields.io` badge (the same technique
  `README.md`'s own CI/Security badges use) instead of a build-time value.
- **Brand assets**: `src/assets/icons/` and `public/` hold plain copies of Nusszopf's own marks and
  favicons (`resources/icons/`, `public/favicons/`, `public/images/`) — these change rarely, so a
  committed copy is simpler than a sync script. If those source files change, copy them again.

## Local development

```sh
cd site
npm install
npm run dev      # http://localhost:5173
npm run build    # outputs to site/dist/
npm run preview  # serve the production build locally
```

## Licensing and provenance

Only Nusszopf's own brand assets are reused here (favicons, the header/hero logos, the OG image) —
see `docs/legal/provenance.md` and decision A-5 for why those are reusable. No third-party partner
logos (`resources/logos/*`), no Feather icons, and no other third-party visual assets are used on
this page.

This build bundles its own copy of the Barlow font (`@fontsource/barlow`, SIL OFL 1.1) — the shared
`tokens.css` only names it in `--font-sans`, it doesn't load the font files, so this site needs its
own `@import`s of the same four weights (`src/main.css`) for the shared type scale to actually render
in Barlow. `vite.config.js`'s `barlowLicense()` plugin emits `dist/LICENSE-barlow.txt`, mirroring the
application's own `vite.config.js` (`docs/legal/provenance.md`, finding P15-03); Tailwind's build
keeps its own MIT license banner in the compiled CSS, same as the application's build does.
