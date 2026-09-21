@props(['request', 'createdAt', 'contactHref', 'open', 'close'])

{{--
    RequestDialog.js (+ RequestDialog.theme.js): a request in full — its
    category's color, the `Request` icon and title, "Erstellt am …", the rich
    text with links in the category's `stone-*` color, and "Kontaktieren" /
    "Schließen". Pre-rendered and shown by the page's Alpine state (`open` is
    the show expression, `close` the Alpine statement that closes it): the
    request is public information of an already visible project, and opening
    it is instant, as historically.
--}}
@php
    [$background, $button, $link] = [
        'companions' => ['bg-red-200', 'bg-red-300', 'nz-link-stone-red'],
        'rooms' => ['bg-yellow-200', 'bg-yellow-300', 'nz-link-stone-yellow'],
        'materials' => ['bg-turquoise-200', 'bg-turquoise-300', 'nz-link-stone-turquoise'],
        'financials' => ['bg-blue-200', 'bg-blue-300', 'nz-link-stone-blue'],
        'others' => ['bg-pink-200', 'bg-pink-300', 'nz-link-stone-pink'],
    ][$request->category];
@endphp
<x-dialog
    label="Gesuche Informationen"
    :ref="'request-dialog-'.$request->id"
    x-show="{{ $open }}"
    x-cloak
    x-trap.noscroll="{{ $open }}"
    x-on:click.self="{{ $close }}"
    data-test="request-dialog"
    :class="'text-stone-800 relative '.$background"
>
    <div>
        <x-button variant="clean" size="baseClean" class="absolute top-0 right-0 p-1 m-3" aria-label="Schließen" x-on:click="{{ $close }}"><x-icon name="x" /></x-button>
        <div class="flex items-start -mt-0.5 mb-2">
            <x-icon name="request" :size="22" class="flex-shrink-0 mt-1 mr-2.5" />
            <x-text as="h2" data-test="title_request-dialog">{{ $request->title }}</x-text>
        </div>
        <x-text variant="textSm">Erstellt am {{ $createdAt }}</x-text>
        <div class="mt-8 text-lg" data-test="description_request-dialog">{!! \App\Support\RichText::toHtml($request->description_template, $link) !!}</div>
    </div>
    <div class="mt-10 space-x-4 text-center">
        <x-button as="a" href="{{ $contactHref }}" color="stone" :class="$button" data-test="btn_contact_request-dialog">Kontaktieren</x-button>
        <x-button color="stone" x-on:click="{{ $close }}">Schließen</x-button>
    </div>
</x-dialog>
