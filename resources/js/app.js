import './rich-text-editor';
import './avatar-cropper';

/**
 * Toast notifications — the app-wide async-feedback mechanism
 * (docs/design/states.md). Reproduces Toast.molecule.js's markup/classes
 * verbatim: a `livid`-colored card, an icon per type (loading/success/error),
 * dismissible by click. This is plain DOM/JS, not a framework dependency —
 * per CLAUDE.md's "smallest client-side solution" instruction.
 */
const ICONS = {
    loading: '<svg class="animate-spin" width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>',
    success: '<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>',
    error: '<svg width="25" height="25" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>',
};

window.nzToast = function nzToast(type, message) {
    const container = document.getElementById('nz-toasts');
    if (!container) return null;

    const el = document.createElement('button');
    el.type = 'button';
    el.className = 'w-full sm:w-96 bg-livid-300 rounded-md flex items-start justify-between p-5 text-livid-800 shadow-md text-left';
    el.innerHTML = `
        <div class="flex items-start">
            <span class="flex-shrink-0 mr-2">${ICONS[type] ?? ICONS.success}</span>
            <span class="nz-text-sm leading-snug text-left"></span>
        </div>
        <span class="flex-shrink-0 ml-5" aria-hidden="true">&times;</span>
    `;
    // Text, never markup: every message today is a fixed string, and none may ever become HTML (P-4, SEC-11).
    el.querySelector('.nz-text-sm').textContent = message;
    el.className += ' mb-2 nz-toast-in';
    const dimOlder = () => {
        // Toasts.service.js: every toast but the newest is `opacity-50`.
        const toasts = [...container.children];
        toasts.forEach((toast, index) => toast.classList.toggle('opacity-50', index !== toasts.length - 1));
    };
    const dismiss = () => {
        el.remove();
        dimOlder();
    };
    el.addEventListener('click', dismiss);
    container.appendChild(el);
    dimOlder();

    // Toasts.service.js's AUTO_CLOSE_MS = 3000, applied unconditionally to
    // every toast including `loading` — preserved exactly, not "fixed" into
    // excluding loading toasts, per this project's preserve-unless-
    // demonstrably-broken default.
    setTimeout(dismiss, 3000);

    return el;
};

document.addEventListener('livewire:init', () => {
    Livewire.on('toast', ({ type, message }) => window.nzToast(type, message));
});

// A toast flashed into the session before a full-page redirect (x-toast-container).
document.addEventListener('DOMContentLoaded', () => {
    const flashed = document.getElementById('nz-toasts')?.dataset.flashToast;
    if (flashed) {
        const toast = JSON.parse(flashed);
        window.nzToast(toast.type, toast.message);
    }
});

// The two former inline `onclick` handlers, which the Content-Security-Policy blocks (P-4, SEC-06):
// the nav header's "Zurück" on Privacy (`router.back()`) and the project page's banner close button.
document.addEventListener('click', (event) => {
    const back = event.target.closest('[data-history-back]');
    if (back) {
        event.preventDefault();
        history.back();
        return;
    }

    const hide = event.target.closest('[data-hide]');
    if (hide) {
        document.getElementById(hide.dataset.hide)?.classList.add('hidden');
    }
});

/**
 * Project-detail "Teilen" (`handleShare` in pages/projects/[id].js): the native
 * share sheet where the browser has one; otherwise (or if sharing fails for
 * any reason but the user dismissing it) the page URL is copied and a success
 * toast confirms it.
 */
window.nzShare = async function nzShare(title) {
    const url = window.location.href;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(url);
        } catch {
            const dummy = document.createElement('input');
            document.body.appendChild(dummy);
            dummy.value = url;
            dummy.select();
            document.execCommand('copy');
            document.body.removeChild(dummy);
        }
        window.nzToast('success', 'Link zum Teilen kopiert!');
    };

    if (typeof navigator.share === 'function') {
        try {
            await navigator.share({ title, url });
        } catch (error) {
            if (error.name === 'AbortError') return;
            await copy();
        }
    } else {
        await copy();
    }
};

/**
 * `Masonry.organism.js` (react-masonry-css): cards are dealt round-robin into columns — card i
 * goes to column i mod n, each column stacking its cards — so the reading order runs left to right.
 * `breakpoints` is its `breakpointCols` ({ default, <max width>: n }, against the window width) and
 * `gap` the pixel gap between columns and rows. The cards are positioned absolutely instead of being
 * moved into column elements, so Livewire keeps morphing the list it rendered. Without JavaScript the
 * container's own CSS columns stay in effect. The container carries `data-nz-masonry`; once Livewire
 * removes it or morphs that attribute away, the layout lets go of the element.
 */
window.nzMasonry = function nzMasonry(el, breakpoints, gap) {
    const columnCount = () => {
        const limits = Object.keys(breakpoints).filter((key) => key !== 'default').map(Number).sort((a, b) => a - b);
        const limit = limits.find((max) => window.innerWidth <= max);
        return limit === undefined ? breakpoints.default : breakpoints[limit];
    };

    let writing = false;
    let mutations;
    const resize = new ResizeObserver(() => { if (!writing) layout(); });
    const teardown = () => {
        resize.disconnect();
        mutations.disconnect();
        window.removeEventListener('resize', layout);
        el.style.removeProperty('columns');
        el.style.removeProperty('position');
        el.style.removeProperty('height');
        [...el.children].forEach((card) => ['position', 'width', 'margin', 'left', 'top'].forEach((property) => card.style.removeProperty(property)));
    };
    function layout() {
        if (!el.isConnected || !el.hasAttribute('data-nz-masonry')) {
            teardown();
            return;
        }
        writing = true;
        const cards = [...el.children];
        const columns = columnCount();
        const width = (el.clientWidth - gap * (columns - 1)) / columns;
        el.style.columns = 'auto';
        el.style.position = 'relative';
        cards.forEach((card) => Object.assign(card.style, { position: 'absolute', width: `${width}px`, margin: '0' }));
        const heights = new Array(columns).fill(0);
        cards.forEach((card, index) => {
            const column = index % columns;
            card.style.left = `${column * (width + gap)}px`;
            card.style.top = `${heights[column]}px`;
            heights[column] += card.offsetHeight + gap;
        });
        el.style.height = `${Math.max(0, ...heights.map((height) => height - gap))}px`;
        el.dataset.masonry = String(columns);
        queueMicrotask(() => { writing = false; });
    }

    const watch = () => [...el.children].forEach((card) => resize.observe(card));
    mutations = new MutationObserver(() => {
        if (writing) return;
        watch();
        layout();
    });
    mutations.observe(el, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class', 'data-nz-masonry'] });
    window.addEventListener('resize', layout);
    document.fonts?.ready.then(layout);
    watch();
    layout();
};

/**
 * `x-dialog` (decision A-7): when a pre-rendered dialog is shown (`x-show` clears its `display`), focus moves to
 * its first focusable element if `x-trap` has not already put it inside, and returns to the element that opened
 * it when the dialog is hidden again. WebKit sometimes activates the trap before the dialog is laid out, which
 * leaves focus on the opener and, on closing, nowhere.
 */
const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]):not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])';

window.nzDialogFocus = function nzDialogFocus(el) {
    let opener = null;
    let shown = el.style.display !== 'none';
    new MutationObserver(() => {
        const visible = el.style.display !== 'none';
        if (visible === shown) return;
        shown = visible;
        if (visible) {
            if (!el.contains(document.activeElement)) opener = document.activeElement;
            window.nzFocusInto(el, FOCUSABLE);
        } else {
            const target = opener;
            opener = null;
            requestAnimationFrame(() => {
                const lost = document.activeElement === document.body || el.contains(document.activeElement);
                if (lost && target?.isConnected) target.focus();
            });
        }
    }).observe(el, { attributes: true, attributeFilter: ['style'] });
};

/**
 * Moves focus into a just-opened popover (decision A-7) once it can take it: `x-show` with a transition applies
 * `display` a tick later, and WebKit ignores focus() on an element that is not displayed yet. Retries for about a
 * third of a second.
 */
window.nzFocusInto = function nzFocusInto(container, selector) {
    let tries = 0;
    const attempt = () => {
        if (container.contains(document.activeElement)) return;
        container.querySelector(selector)?.focus();
        if (!container.contains(document.activeElement) && ++tries < 20) setTimeout(attempt, 16);
    };
    requestAnimationFrame(attempt);
};

/**
 * iOS Safari only turns a tap into a `click` when the tapped element or an ancestor listens for clicks, so a tap on
 * blank page never reached the `click.outside` handlers of the nav menu, the card menus and the search filter, and
 * they stayed open (P-5, DEV-01 in docs/release/parity/P-05-browsers-devices.md). The historical React app listened
 * at its root, which made every tap a click; this listener does the same.
 */
document.body.addEventListener('click', () => {});
