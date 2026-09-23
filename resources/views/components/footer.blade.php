{{--
    `Footer.organism.js` (docs/design/navigation.md). `classy` is Home's:
    the three legal routes, then Instagram. The "Powered by Vercel" badge
    that closes `classy` and is all of the default `vercel` variant is not
    reproduced — it names the hosting platform, and a self-hosted instance
    does not run on Vercel (docs/rewrite/tenth-slice.md, decision 5; same
    reasoning as the Auth0 badge, seventh slice). The default variant keeps
    its band at the badge's height so every page's composition is unchanged.
--}}

@props(['bg' => 'bg-steel-200', 'variant' => 'default'])

<x-frame as="footer" class="{{ $bg }}" data-test="footer">
    @if ($variant === 'classy')
        <div class="flex flex-col items-center justify-between py-6 md:flex-row">
            <div class="flex flex-col items-start w-full space-y-2.5 sm:space-y-0 sm:items-center sm:justify-center sm:flex-row md:justify-start">
                @foreach ([['legal.notice', 'Impressum'], ['privacy', 'Datenschutz'], ['legal.policy', 'Rechtliches']] as [$route, $label])
                    <a
                        href="{{ route($route) }}"
                        title="{{ $label }}"
                        aria-label="{{ $label }}"
                        @class(['nz-text-sm cursor-pointer text-current border-b-2 active:border-current hover:border-current', 'mr-8' => ! $loop->last])
                    >{{ $label }}</a>
                @endforeach
            </div>
            <div class="flex items-center mt-6 md:mt-0">
                <a href="https://www.instagram.com/nuss.zopf" target="_blank" rel="noopener noreferrer" title="Zu Instagram" aria-label="Zu Instagram" class="flex-shrink-0 inline-block cursor-pointer">
                    <x-icon name="instagram" :size="28" :stroke-width="2" />
                </a>
            </div>
        </div>
    @else
        {{-- 33px: the badge's inline-block link line box, measured against the running historical app (P-2). --}}
        <div class="py-6"><div class="h-[33px]"></div></div>
    @endif
</x-frame>
