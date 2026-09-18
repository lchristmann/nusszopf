{{--
    docs/design/screens.md: legal copy is CMS-driven historically and
    explicitly "Unknown pending a dedicated content pass — do not invent."
    This is a deliberate placeholder, not an attempt at real legal text.
--}}
<x-layout :title="$title">
    <x-frame class="py-16 text-center">
        <x-text as="h1" variant="titleMd">{{ $title }}</x-text>
        <x-text as="p" variant="textSm" class="mt-4 text-steel-600">
            Dieser Inhalt wird in einer kommenden Version ergänzt.
        </x-text>
    </x-frame>
</x-layout>
