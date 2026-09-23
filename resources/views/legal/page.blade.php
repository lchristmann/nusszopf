{{--
    `pages/{legalNotice,legalPolicy,privacy}.js`: `bg-steel-200 text-steel-800`,
    `Frame my-12 sm:my-20`, a `max-w-2xl` column with the `titleMd` heading.
    The body is the operator's Markdown (`.nz-legal` maps it onto the
    historical section styles); until it exists, an `InfoCard` says so
    (decision A-4 — the project writes no legal text).
--}}
<x-layout main-class="bg-steel-200 text-steel-800" footer-bg="bg-steel-200" :go-back-uri="$goBackUri">
    <x-frame class="my-12 sm:my-20">
        <div class="max-w-2xl mx-auto">
            <x-text as="h1" variant="titleMd" class="mb-8">{{ $heading }}</x-text>
            @if ($html !== null)
                <div class="nz-legal" data-test="legal-content">{!! $html !!}</div>
            @else
                <x-info-card data-test="legal-not-configured">
                    Dieser Text wurde von den Betreiber:innen dieser Nusszopf-Instanz noch nicht hinterlegt.
                </x-info-card>
            @endif
        </div>
    </x-frame>
</x-layout>
