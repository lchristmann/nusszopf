@props([
    'variant' => 'view',
    'title' => '',
    'category',
    'createdAt' => null,
    // `hit` only: escaped HTML with the matches in `<em>`.
    'titleHtml' => '',
    'descriptionHtml' => '',
    'edit' => null,
    'delete' => null,
])

{{--
    RequestCard.js. `view` (ViewRequestCard.js): a whole-card button in the
    category's color with a chevron, opening the request. `edit`
    (EditRequestCard.js): the same card, but opening the edit dialog, with a
    `MoreHorizontal` context menu (Bearbeiten / Löschen) in the corner.
    `edit`/`delete` are Alpine expressions.
--}}
@php
    $color = [
        'companions' => ['bg-red-200 border border-red-300 hover:ring-red-300', 'red'],
        'rooms' => ['bg-yellow-200 border border-yellow-400 hover:ring-yellow-400', 'yellow'],
        'materials' => ['bg-turquoise-200 border border-turquoise-300 hover:ring-turquoise-300', 'turquoise'],
        'financials' => ['bg-blue-200 border border-blue-300 hover:ring-blue-300', 'blue'],
        'others' => ['bg-pink-200 border border-pink-300 hover:ring-pink-300', 'pink'],
    ][$category] ?? ['bg-stone-400 border border-stone-600', 'lilac']; // only a tampered wizard form has another category
@endphp

@if ($variant === 'preview')
    {{-- PreviewRequestCard.js: nested inside `EditProjectCard`, not a button — the whole project card is. --}}
    <div data-test="card_preview-request" {{ $attributes->class(['w-full rounded-lg px-3 py-2 text-stone-800', $color[0]]) }}>
        <div class="flex items-start">
            <x-icon name="request" :size="18" class="flex-shrink-0 mt-1.5 mr-1.5" />
            <x-text as="span" variant="textSmMedium" data-test="text_title_preview-request-card">{{ $title }}</x-text>
        </div>
        <x-text variant="textXs">Erstellt am {{ $createdAt }}</x-text>
    </div>
@elseif ($variant === 'hit')
    {{-- HitRequestCard.js: not a button — the whole hit card is the link. --}}
    <div data-test="card_request-hit" {{ $attributes->class(['w-full rounded-lg px-3 py-2 text-stone-800', $color[0]]) }}>
        <div class="flex items-start">
            <x-icon name="request" :size="18" class="flex-shrink-0 mt-1 mr-1.5" />
            <x-text variant="textXs" class="font-medium">{!! $titleHtml !!}</x-text>
        </div>
        @if ($descriptionHtml !== '')
            <x-text variant="textXs">{!! $descriptionHtml !!}</x-text>
        @endif
    </div>
@elseif ($variant === 'edit')
    <div
        data-test="card_request"
        {{ $attributes->class(['relative w-full flex text-stone-800 rounded-lg cursor-pointer transition duration-150 ease-in-out ring-1 ring-transparent focus:outline-none', $color[0]]) }}
    >
        <button type="button" x-on:click="{{ $edit }}" class="flex-1 p-4 text-left focus:outline-none">
            <span class="flex items-start -mt-0.5 mr-4">
                <x-icon name="request" :size="18" class="flex-shrink-0 mt-1.5 mr-1.5" />
                <x-text as="span" variant="textSmMedium" data-test="text_title_request-card">{{ $title }}</x-text>
            </span>
            <x-text as="span" variant="textXs" class="block">Erstellt am {{ $createdAt }}</x-text>
        </button>
        <div class="absolute top-0 right-0">
            <x-menu
                data-test="menu_edit-request-card"
                aria-label="Gesuch Menü"
                label-class="mx-4 mb-1"
                inner-class="py-2 mr-3"
                :color="$color[1]"
                :items="[
                    ['text' => 'Bearbeiten', 'click' => $edit],
                    ['text' => 'Löschen', 'click' => $delete],
                ]"
            ><x-icon name="more-horizontal" /></x-menu>
        </div>
    </div>
@else
    <button
        type="button"
        data-test="card_request"
        {{ $attributes->class(['w-full flex text-stone-800 p-4 justify-between items-center ring-1 ring-transparent duration-150 ease-in-out rounded-lg cursor-pointer outline-none focus:outline-none', $color[0]]) }}
    >
        <span class="block mr-4 text-left">
            <span class="flex items-start -mt-0.5">
                <x-icon name="request" :size="18" class="flex-shrink-0 mt-1.5 mr-1.5" />
                <x-text as="span" variant="textSmMedium" data-test="text_title_request-card">{{ $title }}</x-text>
            </span>
            <x-text as="span" variant="textXs" class="block">Erstellt am {{ $createdAt }}</x-text>
        </span>
        <x-icon name="chevron-right" :size="30" class="flex-shrink-0" />
    </button>
@endif
