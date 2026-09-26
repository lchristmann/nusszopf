import { readFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// The build ships third-party code and fonts to every visitor, and their licenses (MIT, SIL OFL 1.1) require the
// notice to travel with them. `build.license` writes the notice of every bundled JavaScript package; the Barlow
// font files are CSS assets Vite does not track, so their license text is emitted next to it.
// Both land in public/build/ and so in the production image (NOTICE names them).
const barlowLicense = () => ({
    name: 'nusszopf:barlow-license',
    generateBundle() {
        this.emitFile({
            type: 'asset',
            fileName: 'LICENSE-barlow.txt',
            source: readFileSync('node_modules/@fontsource/barlow/LICENSE', 'utf-8'),
        });
    },
});

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        barlowLicense(),
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
