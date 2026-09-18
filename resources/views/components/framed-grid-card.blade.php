{{--
    The dominant "app screen" layout (docs/design/components.md, `Templates`):
    a boxed header + white card body used by every authenticated/app screen
    (project create/detail, search, etc). Callers compose:

        <x-framed-grid-card>
            <x-frame class="bg-lilac-300 rounded-t-lg">
                <x-framed-grid-card.header>...</x-framed-grid-card.header>
            </x-frame>
            <x-frame class="bg-white rounded-b-lg">
                <x-framed-grid-card.body>
                    <x-framed-grid-card.body-col>...</x-framed-grid-card.body-col>
                </x-framed-grid-card.body>
            </x-frame>
        </x-framed-grid-card>

    This is an explicit-nesting Blade translation of the historical
    React component's implicit child-type detection — same visual output,
    a Blade-idiomatic composition mechanism instead.
--}}

<div {{ $attributes }}>
    {{ $slot }}
</div>
