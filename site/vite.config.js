import { readdirSync, readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { buildDocs, OUTPUT_DIR, SOURCE_DIR } from './scripts/build-docs.mjs';

// Transcludes the Docker self-hosting snippet from the repo root README.md's "Quick start" section
// (between the `<!-- quickstart:start -->` / `<!-- quickstart:end -->` markers) into index.html at
// build time, so this page can never hand-retype it out of sync with the real installation
// instructions. If the markers move or disappear, the build fails loudly instead of shipping a
// stale snippet — see site/README.md.
function quickstart() {
    const START = '<!-- quickstart:start -->';
    const END = '<!-- quickstart:end -->';

    return {
        name: 'nusszopf:quickstart',
        transformIndexHtml(html, ctx) {
            // Nur die Projektseite hat den Platzhalter, nicht die Seiten des Handbuchs.
            if (ctx.filename !== fileURLToPath(new URL('./index.html', import.meta.url))) return html;

            const readmePath = fileURLToPath(new URL('../README.md', import.meta.url));
            const readme = readFileSync(readmePath, 'utf-8');
            const start = readme.indexOf(START);
            const end = readme.indexOf(END);

            if (start === -1 || end === -1 || end < start) {
                throw new Error(
                    'site: could not find the "<!-- quickstart:start/end -->" markers around the Docker snippet ' +
                        'in the repo root README.md. It may have been reworded or moved — update the markers there, ' +
                        'or this plugin (site/vite.config.js), to match.',
                );
            }

            const codeMatch = readme.slice(start, end).match(/```sh\n([\s\S]*?)```/);
            if (!codeMatch) {
                throw new Error(
                    'site: found the quickstart markers in README.md but no ```sh code fence between them.',
                );
            }

            const snippet = codeMatch[1]
                .trimEnd()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');

            if (!html.includes('<!--QUICKSTART-->')) {
                throw new Error('site: index.html has no <!--QUICKSTART--> placeholder to inject the snippet into.');
            }

            return html.replace('<!--QUICKSTART-->', snippet);
        },
    };
}

// This page bundles the Barlow font (SIL OFL 1.1, see ../resources/css/tokens.css's --font-sans
// and site/README.md), and the license requires its notice to travel with the distributed copy —
// the same requirement and the same fix the application's own vite.config.js applies
// (docs/legal/provenance.md, finding P15-03).
function barlowLicense() {
    return {
        name: 'nusszopf:barlow-license',
        generateBundle() {
            this.emitFile({
                type: 'asset',
                fileName: 'LICENSE-barlow.txt',
                source: readFileSync(
                    fileURLToPath(new URL('./node_modules/@fontsource/barlow/LICENSE', import.meta.url)),
                    'utf-8',
                ),
            });
        },
    };
}

// Das Handbuch (docs/handbuch/*.md) wird vor dem Bauen zu HTML-Seiten unter site/handbuch/ gerendert
// (scripts/build-docs.mjs; ein kaputter Link bricht den Build ab). Jede Seite ist ein eigener Einstiegspunkt.
buildDocs();
const handbuch = Object.fromEntries(
    readdirSync(OUTPUT_DIR)
        .filter((f) => f.endsWith('.html'))
        .map((f) => [`handbuch-${f.replace(/\.html$/, '')}`, fileURLToPath(new URL(`./handbuch/${f}`, import.meta.url))]),
);

// Im Entwicklungsserver: Änderungen an den Markdown-Seiten rendern neu und laden die Seite neu.
function handbuchWatcher() {
    return {
        name: 'nusszopf:handbuch-watch',
        configureServer(server) {
            server.watcher.add(SOURCE_DIR);
            server.watcher.on('change', (file) => {
                if (!file.startsWith(SOURCE_DIR) || !file.endsWith('.md')) return;
                try {
                    buildDocs();
                    server.ws.send({ type: 'full-reload' });
                } catch (error) {
                    server.config.logger.error(String(error));
                }
            });
        },
    };
}

export default defineConfig({
    // GitHub Pages serves a project site (as opposed to a user/org root site) from a subpath —
    // https://lchristmann.github.io/nusszopf/, not the domain root — so an absolute base ('/')
    // would 404 every asset. A relative base works unconditionally for this single-page site: every
    // asset it references is served from the same directory as index.html, at any depth.
    base: './',
    plugins: [tailwindcss(), quickstart(), barlowLicense(), handbuchWatcher()],
    build: {
        outDir: 'dist',
        rollupOptions: {
            input: { main: fileURLToPath(new URL('./index.html', import.meta.url)), ...handbuch },
        },
        // The notice above pairs with Vite's own per-package legal-comment collector for the tiny
        // amount of JS this page ships (none of its own — Vite's client-side helpers only).
        license: { fileName: 'THIRD-PARTY-LICENSES.txt' },
    },
});
