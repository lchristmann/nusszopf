import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// The build ships third-party code and fonts to every visitor, and their licenses (MIT, SIL OFL 1.1) require the
// notice to travel with them. `build.license` writes the notice of every bundled JavaScript package; the Barlow
// font files are CSS assets Vite does not track, so their license text is emitted next to it.
// Both land in public/build/ and so in the production image (NOTICE names them).
//
// The same plugin publishes the two Barlow weights the e-mail layout uses under fixed, unhashed names
// (public/build/fonts/), because a mail that was sent last year must keep finding its font: the hashed names in
// public/build/assets/ change with every build. The layout's @font-face points at these (decision C6).
const barlowMailWeights = ['500', '700'];
const barlowFiles = 'node_modules/@fontsource/barlow';
const barlow = () => ({
    name: 'nusszopf:barlow',
    generateBundle() {
        this.emitFile({
            type: 'asset',
            fileName: 'LICENSE-barlow.txt',
            source: readFileSync(`${barlowFiles}/LICENSE`, 'utf-8'),
        });
        for (const weight of barlowMailWeights) {
            this.emitFile({
                type: 'asset',
                fileName: `fonts/barlow-latin-${weight}.woff2`,
                source: readFileSync(`${barlowFiles}/files/barlow-latin-${weight}-normal.woff2`),
            });
        }
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        barlow(),
    ],
    build: {
        license: { fileName: 'THIRD-PARTY-LICENSES.txt' },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
