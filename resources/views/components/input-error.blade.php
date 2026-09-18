@props(['message'])

@if ($message)
    <x-text as="span" variant="textXs" {{ $attributes->class(['block mt-2 ml-4 italic text-warning-700']) }}>
        {{ $message }}
    </x-text>
@endif
