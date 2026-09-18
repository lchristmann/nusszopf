{{--
    The historical app-wide async-feedback mechanism (docs/design/states.md,
    "Toast/notification states"): a loading toast fires on submit, replaced
    by success/error on completion. Rendered here as a fixed bottom-right
    stack; actual toast elements are created by resources/js/app.js's
    `nzToast()`, either from a Livewire-dispatched `toast` browser event
    (`$this->dispatch('toast', type: 'success', message: '...')`) or from a
    one-off flashed session value after a full-page redirect. Markup/classes
    reproduce `Toast.molecule.js` (bg-livid-300, textSm, dismiss-on-click).
--}}

<div id="nz-toasts" class="fixed z-50 bottom-0 right-0 m-6 flex flex-col gap-3 items-end" aria-live="polite"></div>

@if (session('toast'))
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toast = @json(session('toast'));
            window.nzToast(toast.type, toast.message);
        });
    </script>
@endif
