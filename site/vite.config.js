import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';

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
        transformIndexHtml(html) {
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

export default defineConfig({
    plugins: [tailwindcss(), quickstart(), barlowLicense()],
    build: {
        outDir: 'dist',
        // The notice above pairs with Vite's own per-package legal-comment collector for the tiny
        // amount of JS this page ships (none of its own — Vite's client-side helpers only).
        license: { fileName: 'THIRD-PARTY-LICENSES.txt' },
    },
});
