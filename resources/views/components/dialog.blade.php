@props(['label', 'ref' => null])

{{--
    Dialog.organism.js + Dialog.css (@reach/dialog): a dimmed full-screen
    overlay (`hsla(0, 0%, 0%, 0.2)`, `animate-opacityFade`) over a centered
    panel that is full-screen on phones and a `max-w-xl` rounded card from `sm`
    up (`sm:animate-scaleFade`). The panel takes its color from `class`.
    Reach trapped focus and locked page scroll while open — `x-trap.noscroll`
    does the same; the caller decides when it is open (a conditionally rendered
    dialog is always open; a pre-rendered one passes `x-show` and `x-trap`).
    Dismissal (overlay click, Escape) belongs to the caller too: the request
    edit dialog historically had none.
--}}
<div
    x-init="nzDialogFocus($el)"
    {{ $attributes->except('class')->class(['fixed top-0 bottom-0 left-0 right-0 z-30 w-screen overflow-auto animate-opacity-fade']) }}
    style="background: hsla(0, 0%, 0%, 0.2)"
>
    <div
        role="dialog"
        aria-modal="true"
        aria-label="{{ $label }}"
        tabindex="-1"
        @if ($ref) x-ref="{{ $ref }}" @endif
        class="w-full h-auto mx-auto outline-none sm:max-w-xl sm:my-12 sm:animate-scale-fade"
    >
        <div {{ $attributes->only('class')->class(['w-screen h-auto min-h-screen sm:min-h-0 pt-10 pb-12 px-6 sm:px-8 sm:rounded-lg sm:max-w-xl']) }}>
            {{ $slot }}
        </div>
    </div>
</div>
