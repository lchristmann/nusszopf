@props(['errorFor' => null, 'disabled' => false, 'vertical' => true])
@php
    // Decision A-7: an error message is tied to its field (App\Support\FieldError).
    $attributes = $attributes->merge(\App\Support\FieldError::attributes(\App\Support\FieldError::field($attributes, $errorFor), $errors ?? null));
@endphp

{{--
    Radiobox.atom.js: a visually-hidden native radio (kept for keyboard and
    screen-reader semantics) plus a `border-2 rounded-full` ring with an
    inner `w-3.5 h-3.5` dot in `currentColor` when checked. The label is
    `textSm`; `vertical` appends the historical `<br>`. `class` lands on the
    inline-flex row, as historically.
--}}
<label>
    <input type="radio" class="sr-only peer" @disabled($disabled) {{ $attributes->except('class') }} />
    <span @class([
        'inline-flex peer-focus-visible:[&>span:first-child]:ring-2 peer-focus-visible:[&>span:first-child]:ring-current peer-checked:[&>span:first-child>span]:bg-current',
        'opacity-50 cursor-default' => $disabled,
        'cursor-pointer' => ! $disabled,
        $attributes->get('class'),
    ])>
        <span aria-hidden="true" class="inline-flex items-center justify-center flex-shrink-0 w-6 h-6 mt-0.5 border-2 border-current rounded-full">
            <span class="rounded-full w-3.5 h-3.5 bg-transparent"></span>
        </span>
        <x-text as="span" variant="textSm" class="ml-2">{{ $slot }}</x-text>
    </span>
</label>
@if ($vertical)
    <br />
@endif
