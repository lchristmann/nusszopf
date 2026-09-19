import './rich-text-editor';

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
            <span class="nz-text-sm leading-snug text-left">${message}</span>
        </div>
        <span class="flex-shrink-0 ml-5" aria-hidden="true">&times;</span>
    `;
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
