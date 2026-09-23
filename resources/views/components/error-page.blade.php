@props(['status' => null])

{{--
    `components/ErrorPage/ErrorPage.js` + `error.data.js`, the one page for
    404, 500 and every other status (`_error.js`). No `NavHeader`; a
    `FrameFullCenter` on `bg-warning-200`. The mail link uses this instance's
    mailbox (decision A-5). The React `ErrorBoundary` has no server-side
    counterpart: an exception while rendering is a 500 and shows this page
    with "500 – " (docs/rewrite/tenth-slice.md, decision 7).
--}}
<x-layout hide-nav-header footer-bg="bg-warning-200">
    <div class="flex flex-col items-center justify-center flex-1 px-6 pt-12 sm:px-16 lg:px-24 xl:px-32 text-stone-800 bg-warning-200" data-test="error-page">
        <div class="max-w-xl mx-auto">
            <x-text as="h1" variant="titleLg" class="sm:text-center">{{ $status ? $status.' – ' : '' }}Nusszopf verknetet...</x-text>
            <x-text variant="textMd" class="mt-8">
                Sorry, es ist ein technisches Problem aufgetreten. Falls der Fehler erneut auftritt, melde dich bitte unter
                <a
                    href="mailto:{{ \App\Support\Operator::contactEmail() }}?subject=Nusszopf verknetet"
                    target="_blank"
                    rel="noopener noreferrer"
                    title="E-Mail an Nusszopf schreiben"
                    aria-label="E-Mail an Nusszopf schreiben"
                    class="border-b-2 cursor-pointer nz-text-md nz-link-warning"
                >{{ \App\Support\Operator::contactEmail() }}</a>.
            </x-text>
            <div class="text-center">
                <x-button as="a" href="{{ url('/') }}" size="large" class="inline-block mt-16 bg-warning-300" title="Zum Nusszopf" aria-label="Zum Nusszopf" data-test="btn_home_error-page">Zum Nusszopf</x-button>
            </div>
        </div>
    </div>
</x-layout>
