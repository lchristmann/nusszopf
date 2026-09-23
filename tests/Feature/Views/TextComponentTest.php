<?php

use Illuminate\Support\Facades\Blade;

/**
 * `Text.atom.js`, found in the visual parity pass (P-2): the default variant is
 * `textMd`, and every non-heading text hyphenates.
 */
it('defaults to textMd, as the historical Text atom', function () {
    expect(trim(Blade::render('<x-text>Hallo</x-text>')))->toBe('<p class="nz-text-md hyphens-auto">Hallo</p>');
});

it('hyphenates body text but never a heading', function () {
    expect(trim(Blade::render('<x-text variant="textSm" class="mt-2">a</x-text>')))->toBe('<p class="nz-text-sm hyphens-auto mt-2">a</p>')
        ->and(trim(Blade::render('<x-text as="span" variant="textXs">a</x-text>')))->toContain('hyphens-auto')
        ->and(trim(Blade::render('<x-text as="h1" variant="textLg">a</x-text>')))->toBe('<h1 class="nz-text-lg">a</h1>')
        ->and(trim(Blade::render('<x-text as="h2">a</x-text>')))->not->toContain('hyphens-auto');
});
