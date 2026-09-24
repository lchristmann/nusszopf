{{--
    The historical app-wide async-feedback mechanism (docs/design/states.md,
    "Toast/notification states"): a loading toast fires on submit, replaced
    by success/error on completion. Positioning/timing confirmed against
    `ui-library/services/Toasts.service.js` (SR-009, docs/rewrite/specification-review.md,
    resolved in this verification pass): a fixed top-right stack anchored
    just below the NavHeader (`top-10 lg:top-12`, matching NavHeader's own
    `h-10 lg:h-12`), not bottom-right as previously guessed. Actual toast
    elements are created by resources/js/app.js's `nzToast()`, either from a
    Livewire-dispatched `toast` browser event
    (`$this->dispatch('toast', type: 'success', message: '...')`) or from a
    one-off flashed session value after a full-page redirect. Markup/classes
    reproduce `Toast.molecule.js` (bg-livid-300, textSm, dismiss-on-click);
    auto-dismiss is 3000ms, matching `Toasts.service.js`'s `AUTO_CLOSE_MS`.
--}}

{{-- A flashed toast travels as data, not as an inline script, which the Content-Security-Policy blocks (P-4, SEC-06). --}}
<div id="nz-toasts" class="fixed z-50 right-0 w-full p-3 top-10 lg:top-12 sm:w-auto" aria-live="polite" @if (session('toast')) data-flash-toast="{{ json_encode(session('toast')) }}" @endif></div>
