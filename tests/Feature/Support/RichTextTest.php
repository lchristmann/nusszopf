<?php

use App\Support\RichText;

/**
 * The historical toolbar's six-tool capability ceiling
 * (docs/rewrite/architecture-decisions.md, "Rich-text editor replacement for
 * Slate"), enforced on the server: nothing the toolbar cannot produce survives
 * normalization, and rendering escapes everything it emits.
 */
function paragraphDoc(array $content): array
{
    return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => $content]]];
}

it('keeps exactly the six historical tools and drops everything else', function () {
    $doc = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'Überschrift']]],
            ['type' => 'blockquote', 'content' => [['type' => 'paragraph']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'fett', 'marks' => [['type' => 'bold'], ['type' => 'strike'], ['type' => 'code'], ['type' => 'italic'], ['type' => 'underline']]],
                ['type' => 'image', 'attrs' => ['src' => 'x']],
                ['type' => 'text', 'text' => 'Link', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org', 'class' => 'evil', 'onclick' => 'x()']]]],
            ]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'a']]]]]]],
            ['type' => 'orderedList', 'attrs' => ['start' => 9], 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]],
        ],
    ];

    $normalized = RichText::normalize($doc);

    expect(array_column($normalized['content'], 'type'))->toBe(['paragraph', 'bulletList', 'orderedList'])
        ->and($normalized['content'][0]['content'])->toHaveCount(2)
        ->and(array_column($normalized['content'][0]['content'][0]['marks'], 'type'))->toBe(['bold', 'italic', 'underline'])
        ->and($normalized['content'][0]['content'][1]['marks'])->toBe([['type' => 'link', 'attrs' => ['href' => 'https://example.org']]])
        ->and($normalized['content'][2])->toBe(['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]]);
});

it('turns garbage into the empty document', function (mixed $garbage) {
    expect(RichText::normalize($garbage))->toBe(RichText::empty());
})->with([[null], ['text'], [[]], [['type' => 'paragraph']], [['type' => 'doc', 'content' => 'x']], [['type' => 'doc', 'content' => []]]]);

it('escapes text and attributes when rendering', function () {
    $html = RichText::toHtml(paragraphDoc([
        ['type' => 'text', 'text' => '<script>alert(1)</script>'],
        ['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://a.test/"><img src=x onerror=alert(1)>']]]],
    ]));

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('<img')
        ->and($html)->toContain('&lt;script&gt;');
});

it('forces links to https using whatever follows the last double slash, as the historical serializer did', function (string $input, string $expected) {
    expect(RichText::url($input))->toBe($expected);
})->with([
    ['example.org', 'https://example.org'],
    ['http://example.org/a', 'https://example.org/a'],
    ['https://example.org', 'https://example.org'],
    ['javascript:alert(1)', 'https://javascript:alert(1)'],
    ['//evil.test', 'https://evil.test'],
]);

it('renders the historical markup for marks, lists and links', function () {
    $html = RichText::toHtml(['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'b', 'marks' => [['type' => 'bold']]],
            ['type' => 'text', 'text' => 'i', 'marks' => [['type' => 'italic']]],
            ['type' => 'text', 'text' => 'u', 'marks' => [['type' => 'underline']]],
        ]],
        ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'eins']]]]]]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'zwei']]]]]]],
    ]]);

    expect($html)->toContain('<span class="font-medium">b</span>')
        ->and($html)->toContain('<i>i</i>')
        ->and($html)->toContain('<u>u</u>')
        ->and($html)->toContain('<ul class="ml-8 list-disc"><li>eins</li></ul>')
        ->and($html)->toContain('<ol class="ml-8 list-decimal"><li>zwei</li></ol>');
});

it('treats only a lone textless paragraph as empty, like the historical required test', function () {
    expect(RichText::isEmpty(RichText::empty()))->toBeTrue()
        ->and(RichText::isEmpty(paragraphDoc([['type' => 'text', 'text' => ' ']])))->toBeFalse()
        ->and(RichText::isEmpty(paragraphDoc([['type' => 'text', 'text' => 'x']])))->toBeFalse()
        ->and(RichText::isEmpty(['type' => 'doc', 'content' => [['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph']]]]]]]))->toBeFalse()
        ->and(RichText::isEmpty(['type' => 'doc', 'content' => [['type' => 'paragraph'], ['type' => 'paragraph']]]))->toBeFalse();
});

it('projects a document to collapsed plain text for search', function () {
    $doc = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => "Hallo   \n Welt", 'marks' => [['type' => 'bold']]]]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'eins']]]]],
            ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'zwei']]]]],
        ]],
    ]];

    expect(RichText::toPlainText($doc))->toBe('Hallo Welt eins zwei');
});

it('rebuilds a document from the first slice\'s plain-text description', function () {
    $doc = RichText::fromPlainText("Zeile eins\n\nZeile drei");

    expect(RichText::normalize($doc))->toBe($doc)
        ->and(RichText::toPlainText($doc))->toBe('Zeile eins Zeile drei');
});
