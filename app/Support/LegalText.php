<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The operator's own legal texts (decision A-4): Markdown files in
 * `config('nusszopf.legal_path')`. The project ships none — a page whose file
 * is missing or empty says so instead (docs/handbuch/installation.md).
 */
final class LegalText
{
    /** Page => [file, heading] (`legal-notice/legal-policy/privacy.data.js` titles). */
    public const PAGES = [
        'notice' => ['legal-notice.md', 'Impressum'],
        'policy' => ['legal-policy.md', 'Rechtliches'],
        'privacy' => ['privacy.md', 'Datenschutz'],
    ];

    public static function path(string $page): string
    {
        return rtrim((string) config('nusszopf.legal_path'), '/').'/'.self::PAGES[$page][0];
    }

    /** Rendered HTML, or null while the operator has not provided the text. */
    public static function html(string $page): ?string
    {
        $path = self::path($page);
        $markdown = is_file($path) && is_readable($path) ? (string) file_get_contents($path) : '';

        if (trim($markdown) === '') {
            return null;
        }

        // The operator's file is trusted content, but raw HTML is still escaped
        // and `javascript:` links dropped: Markdown is all the pages need.
        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
