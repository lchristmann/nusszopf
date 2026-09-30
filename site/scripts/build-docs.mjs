// Rendert das Handbuch (docs/handbuch/*.md) zu statischen HTML-Seiten unter site/handbuch/.
//
// Die Markdown-Dateien sind die einzige Quelle: Sie lesen sich auf GitHub genauso wie auf der
// Projektseite. Dieser Generator fügt nur Navigation, Inhaltsverzeichnis, Hinweiskästen und
// Kopier-Knöpfe hinzu und prüft dabei alle Links. Ein kaputter Link bricht den Build ab
// (siehe site/README.md).
import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, statSync, writeFileSync } from 'node:fs';
import { dirname, join, posix, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import MarkdownIt from 'markdown-it';
import anchor from 'markdown-it-anchor';

const SITE = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const REPO = resolve(SITE, '..');
export const SOURCE_DIR = join(REPO, 'docs', 'handbuch');
export const OUTPUT_DIR = join(SITE, 'handbuch');

const GITHUB = 'https://github.com/lchristmann/nusszopf';

// Reihenfolge und Farbe der Bereiche in der Navigation. Die Farben sind Token aus tokens.css.
const GROUPS = [
    { id: 'einstieg', title: 'Einstieg', band: 'bg-turquoise-300' },
    { id: 'betreiben', title: 'Betreiben', band: 'bg-yellow-250' },
    { id: 'entwickeln', title: 'Entwickeln', band: 'bg-blue-300' },
    { id: 'hintergrund', title: 'Hintergrund', band: 'bg-lilac-300' },
];

// Beschriftung der Code-Blöcke: ```sh server, ```sh dev, ```sh (neutral).
const FENCE_LABELS = {
    server: { text: 'Auf dem Server', tone: 'server' },
    dev: { text: 'Lokale Entwicklung', tone: 'dev' },
    env: { text: '.env', tone: 'plain' },
    yaml: { text: 'YAML', tone: 'plain' },
    sql: { text: 'SQL', tone: 'plain' },
    json: { text: 'JSON', tone: 'plain' },
    php: { text: 'PHP', tone: 'plain' },
    text: { text: '', tone: 'plain' },
};

const CALLOUTS = {
    NOTE: { cls: 'note', label: 'Hinweis' },
    TIP: { cls: 'tip', label: 'Tipp' },
    IMPORTANT: { cls: 'important', label: 'Wichtig' },
    WARNING: { cls: 'warning', label: 'Achtung' },
    CAUTION: { cls: 'caution', label: 'Vorsicht' },
};

const escapeHtml = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

// Wie GitHub: Kleinbuchstaben, Satzzeichen weg, Leerzeichen zu Bindestrichen, Umlaute bleiben.
// So funktionieren dieselben Sprungmarken auf GitHub und auf der Projektseite.
export function slugify(text) {
    return text
        .trim()
        .toLowerCase()
        .replace(/[^\p{L}\p{N}\s_-]/gu, '')
        .replace(/\s/g, '-');
}

function parseFrontMatter(raw, file) {
    const match = raw.match(/^---\n([\s\S]*?)\n---\n?/);
    if (!match) throw new Error(`${file}: Front Matter (--- … ---) fehlt.`);
    const data = {};
    for (const line of match[1].split('\n')) {
        const m = line.match(/^([a-zA-Z_]+):\s*(.*)$/);
        if (m) data[m[1]] = m[2].trim().replace(/^"(.*)"$/, '$1');
    }
    for (const key of ['titel', 'beschreibung', 'gruppe', 'reihenfolge']) {
        if (!data[key]) throw new Error(`${file}: Front Matter „${key}“ fehlt.`);
    }
    if (!GROUPS.some((g) => g.id === data.gruppe)) throw new Error(`${file}: unbekannte Gruppe „${data.gruppe}“.`);
    data.reihenfolge = Number(data.reihenfolge);
    return { data, body: raw.slice(match[0].length) };
}

// Färbt Kommentare in Shell-/.env-Blöcken ein; alles andere bleibt unverändert.
function highlightShell(code) {
    return code
        .split('\n')
        .map((line) => {
            let quote = null;
            for (let i = 0; i < line.length; i++) {
                const c = line[i];
                if (quote) {
                    if (c === quote) quote = null;
                } else if (c === '"' || c === "'") {
                    quote = c;
                } else if (c === '#' && (i === 0 || /\s/.test(line[i - 1]))) {
                    return `${escapeHtml(line.slice(0, i))}<span class="tok-comment">${escapeHtml(line.slice(i))}</span>`;
                }
            }
            return escapeHtml(line);
        })
        .join('\n');
}

function createRenderer(pageIndex) {
    const md = new MarkdownIt({ html: true, linkify: false, typographer: false });
    md.use(anchor, { slugify, tabIndex: false });

    // Code-Blöcke mit Beschriftung und Kopier-Knopf.
    md.renderer.rules.fence = (tokens, idx) => {
        const token = tokens[idx];
        const [lang = '', flag = ''] = token.info.trim().split(/\s+/);
        const label = FENCE_LABELS[flag] ?? FENCE_LABELS[lang] ?? { text: '', tone: 'plain' };
        const shellLike = ['sh', 'bash', 'shell', 'env', ''].includes(lang);
        const code = token.content.replace(/\n$/, '');
        const body = shellLike ? highlightShell(code) : escapeHtml(code);
        const head = label.text ? `<span class="code-label code-label-${label.tone}">${label.text}</span>` : '<span></span>';
        return (
            `<figure class="code" data-tone="${label.tone}"><figcaption>${head}` +
            `<button type="button" class="code-copy" data-copy>Kopieren</button></figcaption>` +
            `<pre><code>${body}</code></pre></figure>\n`
        );
    };

    // Tabellen scrollen auf schmalen Bildschirmen für sich, nicht die ganze Seite.
    md.renderer.rules.table_open = () => '<div class="table-wrap"><table>\n';
    md.renderer.rules.table_close = () => '</table></div>\n';

    // GitHub-Hinweiskästen (> [!NOTE] …) werden zu Karten mit deutscher Überschrift.
    md.core.ruler.after('block', 'callouts', (state) => {
        const t = state.tokens;
        for (let i = 0; i < t.length; i++) {
            if (t[i].type !== 'blockquote_open' || t[i + 1]?.type !== 'paragraph_open' || t[i + 2]?.type !== 'inline') continue;
            const inline = t[i + 2];
            const m = inline.content.match(/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\][ \t]*\n?/);
            if (!m) continue;
            inline.content = inline.content.slice(m[0].length);
            t[i].attrJoin('class', `callout callout-${CALLOUTS[m[1]].cls}`);
            t[i].meta = { label: CALLOUTS[m[1]].label };
        }
    });
    md.renderer.rules.blockquote_open = (tokens, idx, options, env, self) => {
        const token = tokens[idx];
        const label = token.meta?.label;
        return label
            ? `<aside${self.renderAttrs(token)}><p class="callout-title">${label}</p>\n`
            : '<blockquote>\n';
    };
    md.renderer.rules.blockquote_close = (tokens, idx) => {
        // Das Gegenstück zum öffnenden Token finden, um <aside> von <blockquote> zu unterscheiden.
        let depth = 0;
        for (let i = idx; i >= 0; i--) {
            if (tokens[i].type === 'blockquote_close') depth++;
            if (tokens[i].type === 'blockquote_open' && --depth === 0) return tokens[i].meta?.label ? '</aside>\n' : '</blockquote>\n';
        }
        return '</blockquote>\n';
    };

    return md;
}

// Links: Handbuch-Seiten → .html, andere Repository-Dateien → GitHub. Alles wird geprüft.
function rewriteLinks(tokens, page, pageIndex, problems) {
    const walk = (list) => {
        for (const token of list) {
            if (token.type === 'link_open') {
                const href = token.attrGet('href') ?? '';
                if (/^(https?:|mailto:|tel:)/.test(href)) {
                    token.attrSet('rel', 'noopener');
                    continue;
                }
                if (href.startsWith('#')) {
                    page.anchorLinks.push({ href: decodeURIComponent(href.slice(1)), from: page.file });
                    continue;
                }
                const [pathPart, rawHash = ''] = href.split('#');
                const hash = decodeURIComponent(rawHash);
                const target = resolve(dirname(page.path), pathPart);
                const rel = relative(REPO, target).split(sep).join('/');
                if (rel.startsWith('..')) {
                    problems.push(`${page.file}: Link verlässt das Repository: ${href}`);
                    continue;
                }
                if (target.startsWith(SOURCE_DIR + sep) && target.endsWith('.md')) {
                    const slug = posix.basename(rel, '.md');
                    if (!pageIndex.has(slug)) {
                        problems.push(`${page.file}: Link auf unbekannte Handbuch-Seite: ${href}`);
                        continue;
                    }
                    page.pageLinks.push({ slug, hash, from: page.file, href });
                    token.attrSet('href', `${slug === 'README' ? 'index' : slug}.html${rawHash ? `#${rawHash}` : ''}`);
                } else if (existsSync(target)) {
                    const isDir = statSync(target).isDirectory();
                    token.attrSet('href', `${GITHUB}/${isDir ? 'tree' : 'blob'}/main/${rel}${rawHash ? `#${rawHash}` : ''}`);
                    token.attrSet('rel', 'noopener');
                } else {
                    problems.push(`${page.file}: Link-Ziel existiert nicht: ${href}`);
                }
            }
            if (token.children) walk(token.children);
        }
    };
    walk(tokens);
}

function collectHeadings(tokens) {
    const out = [];
    tokens.forEach((token, i) => {
        if (token.type === 'heading_open' && (token.tag === 'h2' || token.tag === 'h3')) {
            const text = tokens[i + 1].children.filter((c) => c.type === 'text' || c.type === 'code_inline').map((c) => c.content).join('');
            out.push({ level: Number(token.tag[1]), id: token.attrGet('id'), text });
        }
    });
    return out;
}

function nav(pages, current) {
    return GROUPS.map((group) => {
        const items = pages.filter((p) => p.data.gruppe === group.id);
        if (!items.length) return '';
        const links = items
            .map((p) => {
                const active = p.slug === current.slug;
                return `<li><a href="${p.href}"${active ? ' aria-current="page"' : ''} class="doc-nav-link">${escapeHtml(p.data.titel)}</a></li>`;
            })
            .join('\n');
        return `<div class="doc-nav-group"><p class="doc-nav-title">${group.title}</p><ul>${links}</ul></div>`;
    }).join('\n');
}

function renderPage(page, pages, md) {
    const group = GROUPS.find((g) => g.id === page.data.gruppe);
    const env = {};
    const tokens = md.parse(page.body, env);
    const problems = [];
    page.anchorLinks = [];
    page.pageLinks = [];
    rewriteLinks(tokens, page, page.index, problems);
    const headings = collectHeadings(tokens);
    page.headingIds = new Set(headings.map((h) => h.id));
    // Auch h1/h4… sollen als Sprungziel gelten.
    tokens.forEach((t) => t.type === 'heading_open' && t.attrGet('id') && page.headingIds.add(t.attrGet('id')));
    const html = md.renderer.render(tokens, md.options, env);

    const pos = pages.indexOf(page);
    const prev = pos > 0 ? pages[pos - 1] : null;
    const next = pos < pages.length - 1 ? pages[pos + 1] : null;
    const toc = headings.length > 2
        ? `<nav class="doc-toc" aria-label="Auf dieser Seite"><p class="doc-nav-title">Auf dieser Seite</p><ul>${headings
              .map((h) => `<li class="toc-l${h.level}"><a href="#${h.id}">${escapeHtml(h.text)}</a></li>`)
              .join('')}</ul></nav>`
        : '';
    const pager = `<nav class="doc-pager" aria-label="Weiter im Handbuch">${
        prev ? `<a href="${prev.href}" rel="prev"><span>Zurück</span>${escapeHtml(prev.data.titel)}</a>` : '<span></span>'
    }${next ? `<a href="${next.href}" rel="next" class="doc-pager-next"><span>Weiter</span>${escapeHtml(next.data.titel)}</a>` : ''}</nav>`;
    const title = page.slug === 'README' ? 'Handbuch' : page.data.titel;

    page.html = `<!doctype html>
<!-- Generiert aus docs/handbuch/${page.file} von site/scripts/build-docs.mjs — nicht von Hand ändern. -->
<html lang="de">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>${escapeHtml(title)} — Nusszopf Handbuch</title>
        <meta name="description" content="${escapeHtml(page.data.beschreibung)}" />
        <link rel="icon" href="/favicon.ico" sizes="any" />
        <link rel="icon" type="image/png" sizes="32x32" href="/favicons/favicon-32x32.png" />
        <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
        <meta name="theme-color" content="#ffffff" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="${escapeHtml(title)} — Nusszopf Handbuch" />
        <meta property="og:description" content="${escapeHtml(page.data.beschreibung)}" />
        <link rel="stylesheet" href="../src/main.css" />
        <script type="module" src="../src/docs.js"></script>
    </head>
    <body class="bg-white text-steel-700">
        <a href="#inhalt" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2">Zum Inhalt springen</a>
        <header class="doc-header px-6 py-4 sm:px-10 lg:px-16">
            <div class="mx-auto flex max-w-[88rem] items-center justify-between gap-4">
                <a href="../index.html" class="flex items-center gap-2 text-steel-700" aria-label="Nusszopf, zur Projektseite">
                    <img src="../src/assets/icons/nusszopf-header-logo.svg" alt="" class="h-8 w-8" width="32" height="32" />
                    <span class="nz-title-sm-semi">Nusszopf</span>
                    <span class="nz-title-sm-semi text-steel-400">/ Handbuch</span>
                </a>
                <nav class="flex items-center gap-3" aria-label="Kopfzeile">
                    <a href="https://nusszopf.org" class="hidden text-lg sm:inline doc-plain-link">Demo</a>
                    <a href="${GITHUB}" class="nz-btn-steel inline-block cursor-pointer px-4 py-2 text-lg font-medium outline-none focus:outline-none">GitHub</a>
                </nav>
            </div>
        </header>

        <div class="${group.band} px-6 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto max-w-[88rem]">
                <p class="text-lg font-semibold text-steel-600">${group.title}</p>
                <h1 class="nz-title-lg mt-1">${escapeHtml(title)}</h1>
                <p class="nz-text-md mt-3 max-w-3xl">${escapeHtml(page.data.beschreibung)}</p>
            </div>
        </div>

        <div class="doc-layout mx-auto max-w-[88rem] px-6 sm:px-10 lg:px-16">
            <aside class="doc-side">
                <details class="doc-menu">
                    <summary>Alle Seiten des Handbuchs</summary>
                    <nav aria-label="Handbuch (Menü)">${nav(pages, page)}</nav>
                </details>
                <nav class="doc-sidebar" aria-label="Handbuch">${nav(pages, page)}</nav>
            </aside>
            <main id="inhalt" class="doc-main">
                <article class="doc">
${html}
                </article>
                ${pager}
                <p class="doc-edit"><a href="${GITHUB}/edit/main/docs/handbuch/${page.file}">Fehler gefunden? Diese Seite auf GitHub bearbeiten</a></p>
            </main>
            ${toc}
        </div>

        <footer class="bg-steel-200 px-6 py-8 sm:px-10 lg:px-16">
            <div class="mx-auto flex max-w-[88rem] flex-col gap-3 text-center sm:flex-row sm:justify-between sm:text-left">
                <p class="nz-text-sm">© Nusszopf-Mitwirkende. Lizenziert unter GPL-3.0-or-later.</p>
                <nav class="flex flex-wrap justify-center gap-4" aria-label="Fußzeile">
                    <a href="../index.html" class="border-b-2 nz-link-current nz-text-sm">Projektseite</a>
                    <a href="${GITHUB}/issues" class="border-b-2 nz-link-current nz-text-sm">Fehler melden</a>
                    <a href="${GITHUB}/releases" class="border-b-2 nz-link-current nz-text-sm">Versionen</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
`;
    return problems;
}

export function buildDocs() {
    const files = readdirSync(SOURCE_DIR).filter((f) => f.endsWith('.md')).sort();
    const pages = files.map((file) => {
        const path = join(SOURCE_DIR, file);
        const { data, body } = parseFrontMatter(readFileSync(path, 'utf-8'), file);
        const slug = file.replace(/\.md$/, '');
        return { file, path, slug, data, body, href: `${slug === 'README' ? 'index' : slug}.html` };
    });
    pages.sort((a, b) => GROUPS.findIndex((g) => g.id === a.data.gruppe) - GROUPS.findIndex((g) => g.id === b.data.gruppe) || a.data.reihenfolge - b.data.reihenfolge);
    const pageIndex = new Map(pages.map((p) => [p.slug, p]));
    pages.forEach((p) => (p.index = pageIndex));

    const md = createRenderer(pageIndex);
    const problems = pages.flatMap((page) => renderPage(page, pages, md));

    for (const page of pages) {
        for (const link of page.anchorLinks) {
            if (!page.headingIds.has(link.href)) problems.push(`${page.file}: Sprungmarke #${link.href} existiert nicht.`);
        }
        for (const link of page.pageLinks) {
            if (link.hash && !pageIndex.get(link.slug).headingIds.has(link.hash)) {
                problems.push(`${page.file}: Sprungmarke fehlt in ${link.slug}.md: ${link.href}`);
            }
        }
    }
    if (problems.length) throw new Error(`Handbuch: ${problems.length} Problem(e)\n  - ${problems.join('\n  - ')}`);

    rmSync(OUTPUT_DIR, { recursive: true, force: true });
    mkdirSync(OUTPUT_DIR, { recursive: true });
    for (const page of pages) writeFileSync(join(OUTPUT_DIR, page.href), page.html);
    return pages.map((p) => p.href);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const written = buildDocs();
    console.log(`Handbuch: ${written.length} Seiten nach site/handbuch/ geschrieben.`);
}
