<?php

namespace App\Support;

/**
 * The stable document representation behind the historical rich-text fields
 * (`description`, `team`) — a ProseMirror/TipTap-style JSON tree replacing the
 * historical Slate tree (docs/rewrite/architecture-decisions.md, "Rich-text
 * editor replacement for Slate").
 *
 * The capability ceiling is the historical toolbar's six tools, exactly:
 * bold/italic/underline marks, bullet/ordered lists and a link. Everything
 * else in an incoming document is dropped by {@see self::normalize()}, so what
 * is persisted and rendered is a whitelist by construction — never client
 * markup. Rendering ({@see self::toHtml()}) escapes every string it emits.
 *
 *   doc        := { type: 'doc', content: block+ }
 *   block      := paragraph | bulletList | orderedList
 *   paragraph  := { type: 'paragraph', content?: text* }
 *   list       := { type: 'bulletList'|'orderedList', content: listItem+ }
 *   listItem   := { type: 'listItem', content: paragraph+ }
 *   text       := { type: 'text', text: string, marks?: (bold|italic|underline|link{href})* }
 */
final class RichText
{
    private const MARKS = ['bold', 'italic', 'underline'];

    /**
     * @return array<string, mixed>
     */
    public static function empty(): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph']]];
    }

    /**
     * Wraps plain text (e.g. a first-slice `description`, which predates the
     * structured document) as one paragraph per line.
     *
     * @return array<string, mixed>
     */
    public static function fromPlainText(string $text): array
    {
        $paragraphs = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $paragraphs[] = $line === ''
                ? ['type' => 'paragraph']
                : ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $line]]];
        }

        return ['type' => 'doc', 'content' => $paragraphs ?: [['type' => 'paragraph']]];
    }

    /**
     * Reduces an arbitrary (possibly tampered) value to a valid document.
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $doc): array
    {
        if (! is_array($doc) || ($doc['type'] ?? null) !== 'doc' || ! is_array($doc['content'] ?? null)) {
            return self::empty();
        }

        $blocks = [];

        foreach ($doc['content'] as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = $block['type'] ?? null;

            if ($type === 'paragraph') {
                $blocks[] = self::paragraph($block);
            } elseif ($type === 'bulletList' || $type === 'orderedList') {
                $items = [];

                foreach (is_array($block['content'] ?? null) ? $block['content'] : [] as $item) {
                    if (! is_array($item) || ($item['type'] ?? null) !== 'listItem') {
                        continue;
                    }

                    $paragraphs = [];

                    foreach (is_array($item['content'] ?? null) ? $item['content'] : [] as $child) {
                        if (is_array($child) && ($child['type'] ?? null) === 'paragraph') {
                            $paragraphs[] = self::paragraph($child);
                        }
                    }

                    $items[] = ['type' => 'listItem', 'content' => $paragraphs ?: [['type' => 'paragraph']]];
                }

                if ($items !== []) {
                    $blocks[] = ['type' => $type, 'content' => $items];
                }
            }
        }

        return ['type' => 'doc', 'content' => $blocks ?: [['type' => 'paragraph']]];
    }

    /**
     * @param  array<mixed>  $paragraph
     * @return array<string, mixed>
     */
    private static function paragraph(array $paragraph): array
    {
        $nodes = [];

        foreach (is_array($paragraph['content'] ?? null) ? $paragraph['content'] : [] as $node) {
            if (! is_array($node) || ($node['type'] ?? null) !== 'text' || ! is_string($node['text'] ?? null) || $node['text'] === '') {
                continue;
            }

            $marks = [];

            foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
                $markType = is_array($mark) ? ($mark['type'] ?? null) : null;

                if (in_array($markType, self::MARKS, true)) {
                    $marks[$markType] = ['type' => $markType];
                } elseif ($markType === 'link' && is_string($mark['attrs']['href'] ?? null) && $mark['attrs']['href'] !== '') {
                    $marks['link'] = ['type' => 'link', 'attrs' => ['href' => $mark['attrs']['href']]];
                }
            }

            $normalized = ['type' => 'text', 'text' => $node['text']];

            if ($marks !== []) {
                $normalized['marks'] = array_values($marks);
            }

            $nodes[] = $normalized;
        }

        return $nodes === [] ? ['type' => 'paragraph'] : ['type' => 'paragraph', 'content' => $nodes];
    }

    /**
     * The historical "required" test only rejected a document consisting of a
     * single, textless paragraph (`ProjectField.js`, `description_required`);
     * whitespace counts as content, and a lone empty list item is not empty.
     *
     * @param  array<string, mixed>  $doc
     */
    public static function isEmpty(array $doc): bool
    {
        $doc = self::normalize($doc);

        return count($doc['content']) === 1
            && $doc['content'][0]['type'] === 'paragraph'
            && self::plainText($doc['content'][0]) === '';
    }

    /**
     * The plain-text projection stored alongside the document
     * (`description`/`team`, used for search): block text joined by a space,
     * whitespace collapsed, trimmed — as `projects.service.js` did.
     *
     * @param  array<string, mixed>  $doc
     */
    public static function toPlainText(array $doc): string
    {
        $text = self::plainText(self::normalize($doc));

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * @param  array<mixed>  $node
     */
    private static function plainText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) $node['text'];
        }

        $children = array_map(fn (array $child): string => self::plainText($child), $node['content'] ?? []);

        return implode(in_array($node['type'] ?? null, ['doc', 'bulletList', 'orderedList'], true) ? ' ' : '', $children);
    }

    /**
     * Server-rendered HTML, matching `useRichTextEditor.serializeJSX`'s markup:
     * bold is `font-medium`, italic `<i>`, underline `<u>`, lists `ml-8
     * list-disc|list-decimal`, paragraphs `textSm`, links `https://` + whatever
     * follows the last `//` (which also neutralises `javascript:` URLs).
     *
     * @param  array<string, mixed>  $doc
     */
    public static function toHtml(array $doc, string $linkClass = 'nz-link-lilac'): string
    {
        $html = '';

        foreach (self::normalize($doc)['content'] as $block) {
            $html .= match ($block['type']) {
                'bulletList' => '<ul class="ml-8 list-disc">'.self::items($block, $linkClass).'</ul>',
                'orderedList' => '<ol class="ml-8 list-decimal">'.self::items($block, $linkClass).'</ol>',
                default => self::paragraphHtml($block, $linkClass),
            };
        }

        return $html;
    }

    /**
     * @param  array<mixed>  $list
     */
    private static function items(array $list, string $linkClass): string
    {
        return implode('', array_map(
            fn (array $item): string => '<li>'.implode('', array_map(
                fn (array $paragraph): string => self::inline($paragraph, $linkClass),
                $item['content'],
            )).'</li>',
            $list['content'],
        ));
    }

    /**
     * @param  array<mixed>  $paragraph
     */
    private static function paragraphHtml(array $paragraph, string $linkClass): string
    {
        $inner = self::inline($paragraph, $linkClass);

        // Slate rendered an empty text leaf as a `block my-2` span.
        return '<p class="nz-text-sm hyphens-auto">'.($inner === '' ? '<span class="block my-2"></span>' : $inner).'</p>';
    }

    /**
     * @param  array<mixed>  $paragraph
     */
    private static function inline(array $paragraph, string $linkClass): string
    {
        $html = '';

        foreach ($paragraph['content'] ?? [] as $node) {
            $out = e($node['text']);
            $link = null;

            foreach ($node['marks'] ?? [] as $mark) {
                if ($mark['type'] === 'bold') {
                    $out = '<span class="font-medium">'.$out.'</span>';
                } elseif ($mark['type'] === 'italic') {
                    $out = '<i>'.$out.'</i>';
                } elseif ($mark['type'] === 'underline') {
                    $out = '<u>'.$out.'</u>';
                } elseif ($mark['type'] === 'link') {
                    $link = $mark['attrs']['href'];
                }
            }

            if ($link !== null) {
                $url = e(self::url($link));
                $out = '<a class="nz-text-sm cursor-pointer border-b-2 '.e($linkClass).'" href="'.$url.'" title="'.e($link).'" aria-label="'.e($link).'" rel="noopener noreferrer" target="_blank">'.$out.'</a>';
            }

            $html .= $out;
        }

        return $html;
    }

    public static function url(string $url): string
    {
        $parts = explode('//', $url);

        return 'https://'.end($parts);
    }
}
