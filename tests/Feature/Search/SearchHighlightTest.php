<?php

use App\Support\SearchHighlight;

$open = SearchHighlight::OPEN;
$close = SearchHighlight::CLOSE;

it('escapes the text and turns only the markers into emphasis', function () use ($open, $close) {
    expect(SearchHighlight::html("Ein {$open}Baum{$close} <script>alert(1)</script> & \"Mehr\""))
        ->toBe('Ein <em>Baum</em> &lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;Mehr&quot;');
});

it('leaves text within the length untouched', function () use ($open, $close) {
    $text = "Kurz {$open}mit{$close} Treffer";

    expect(SearchHighlight::truncate($text, 90))->toBe($text);
});

it('cuts like lodash truncate: at most the length in visible characters, ending in three dots', function () {
    $text = str_repeat('a', 100);

    expect(SearchHighlight::truncate($text, 90))->toBe(str_repeat('a', 87).'...')
        ->and(mb_strlen(SearchHighlight::truncate($text, 90)))->toBe(90);
});

it('does not count the markers and closes a highlight the cut falls inside', function () use ($open, $close) {
    $text = "{$open}".str_repeat('b', 20)."{$close}".str_repeat('c', 20);

    $cut = SearchHighlight::truncate($text, 13);

    expect(SearchHighlight::plain($cut))->toBe(str_repeat('b', 10).'...')
        ->and($cut)->toBe("{$open}".str_repeat('b', 10)."{$close}...");
});

it('counts characters, not bytes', function () {
    expect(SearchHighlight::truncate(str_repeat('ä', 95), 90))->toBe(str_repeat('ä', 87).'...');
});
