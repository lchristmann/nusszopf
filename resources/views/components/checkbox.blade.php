@props(['disabled' => false])

{{--
    Checkbox.atom.js: a visually-hidden native checkbox (keeps keyboard and
    screen-reader semantics) plus a Feather `Square` that swaps for
    `CheckSquare` once checked — the check mark is the icon itself, there is no
    bordered box. `class` lands on the inline-flex row, as historically.
--}}
<label>
    <input type="checkbox" class="sr-only peer" @disabled($disabled) {{ $attributes->except('class') }} />
    <span @class([
        'inline-flex peer-focus-visible:[&>svg]:rounded-sm peer-focus-visible:[&>svg]:outline peer-focus-visible:[&>svg]:outline-2 peer-checked:[&>.nz-cb-off]:hidden peer-checked:[&>.nz-cb-on]:block',
        'opacity-50 cursor-default' => $disabled,
        'cursor-pointer' => ! $disabled,
        $attributes->get('class'),
    ])>
        <x-icon name="square" class="flex-shrink-0 mt-px nz-cb-off" />
        <x-icon name="check-square" class="flex-shrink-0 mt-px hidden nz-cb-on" />
        <x-text as="span" variant="textSm" class="ml-2">{{ $slot }}</x-text>
    </span>
</label>
