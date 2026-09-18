@props(['info' => null])

{{--
    Reproduces the historical FieldTitle (label + optional info affordance).
    The historical component revealed `info` via a hover/click Popover
    whose internals are documented as "Listed, not Read" — unknown exact
    trigger/visual behavior (docs/design/components.md). Rather than invent
    a specific popover interaction from no evidence, this renders the same
    information as a small, always-visible caption under the label — a
    documented, deliberate simplification of an unread component, not a
    silent guess at its behavior.
--}}

<div {{ $attributes }}>
    <x-text as="span" variant="textSmMedium" class="block text-steel-800">{{ $slot }}</x-text>
    @if ($info)
        <x-text as="span" variant="textXs" class="block mt-1 text-steel-500">{{ $info }}</x-text>
    @endif
</div>
