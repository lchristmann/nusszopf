@props(['message', 'for' => null])

{{-- `for`: the field's validation key; the control carries `aria-describedby` to this id (App\Support\FieldError). --}}
@if ($message)
    <x-text as="span" variant="textXs" :id="$for ? \App\Support\FieldError::id($for) : null" {{ $attributes->class(['block mt-2 ml-4 italic text-warning-700']) }}>
        {{ $message }}
    </x-text>
@endif
