# Tenth Vertical Slice — Public shell: Home, legal, errors, SEO

Status: **in progress** (started 2026-09-23). Scope and sequencing come from
`docs/rewrite/master-roadmap.md`, "Slice 10" (inventory items 28–31); the governing decisions are A-4
(legal text is operator-provided) and A-5 (Home verbatim; operator mailbox/identity become configuration).

**Out of scope (unchanged)**: newsletter issue sending (A-6); anything after slice 10 in the roadmap.

## Historical mechanics (Confirmed, read in full for this slice)

| Item | Evidence | Behavior |
|---|---|---|
| Home | `pages/index.js`, `containers/home/{HowToSection,NewsletterSection}/*`, `assets/data/{header,home,contest,fellows,newsletter}.data.js` | No `NavHeader`; hero (`bg-steel-50`) with the big logo and title/subtitle, then the `bg-livid-300` "Wir sind am Kneten" card with a 4-item `<ol>`; HowTo (`bg-yellow-250`, four `StepCard`s, step 3 shows the `Request` icon, one CTA "Alte Version entdecken" → `/search`, `data-test="route_search-page"`); `CarouselSection` commented out; About (`bg-turquoise-300`); Contest (`bg-red-300`, AZ logo); Fellows (`bg-pink-200`, four sponsor logos, three options with CTAs); Newsletter (`bg-blue-300`, `id="newsletter"`, "Kontakt speichern" vCard link, the form). Footer `classy` on `bg-steel-200`; `<main>` `text-steel-700` |
| No "create project" CTA on Home | `HowToSection.js` renders only the search `Route`; `homeData.howTo.actions.create` is unused CMS data | Journey 1 step 3 (`route_create-project-page`) is a stale assertion (`docs/journeys/README.md`) |
| Footer | `ui-library/.../Footer/Footer.organism.js`, `footer.data.js` | `vercel` (default: a centered "Powered by Vercel" badge, `py-6`); `classy` (Home: Impressum / Datenschutz / Rechtliches `Route`s left, Instagram + Vercel badge right, stacked below `md`) |
| Legal pages | `pages/{legalNotice,legalPolicy,privacy}.js`, `legal-notice/legal-policy/privacy.data.js` | `bg-steel-200 text-steel-800`, `Frame my-12 sm:my-20`, `max-w-2xl mx-auto`; h1 `titleMd mb-8` ("Impressum", "Rechtliches", "Datenschutz"); sections `mb-10`, headings `titleSmSemi mb-3`, body `textSm`; closing italic source line. Back chevron → `/`, Privacy → `history.back()` when `?back` is present (Profile passes `?back=history`) |
| Error page | `components/ErrorPage/ErrorPage.js`, `error.data.js`, `pages/{404,500,_error}.js`, `Page/ErrorBoundary.js` | No `NavHeader`; `FrameFullCenter` `bg-warning-200 text-stone-800`; h1 `titleLg` "{code} – Nusszopf verknetet..."; message + `warning` mail link; "Zum Nusszopf" large button `bg-warning-300`; footer `bg-warning-200`. `_error.js` passes any status; `ErrorBoundary` renders it without a code |
| SEO | `components/Page/Page.js`, `seo.data.js`, `pages/_document.js` | `next-seo`: title truncated to 60, description to 150, canonical `DOMAIN + asPath`, `noindex` outside production and on Profile/My projects/create/edit, Open Graph (`de_DE`, `website`, og-image 1648×863), Twitter `summary_large_image` with placeholder handles; project detail passes its title and goal. `_document.js`: favicons, manifest, theme colour, three search-engine verification tags and the Visitor Analytics script |
| Sitemap/robots | `pages/api/sitemap.js`, `next.config.js` rewrite, `public/robots.txt` | `/sitemap.xml` → `/`, `/legalNotice`, `/privacy` and every public project (`lastmod = updated_at`); hostname hard-coded `https://nusszopf.org`; rate-limited 10 / 15 min per IP. `robots.txt`: `User-agent: *` and the hard-coded sitemap URL |
| vCard | `public/contact/nusszopf-vcard.vcf` | "Team Nusszopf", ORG Nusszopf, `noreply@` and `mail@nusszopf.org`, `https://nusszopf.org`, the slogan |

## Decisions and deviations

| # | Item | Class | Notes |
|---|---|---|---|
| 1 | Home copy, logos and sections reproduced verbatim, Contest and Fellows included | **Preserve** (A-5) | The dated Contest text and the Vercel/Auth0/Sanity sponsor row are the maintainer's explicit choice; revisit before the release candidate |
| 2 | `CarouselSection` absent; no "create project" CTA on Home | **Preserve** | Both absent historically; `docs/design/screen-specs.md` corrected |
| 3 | Operator mailbox is configuration: `NUSSZOPF_CONTACT_EMAIL` (falls back to `MAIL_FROM_ADDRESS`) | **Replace** (A-5, adopted) | Every *displayed* `mail@nusszopf.org` (error page, Home, newsletter pages, Profile, mails, report link, error toasts) now reads `config('nusszopf.contact_email')`. The stored `projects.contact` marker `Project::NUSSZOPF_CONTACT` (historical `NZ_EMAIL`) is a data value, not an address shown to anyone, and is unchanged so existing rows keep their meaning |
| 4 | The vCard is generated from this instance's identity and linked again (Home, Profile, mail footer) | **Replace** (closes the slice 6/8 deferral) | `/contact/nusszopf-vcard.vcf`: the historical card with the contact address, `MAIL_FROM_ADDRESS` and `APP_URL` substituted |
| 5 | "Powered by Vercel" badge not reproduced; the default footer keeps its band and height | **Replace** | The badge states the hosting platform; a self-hosted instance does not run on Vercel. Same reasoning as the Auth0 badge (seventh slice, decision 11). The Fellows sponsor row on Home is content and stays (decision 1) |
| 6 | Legal pages render operator Markdown files from `NUSSZOPF_LEGAL_PATH` (default `<app>/legal`), with a "not configured" notice when a file is missing | **Replace** (A-4) | `legal-notice.md`, `legal-policy.md`, `privacy.md`; raw HTML escaped. Historical texts ship only as labelled examples under `docs/deployment/legal-examples/`, with the Auth0/SendGrid/Visitor Analytics sections removed |
| 7 | Error pages for 404, 500 and every other 4xx/5xx; a render failure is a 500 | **Replace** | The React `ErrorBoundary` has no server-rendered equivalent; an exception while rendering becomes the 500 page, so its heading carries "500 – " where the boundary showed none |
| 8 | Sitemap and `robots.txt` use `APP_URL` | **Fix** (BUG-035) | Historically hard-coded to `https://nusszopf.org` |
| 9 | `og:url` absolute; placeholder Twitter handles dropped | **Fix** (BUG-036) | `og:url` was the bare path; `@handle`/`@site` were next-seo's example values |
| 10 | Search-engine verification tags and Visitor Analytics not reproduced | **Replace** (A-5, adopted) | They belong to the original operator's accounts; analytics was decided out |
| 11 | Sitemap throttle 10 / 15 min per IP | **Preserve** | |
| 12 | `noindex` on Profile, My projects, project create and edit | **Preserve** | Previously only the newsletter pages set it |

## Implementation

Filled in as the slice lands.

## Test map

Filled in as the slice lands.

## Remaining gaps

Filled in at close-out.
